<?php
/**
 * MediSeba - Admin Controller
 *
 * Handles admin-only operations such as doctor verification approval/rejection.
 */

declare(strict_types=1);

namespace MediSeba\Controllers;

use MediSeba\Config\Database;
use MediSeba\Models\DoctorProfile;
use MediSeba\Models\User;
use MediSeba\Utils\Response;
use MediSeba\Utils\Security;
use Throwable;

class AdminController
{
    private DoctorProfile $doctorModel;
    private User $userModel;
    private \PDO $db;

    public function __construct()
    {
        $this->doctorModel = new DoctorProfile();
        $this->userModel = new User();
        $this->db = Database::getConnection();
    }

    /**
     * List doctor verification queue.
     * GET /api/admin/doctors
     */
    public function doctorQueue(array $request, array $user): void
    {
        $status = strtolower(trim((string) ($request['status'] ?? 'pending')));
        if (!in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
            $status = 'pending';
        }

        $search = trim((string) ($request['search'] ?? ''));
        $page = max(1, (int) ($request['page'] ?? 1));
        $perPage = (int) ($request['per_page'] ?? 20);
        $perPage = max(5, min(100, $perPage));
        $offset = ($page - 1) * $perPage;

        [$whereClause, $params] = $this->buildDoctorQueueWhere($status, $search);

        $countSql = "SELECT COUNT(*)
                     FROM doctor_profiles dp
                     INNER JOIN users u ON u.id = dp.user_id
                     WHERE u.role = 'doctor' {$whereClause}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT
                    dp.id,
                    dp.user_id,
                    dp.full_name,
                    dp.specialty,
                    dp.qualification,
                    dp.experience_years,
                    dp.consultation_fee,
                    dp.clinic_name,
                    dp.registration_number,
                    dp.profile_photo,
                    dp.is_verified,
                    dp.created_at,
                    dp.updated_at,
                    u.email AS user_email,
                    u.status AS user_status
                FROM doctor_profiles dp
                INNER JOIN users u ON u.id = dp.user_id
                WHERE u.role = 'doctor' {$whereClause}
                ORDER BY dp.created_at DESC
                LIMIT ? OFFSET ?";

        $listStmt = $this->db->prepare($sql);
        $listStmt->execute([...$params, $perPage, $offset]);
        $rows = $listStmt->fetchAll() ?: [];

        $summary = $this->getDoctorQueueSummary();
        $items = array_map(function (array $row): array {
            return [
                'id' => (int) $row['id'],
                'user_id' => (int) $row['user_id'],
                'full_name' => (string) $row['full_name'],
                'specialty' => (string) $row['specialty'],
                'qualification' => (string) $row['qualification'],
                'experience_years' => (int) $row['experience_years'],
                'consultation_fee' => (float) $row['consultation_fee'],
                'clinic_name' => $row['clinic_name'] !== null ? (string) $row['clinic_name'] : null,
                'registration_number' => $row['registration_number'] !== null ? (string) $row['registration_number'] : null,
                'profile_photo' => $row['profile_photo'] !== null ? (string) $row['profile_photo'] : null,
                'is_verified' => (bool) $row['is_verified'],
                'user_email' => (string) $row['user_email'],
                'user_status' => (string) $row['user_status'],
                'queue_status' => $this->resolveQueueStatus((bool) $row['is_verified'], (string) $row['user_status']),
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at']
            ];
        }, $rows);

        Response::success('Doctor verification queue retrieved', [
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'total_pages' => (int) ceil($total / $perPage),
                'has_next_page' => ($offset + $perPage) < $total,
                'has_prev_page' => $page > 1
            ],
            'summary' => $summary,
            'filters' => [
                'status' => $status,
                'search' => $search
            ]
        ]);
    }

    /**
     * Approve doctor profile.
     * POST /api/admin/doctors/{id}/approve
     */
    public function approveDoctor(int $id, array $request, array $user): void
    {
        $this->updateDoctorVerification($id, true, $request, $user);
    }

    /**
     * Reject doctor profile.
     * POST /api/admin/doctors/{id}/reject
     */
    public function rejectDoctor(int $id, array $request, array $user): void
    {
        $this->updateDoctorVerification($id, false, $request, $user);
    }

    private function updateDoctorVerification(int $doctorProfileId, bool $approved, array $request, array $adminUser): void
    {
        $note = trim((string) ($request['note'] ?? $request['reason'] ?? ''));
        if (strlen($note) > 500) {
            Response::validationError([
                'note' => ['The note must not exceed 500 characters.']
            ]);
        }

        $doctor = $this->doctorModel->find($doctorProfileId);
        if (!$doctor) {
            Response::notFound('Doctor profile');
        }

        $doctorUser = $this->userModel->find((int) $doctor['user_id']);
        if (!$doctorUser || ($doctorUser['role'] ?? '') !== 'doctor') {
            Response::notFound('Doctor account');
        }

        $oldValues = [
            'is_verified' => (bool) ($doctor['is_verified'] ?? false),
            'user_status' => (string) ($doctorUser['status'] ?? 'active')
        ];

        $nextVerification = $approved;
        $nextUserStatus = $approved ? 'active' : 'suspended';
        $action = $approved ? 'admin_doctor_approved' : 'admin_doctor_rejected';
        $message = $approved ? 'Doctor approved successfully' : 'Doctor rejected successfully';

        try {
            $this->doctorModel->beginTransaction();

            $doctorUpdated = $this->doctorModel->update($doctorProfileId, [
                'is_verified' => $nextVerification
            ]);

            $userUpdated = $this->userModel->updateStatus((int) $doctor['user_id'], $nextUserStatus);

            if (!$doctorUpdated || !$userUpdated) {
                throw new \RuntimeException('Unable to update doctor verification.');
            }

            $this->doctorModel->commit();
        } catch (Throwable $e) {
            if (Database::inTransaction()) {
                Database::rollback();
            }

            Response::serverError('Failed to update doctor verification status.');
        }

        $updatedDoctor = $this->doctorModel->find($doctorProfileId);
        $updatedUser = $this->userModel->find((int) $doctor['user_id']);

        $newValues = [
            'is_verified' => (bool) ($updatedDoctor['is_verified'] ?? false),
            'user_status' => (string) ($updatedUser['status'] ?? $nextUserStatus),
            'note' => $note !== '' ? $note : null
        ];

        $this->logAdminVerificationAction(
            (int) $adminUser['user_id'],
            $action,
            $doctorProfileId,
            $oldValues,
            $newValues
        );

        Response::success($message, [
            'doctor' => [
                'id' => (int) ($updatedDoctor['id'] ?? $doctorProfileId),
                'full_name' => (string) ($updatedDoctor['full_name'] ?? $doctor['full_name']),
                'is_verified' => (bool) ($updatedDoctor['is_verified'] ?? $nextVerification)
            ],
            'user' => [
                'id' => (int) ($updatedUser['id'] ?? $doctor['user_id']),
                'status' => (string) ($updatedUser['status'] ?? $nextUserStatus)
            ],
            'queue_status' => $this->resolveQueueStatus(
                (bool) ($updatedDoctor['is_verified'] ?? $nextVerification),
                (string) ($updatedUser['status'] ?? $nextUserStatus)
            ),
            'note' => $note !== '' ? $note : null
        ]);
    }

    private function buildDoctorQueueWhere(string $status, string $search): array
    {
        $conditions = [];
        $params = [];

        switch ($status) {
            case 'approved':
                $conditions[] = 'dp.is_verified = 1 AND u.status = ?';
                $params[] = 'active';
                break;
            case 'rejected':
                $conditions[] = 'dp.is_verified = 0 AND u.status IN (?, ?, ?)';
                $params[] = 'inactive';
                $params[] = 'suspended';
                $params[] = 'deleted';
                break;
            case 'pending':
                $conditions[] = 'dp.is_verified = 0 AND u.status = ?';
                $params[] = 'active';
                break;
            case 'all':
            default:
                // no additional status filter
                break;
        }

        if ($search !== '') {
            $conditions[] = '(dp.full_name LIKE ? OR dp.specialty LIKE ? OR dp.registration_number LIKE ? OR u.email LIKE ?)';
            $term = "%{$search}%";
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
            $params[] = $term;
        }

        if (empty($conditions)) {
            return ['', $params];
        }

        return [' AND ' . implode(' AND ', $conditions), $params];
    }

    private function getDoctorQueueSummary(): array
    {
        $sql = "SELECT
                    COUNT(*) AS total,
                    SUM(CASE WHEN dp.is_verified = 0 AND u.status = 'active' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN dp.is_verified = 1 AND u.status = 'active' THEN 1 ELSE 0 END) AS approved,
                    SUM(CASE WHEN dp.is_verified = 0 AND u.status IN ('inactive', 'suspended', 'deleted') THEN 1 ELSE 0 END) AS rejected
                FROM doctor_profiles dp
                INNER JOIN users u ON u.id = dp.user_id
                WHERE u.role = 'doctor'";

        $row = $this->db->query($sql)->fetch() ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
            'approved' => (int) ($row['approved'] ?? 0),
            'rejected' => (int) ($row['rejected'] ?? 0)
        ];
    }

    private function resolveQueueStatus(bool $isVerified, string $userStatus): string
    {
        if ($isVerified && $userStatus === 'active') {
            return 'approved';
        }

        if (!$isVerified && $userStatus === 'active') {
            return 'pending';
        }

        return 'rejected';
    }

    private function logAdminVerificationAction(
        int $adminUserId,
        string $action,
        int $doctorProfileId,
        array $oldValues,
        array $newValues
    ): void {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO activity_logs
                    (user_id, action, entity_type, entity_id, old_values, new_values, ip_address, user_agent)
                 VALUES
                    (?, ?, 'doctor_profile', ?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $adminUserId,
                $action,
                $doctorProfileId,
                json_encode($oldValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($newValues, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                Security::getClientIp(),
                Security::getUserAgent()
            ]);
        } catch (Throwable $e) {
            error_log('Failed to insert admin verification activity log: ' . $e->getMessage());
        }
    }
}
