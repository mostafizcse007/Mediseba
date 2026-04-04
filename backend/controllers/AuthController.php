<?php
/**
 * MediSeba - Authentication Controller
 * 
 * Handles OTP-based phone authentication
 */

declare(strict_types=1);

namespace MediSeba\Controllers;

use MediSeba\Utils\Response;
use MediSeba\Utils\Validator;
use MediSeba\Utils\Security;
use MediSeba\Utils\RateLimiter;
use MediSeba\Config\Environment;
use MediSeba\Models\User;
use MediSeba\Models\OTPRequest;
use MediSeba\Models\PatientProfile;
use MediSeba\Models\DoctorProfile;

class AuthController
{
    private User $userModel;
    private OTPRequest $otpModel;
    
    public function __construct()
    {
        $this->userModel = new User();
        $this->otpModel = new OTPRequest();
    }
    
    /**
     * Request OTP for email authentication
     * POST /api/auth/request-otp
     */
    public function requestOTP(array $request): void
    {
        // Validate input
        $validator = Validator::quick($request, [
            'email' => 'required|email',
            'role' => 'in:patient,doctor'
        ]);
        
        if (!$validator['valid']) {
            Response::validationError($validator['errors']);
        }
        
        $email = filter_var($request['email'], FILTER_SANITIZE_EMAIL);
        $requestedRole = $this->resolveRequestedRole($request);
        $ipAddress = Security::getClientIp();
        $userAgent = Security::getUserAgent();
        
        // Check rate limit
        $rateLimitCheck = RateLimiter::checkOtpLimit($email);
        if (!$rateLimitCheck['allowed']) {
            Response::tooManyRequests($rateLimitCheck['retry_after']);
        }
        
        // Check if user exists, create if not
        $user = $this->userModel->findByEmail($email);
        $this->assertRoleMatchesExistingUser($user, $requestedRole);
        $userId = $user ? $user['id'] : null;
        
        // Invalidate any existing OTPs for this email
        $this->otpModel->invalidateAllForEmail($email);
        
        // Create new OTP request
        $otpData = $this->otpModel->createRequest($userId, $email, $ipAddress, $userAgent);
        
        // Record rate limit
        RateLimiter::recordOtpRequest($email);
        
        $responseData = [
            'message' => 'OTP sent successfully',
            'expires_in' => 300, // 5 minutes
            'remaining_attempts' => $rateLimitCheck['remaining'] - 1
        ];

        $deliveryMode = strtolower((string) Environment::get('OTP_DELIVERY_MODE', 'server_emailjs'));

        if ($deliveryMode === 'client_emailjs') {
            $responseData['otp'] = $otpData['otp'];
            Response::success('OTP sent successfully', $responseData);
        }

        $deliveryResult = $this->sendOtpViaEmailJs($email, (string) $otpData['otp'], $requestedRole);

        if (!$deliveryResult['success']) {
            $this->otpModel->invalidateAllForEmail($email);
            Response::serverError($deliveryResult['message']);
        }
        
        Response::success('OTP sent successfully', $responseData);
    }
    
    /**
     * Verify OTP and authenticate user
     * POST /api/auth/verify-otp
     */
    public function verifyOTP(array $request): void
    {
        // Validate input
        $validator = Validator::quick($request, [
            'email' => 'required|email',
            'otp' => 'required|length:6',
            'role' => 'in:patient,doctor'
        ]);
        
        if (!$validator['valid']) {
            Response::validationError($validator['errors']);
        }
        
        $email = filter_var($request['email'], FILTER_SANITIZE_EMAIL);
        $role = $this->resolveRequestedRole($request);
        
        // Check login rate limit
        $rateLimitCheck = RateLimiter::checkLoginLimit($email);
        if (!$rateLimitCheck['allowed']) {
            Response::tooManyRequests($rateLimitCheck['retry_after']);
        }

        $existingUser = $this->userModel->findByEmail($email);
        $this->assertRoleMatchesExistingUser($existingUser, $role);
        
        // Get latest valid OTP request
        $otpRequest = $this->otpModel->getLatestValidRequest($email);
        
        if (!$otpRequest) {
            Response::error('No active OTP found. Please request a new OTP.');
        }
        
        // Verify OTP
        $verification = $this->otpModel->verifyOTP($otpRequest['id'], $request['otp']);
        
        if (!$verification['success']) {
            RateLimiter::recordLoginAttempt($email);
            Response::error($verification['message'], [
                'remaining_attempts' => $verification['remaining_attempts'] ?? 0
            ]);
        }
        
        // Find or create user with requested role
        $user = $this->userModel->findOrCreateByEmail($email, $role);
        
        // Ensure email is marked verified
        if (!$user['email_verified_at']) {
            $this->userModel->verifyEmail($user['id']);
        }
        
        // Update last login
        $this->userModel->updateLastLogin($user['id']);
        
        // Generate JWT token
        $token = Security::generateJWT([
            'user_id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role']
        ]);
        
        // Get profile based on role
        $profile = null;
        if ($user['role'] === 'patient') {
            $profileModel = new PatientProfile();
            $profile = $profileModel->findByUserId($user['id']);
        } elseif ($user['role'] === 'doctor') {
            $profileModel = new DoctorProfile();
            $profile = $profileModel->findByUserId($user['id']);
        }
        
        // Clear login attempts on successful login
        RateLimiter::clearLoginAttempts($email);
        
        Response::success('Authentication successful', [
            'token' => $token,
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role'],
                'status' => $user['status']
            ],
            'profile' => $profile,
            'is_new_user' => $profile === null
        ]);
    }
    
    /**
     * Get current user profile
     * GET /api/auth/me
     */
    public function me(array $user): void
    {
        $fullUser = $this->userModel->find($user['user_id']);
        
        if (!$fullUser) {
            Response::notFound('User');
        }
        
        // Get profile based on role
        $profile = null;
        if ($fullUser['role'] === 'patient') {
            $profileModel = new PatientProfile();
            $profile = $profileModel->findByUserId($fullUser['id']);
        } elseif ($fullUser['role'] === 'doctor') {
            $profileModel = new DoctorProfile();
            $profile = $profileModel->findByUserId($fullUser['id']);
        }
        
        Response::success('User profile retrieved', [
            'user' => [
                'id' => $fullUser['id'],
                'email' => $fullUser['email'],
                'role' => $fullUser['role'],
                'status' => $fullUser['status'],
                'last_login_at' => $fullUser['last_login_at']
            ],
            'profile' => $profile
        ]);
    }
    
    /**
     * Logout user
     * POST /api/auth/logout
     */
    public function logout(array $user): void
    {
        // In a stateless JWT system, logout is handled client-side
        // by removing the token. Optionally, we can maintain a token blacklist.
        
        Response::success('Logged out successfully');
    }
    
    /**
     * Refresh JWT token
     * POST /api/auth/refresh
     */
    public function refresh(array $user): void
    {
        // Generate new token
        $token = Security::generateJWT([
            'user_id' => $user['user_id'],
            'phone' => $user['phone'],
            'role' => $user['role']
        ]);
        
        Response::success('Token refreshed', ['token' => $token]);
    }
    
    /**
     * Complete profile setup for new users
     * POST /api/auth/complete-profile
     */
    public function completeProfile(array $request, array $user): void
    {
        $role = $user['role'];
        
        if ($role === 'patient') {
            $this->completePatientProfile($request, $user);
        } elseif ($role === 'doctor') {
            $this->completeDoctorProfile($request, $user);
        } else {
            Response::error('Invalid user role');
        }
    }
    
    /**
     * Update existing patient profile
     * PUT /api/auth/profile
     */
    public function updateProfile(array $request, array $user): void
    {
        // Only patient profiles managed here (Doctors have their own controller)
        if ($user['role'] !== 'patient') {
            Response::forbidden('Only patients can update their profiles via this endpoint');
        }
        
        // Use the exact same validation and model insert logic securely mapped
        $this->completePatientProfile($request, $user);
    }
    
    /**
     * Complete patient profile
     */
    private function completePatientProfile(array $request, array $user): void
    {
        // Validate input
        $validator = Validator::quick($request, [
            'full_name' => 'required|min:3|max:100',
            'date_of_birth' => 'date|past',
            'gender' => 'in:male,female,other,prefer_not_to_say',
            'blood_group' => 'in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'address' => 'max:500'
        ]);
        
        if (!$validator['valid']) {
            Response::validationError($validator['errors']);
        }
        
        $profileModel = new PatientProfile();
        $result = $profileModel->createOrUpdate($user['user_id'], [
            'full_name' => $request['full_name'],
            'date_of_birth' => $request['date_of_birth'] ?? null,
            'gender' => $request['gender'] ?? null,
            'blood_group' => $request['blood_group'] ?? null,
            'address' => $request['address'] ?? null,
            'emergency_contact_name' => $request['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $request['emergency_contact_phone'] ?? null
        ]);
        
        if ($result['success']) {
            Response::success('Profile completed successfully', $result);
        } else {
            Response::error('Failed to complete profile');
        }
    }
    
    /**
     * Complete doctor profile
     */
    private function completeDoctorProfile(array $request, array $user): void
    {
        if (array_key_exists('registration_number', $request)) {
            $registrationNumber = trim((string) $request['registration_number']);
            $request['registration_number'] = $registrationNumber === '' ? null : $registrationNumber;

            if ($request['registration_number'] !== null && strlen($request['registration_number']) > 100) {
                Response::validationError([
                    'registration_number' => 'The registration_number must not exceed 100 characters.'
                ]);
            }
        }

        // Validate input
        $validator = Validator::quick($request, [
            'full_name' => 'required|min:3|max:100',
            'specialty' => 'required|max:100',
            'qualification' => 'required|max:500',
            'experience_years' => 'integer|min:0|max:70',
            'consultation_fee' => 'numeric|min:0',
            'clinic_name' => 'max:255',
            'clinic_address' => 'max:500'
        ]);
        
        if (!$validator['valid']) {
            Response::validationError($validator['errors']);
        }
        
        $profileModel = new DoctorProfile();
        
        // Generate slug
        $slug = $profileModel->generateSlug($request['full_name']);
        
        $result = $profileModel->create([
            'user_id' => $user['user_id'],
            'full_name' => $request['full_name'],
            'slug' => $slug,
            'specialty' => $request['specialty'],
            'qualification' => $request['qualification'],
            'experience_years' => $request['experience_years'] ?? 0,
            'consultation_fee' => $request['consultation_fee'] ?? 0,
            'clinic_name' => $request['clinic_name'] ?? null,
            'clinic_address' => $request['clinic_address'] ?? null,
            'bio' => $request['bio'] ?? null,
            'languages' => json_encode($request['languages'] ?? ['Bengali']),
            'registration_number' => $request['registration_number'] ?? null,
            'is_verified' => false
        ]);
        
        Response::success('Profile submitted for verification', [
            'profile_id' => $result,
            'message' => 'Your profile is pending admin verification'
        ]);
    }

    private function resolveRequestedRole(array $request): string
    {
        $role = $request['role'] ?? 'patient';

        return in_array($role, ['patient', 'doctor'], true) ? $role : 'patient';
    }

    private function assertRoleMatchesExistingUser(?array $user, string $requestedRole): void
    {
        if (!$user || $user['role'] === $requestedRole) {
            return;
        }

        $targetPage = $user['role'] === 'doctor' ? 'Doctor Login' : 'Patient Login';

        Response::conflict(
            sprintf(
                'This email is already registered as a %s account. Please use the %s page.',
                $user['role'],
                $targetPage
            )
        );
    }

    private function sendOtpViaEmailJs(string $email, string $otp, string $role): array
    {
        $publicKey = trim((string) Environment::get('EMAILJS_PUBLIC_KEY', ''));
        $privateKey = trim((string) Environment::get('EMAILJS_PRIVATE_KEY', ''));
        $serviceId = trim((string) Environment::get('EMAILJS_SERVICE_ID', ''));
        $templateId = trim((string) Environment::get('EMAILJS_TEMPLATE_ID', ''));

        if ($publicKey === '' || $serviceId === '' || $templateId === '') {
            return [
                'success' => false,
                'message' => 'OTP email service is not configured. Please add the EmailJS settings before deployment.'
            ];
        }

        $appName = trim((string) Environment::get('APP_NAME', 'MediSeba')) ?: 'MediSeba';
        $expiryMinutes = (int) Environment::get('OTP_EXPIRY_MINUTES', 5);

        $payload = [
            'service_id' => $serviceId,
            'template_id' => $templateId,
            'user_id' => $publicKey,
            'template_params' => [
                'to_email' => $email,
                'email' => $email,
                'user_email' => $email,
                'recipient_email' => $email,
                'otp_code' => $otp,
                'otp' => $otp,
                'passcode' => $otp,
                'app_name' => $appName,
                'company_name' => $appName,
                'brand_name' => $appName,
                'otp_expiry_minutes' => $expiryMinutes,
                'validity_minutes' => $expiryMinutes,
                'login_context' => $role . ' login'
            ]
        ];

        if ($privateKey !== '') {
            $payload['accessToken'] = $privateKey;
        }

        $response = $this->postJson('https://api.emailjs.com/api/v1.0/email/send', $payload);

        if ($response['success']) {
            return ['success' => true];
        }

        $responseBody = strtolower(trim((string) ($response['body'] ?? '')));

        if (($response['status'] ?? 0) === 403) {
            return [
                'success' => false,
                'message' => 'EmailJS blocked the OTP request from the server. Enable API access from non-browser environments in your EmailJS account security settings.'
            ];
        }

        if (str_contains($responseBody, 'public key is invalid')) {
            return [
                'success' => false,
                'message' => 'EmailJS rejected the configured public key. Update EMAILJS_PUBLIC_KEY in your .env using the current Public Key from the EmailJS dashboard.'
            ];
        }

        if (str_contains($responseBody, 'private key is invalid') || str_contains($responseBody, 'access token is invalid')) {
            return [
                'success' => false,
                'message' => 'EmailJS rejected the configured private key. Update EMAILJS_PRIVATE_KEY in your .env or clear it if you do not want to use a private key.'
            ];
        }

        if (str_contains($responseBody, 'origin is not allowed') || str_contains($responseBody, 'domain is not allowed')) {
            return [
                'success' => false,
                'message' => 'EmailJS blocked this request because the current domain is not allowed. Add your site URL in the EmailJS account security domain list.'
            ];
        }

        return [
            'success' => false,
            'message' => 'Failed to deliver the OTP email. Please verify your EmailJS service, template, public key, and optional private key settings.'
        ];
    }

    private function postJson(string $url, array $payload): array
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

        if ($body === false) {
            return ['success' => false];
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);

            if ($ch === false) {
                return ['success' => false];
            }

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_TIMEOUT => 20
            ]);

            $responseBody = curl_exec($ch);
            $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError !== '') {
                error_log('EmailJS OTP delivery failed: ' . $curlError);
                return ['success' => false, 'status' => 0, 'body' => ''];
            }

            return [
                'success' => $statusCode >= 200 && $statusCode < 300,
                'status' => $statusCode,
                'body' => is_string($responseBody) ? $responseBody : ''
            ];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $body,
                'timeout' => 20,
                'ignore_errors' => true
            ]
        ]);

        $result = @file_get_contents($url, false, $context);
        $statusLine = $http_response_header[0] ?? '';
        preg_match('/\s(\d{3})\s/', $statusLine, $matches);
        $statusCode = isset($matches[1]) ? (int) $matches[1] : 0;

        if ($result === false && $statusCode === 0) {
            return ['success' => false, 'status' => 0, 'body' => ''];
        }

        return [
            'success' => $statusCode >= 200 && $statusCode < 300,
            'status' => $statusCode,
            'body' => is_string($result) ? $result : ''
        ];
    }
}
