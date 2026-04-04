<?php
/**
 * MediSeba - Appointment Chat Controller
 *
 * Provides polling-based chat for patients and doctors around an appointment.
 */

declare(strict_types=1);

namespace MediSeba\Controllers;

use MediSeba\Models\Appointment;
use MediSeba\Models\AppointmentChatMessage;
use MediSeba\Models\DoctorProfile;
use MediSeba\Models\PatientProfile;
use MediSeba\Utils\Response;
use MediSeba\Utils\Validator;

class ChatController
{
    private Appointment $appointmentModel;
    private AppointmentChatMessage $messageModel;
    private DoctorProfile $doctorModel;
    private PatientProfile $patientModel;

    public function __construct()
    {
        $this->appointmentModel = new Appointment();
        $this->messageModel = new AppointmentChatMessage();
        $this->doctorModel = new DoctorProfile();
        $this->patientModel = new PatientProfile();
    }

    /**
     * Get appointment chat messages.
     * GET /api/chats/appointments/{id}
     */
    public function conversation(int $id, array $request, array $user): void
    {
        try {
            [$appointment, $participant] = $this->getAuthorizedAppointmentContext($id, $user);
            $sinceId = isset($request['since_id']) ? max(0, (int) $request['since_id']) : null;
            $messages = $this->messageModel->getConversation($id, $sinceId);

            Response::success('Appointment chat retrieved', [
                'appointment' => $this->buildAppointmentPayload($appointment),
                'messages' => $messages,
                'participants' => [
                    'patient_name' => $appointment['patient_name'] ?? 'Patient',
                    'patient_email' => $appointment['patient_email'] ?? '',
                    'doctor_name' => $appointment['doctor_name'] ?? 'Doctor',
                    'specialty' => $appointment['specialty'] ?? ''
                ],
                'meta' => [
                    'current_user_role' => $participant['role'],
                    'chat_enabled' => $this->isChatEnabledForAppointment($appointment),
                    'message_count' => $this->messageModel->countByAppointment($id),
                    'last_message_id' => $this->messageModel->getLastMessageId($id)
                ]
            ]);
        } catch (\Throwable $e) {
            $this->handleChatFailure($e);
        }
    }

    /**
     * Send a new appointment chat message.
     * POST /api/chats/appointments/{id}
     */
    public function send(int $id, array $request, array $user): void
    {
        try {
            [$appointment, $participant] = $this->getAuthorizedAppointmentContext($id, $user);

            if (!$this->isChatEnabledForAppointment($appointment)) {
                Response::error(
                    'Chat is not available for cancelled or missed appointments.',
                    [],
                    Response::HTTP_UNPROCESSABLE
                );
            }

            $validator = Validator::quick($request, [
                'message' => 'required|max:2000'
            ]);

            if (!$validator['valid']) {
                Response::validationError($validator['errors']);
            }

            $messageText = trim((string) ($request['message'] ?? ''));

            if ($messageText === '') {
                Response::validationError([
                    'message' => 'Please enter a message before sending.'
                ]);
            }

            $message = $this->messageModel->createMessage(
                $id,
                (int) $participant['sender_user_id'],
                $participant['role'],
                $messageText
            );

            Response::created('Message sent successfully', [
                'message' => $message,
                'meta' => [
                    'last_message_id' => $message['id']
                ]
            ]);
        } catch (\Throwable $e) {
            $this->handleChatFailure($e);
        }
    }

    private function getAuthorizedAppointmentContext(int $appointmentId, array $user): array
    {
        if (!in_array($user['role'] ?? '', ['patient', 'doctor'], true)) {
            Response::forbidden('Chat is only available for patients and doctors.');
        }

        $appointment = $this->appointmentModel->getFullDetails($appointmentId);

        if (!$appointment) {
            Response::notFound('Appointment');
        }

        if (($user['role'] ?? '') === 'patient') {
            $patient = $this->patientModel->findByUserId((int) $user['user_id']);

            if (!$patient || (int) $appointment['patient_id'] !== (int) $patient['id']) {
                Response::forbidden('You can only access chat for your own appointments.');
            }

            return [$appointment, [
                'role' => 'patient',
                'sender_user_id' => (int) $user['user_id']
            ]];
        }

        $doctor = $this->doctorModel->findByUserId((int) $user['user_id']);

        if (!$doctor || (int) $appointment['doctor_id'] !== (int) $doctor['id']) {
            Response::forbidden('You can only access chat for your own appointments.');
        }

        return [$appointment, [
            'role' => 'doctor',
            'sender_user_id' => (int) $user['user_id']
        ]];
    }

    private function buildAppointmentPayload(array $appointment): array
    {
        return [
            'id' => (int) ($appointment['id'] ?? 0),
            'appointment_number' => $appointment['appointment_number'] ?? '',
            'appointment_date' => $appointment['appointment_date'] ?? null,
            'estimated_time' => $appointment['estimated_time'] ?? null,
            'status' => $appointment['status'] ?? 'pending',
            'doctor_name' => $appointment['doctor_name'] ?? 'Doctor',
            'patient_name' => $appointment['patient_name'] ?? 'Patient',
            'specialty' => $appointment['specialty'] ?? '',
            'clinic_name' => $appointment['clinic_name'] ?? '',
            'clinic_address' => $appointment['clinic_address'] ?? ''
        ];
    }

    private function isChatEnabledForAppointment(array $appointment): bool
    {
        return !in_array($appointment['status'] ?? '', ['cancelled', 'no_show'], true);
    }

    private function handleChatFailure(\Throwable $e): void
    {
        error_log('Appointment chat error: ' . $e->getMessage());

        if ($this->isMissingChatTableError($e)) {
            Response::serverError('Chat table is missing. Import database/appointment_chat_messages.sql first.');
        }

        Response::serverError('Unable to process the chat request right now.');
    }

    private function isMissingChatTableError(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());
        $code = strtoupper((string) $e->getCode());

        return $code === '42S02'
            || str_contains($message, 'appointment_chat_messages')
            || str_contains($message, 'base table')
            || str_contains($message, "doesn't exist");
    }
}
