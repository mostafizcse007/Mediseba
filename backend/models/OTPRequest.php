<?php
/**
 * MediSeba - OTP Request Model
 * 
 * Handles OTP generation, storage, and verification
 */

declare(strict_types=1);

namespace MediSeba\Models;

use MediSeba\Utils\Security;
use MediSeba\Config\Environment;

class OTPRequest extends Model
{
    protected string $table = 'otp_requests';
    protected string $primaryKey = 'id';
    
    protected array $fillable = [
        'user_id',
        'email',
        'otp_hash',
        'attempts',
        'max_attempts',
        'expires_at',
        'verified_at',
        'ip_address',
        'user_agent'
    ];
    
    protected array $casts = [
        'id' => 'int',
        'user_id' => 'int',
        'attempts' => 'int',
        'max_attempts' => 'int',
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'created_at' => 'datetime'
    ];
    
    /**
     * Create new OTP request
     */
    public function createRequest(?int $userId, string $email, string $ipAddress, string $userAgent): array
    {
        $otp = Security::generateOTP();
        $otpHash = Security::hashOTP($otp);
        $expiryMinutes = (int) Environment::get('OTP_EXPIRY_MINUTES', 5);
        
        $id = $this->create([
            'user_id' => $userId,
            'email' => filter_var($email, FILTER_SANITIZE_EMAIL),
            'otp_hash' => $otpHash,
            'attempts' => 0,
            'max_attempts' => (int) Environment::get('OTP_MAX_ATTEMPTS', 3),
            'expires_at' => date('Y-m-d H:i:s', strtotime("+{$expiryMinutes} minutes")),
            'ip_address' => $ipAddress,
            'user_agent' => substr($userAgent, 0, 500)
        ]);
        
        return [
            'id' => $id,
            'otp' => $otp, // Return plain OTP for email sending
            'expires_at' => date('Y-m-d H:i:s', strtotime("+{$expiryMinutes} minutes"))
        ];
    }
    
    /**
     * Get latest valid OTP request for email
     */
    public function getLatestValidRequest(string $email): ?array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE email = ? 
                AND expires_at > NOW() 
                AND verified_at IS NULL 
                AND attempts < max_attempts
                ORDER BY created_at DESC 
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$email]);
        $result = $stmt->fetch();
        
        return $result ? $this->processResult($result) : null;
    }
    
    /**
     * Verify OTP
     */
    public function verifyOTP(int $requestId, string $otp): array
    {
        $request = $this->find($requestId);
        
        if (!$request) {
            return [
                'success' => false,
                'message' => 'Invalid OTP request'
            ];
        }
        
        // Check if already verified
        if ($request['verified_at'] !== null) {
            return [
                'success' => false,
                'message' => 'OTP already used'
            ];
        }
        
        // Check if expired
        if (strtotime($request['expires_at']) < time()) {
            return [
                'success' => false,
                'message' => 'OTP has expired'
            ];
        }
        
        // Check max attempts
        if ($request['attempts'] >= $request['max_attempts']) {
            return [
                'success' => false,
                'message' => 'Maximum attempts exceeded'
            ];
        }
        
        // Increment attempts
        $this->incrementAttempts($requestId);
        
        // Verify OTP
        if (!Security::verifyOTP($otp, $request['otp_hash'])) {
            $remaining = $request['max_attempts'] - ($request['attempts'] + 1);
            return [
                'success' => false,
                'message' => 'Invalid OTP',
                'remaining_attempts' => max(0, $remaining)
            ];
        }
        
        // Mark as verified
        $this->markVerified($requestId);
        
        return [
            'success' => true,
            'message' => 'OTP verified successfully',
            'user_id' => $request['user_id'],
            'email' => $request['email']
        ];
    }
    
    /**
     * Increment attempt counter
     */
    public function incrementAttempts(int $requestId): bool
    {
        $sql = "UPDATE {$this->table} SET attempts = attempts + 1 WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$requestId]);
    }
    
    /**
     * Mark OTP as verified
     */
    public function markVerified(int $requestId): bool
    {
        return $this->update($requestId, [
            'verified_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Invalidate all OTPs for an email address
     */
    public function invalidateAllForEmail(string $email): bool
    {
        $sql = "UPDATE {$this->table} 
                SET verified_at = NOW() 
                WHERE email = ? AND verified_at IS NULL";
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$email]);
    }
    
    /**
     * Get OTP statistics for an email address
     */
    public function getEmailStats(string $email, int $hours = 1): array
    {
        $sql = "SELECT 
            COUNT(*) as total_requests,
            SUM(CASE WHEN verified_at IS NOT NULL THEN 1 ELSE 0 END) as successful_verifications,
            SUM(CASE WHEN expires_at < NOW() AND verified_at IS NULL THEN 1 ELSE 0 END) as expired_requests,
            SUM(CASE WHEN attempts >= max_attempts THEN 1 ELSE 0 END) as max_attempt_reached
        FROM {$this->table} 
        WHERE email = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$email, $hours]);
        
        return $stmt->fetch();
    }
    
    /**
     * Clean up expired OTP requests
     */
    public function cleanup(int $hours = 24): int
    {
        $sql = "DELETE FROM {$this->table} WHERE created_at < DATE_SUB(NOW(), INTERVAL ? HOUR)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$hours]);
        
        return $stmt->rowCount();
    }
    
    /**
     * Check if phone has pending OTP
     */
    public function hasPendingOTP(string $phone): bool
    {
        return $this->getLatestValidRequest($phone) !== null;
    }
    
    /**
     * Get remaining time for active OTP
     */
    public function getRemainingTime(string $phone): int
    {
        $request = $this->getLatestValidRequest($phone);
        
        if (!$request) {
            return 0;
        }
        
        return max(0, strtotime($request['expires_at']) - time());
    }
}
