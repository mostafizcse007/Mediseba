# MediSeba

MediSeba is a role-based healthcare web application built with plain PHP, MySQL, HTML, CSS, and JavaScript. It runs directly on Apache/XAMPP without a build step and supports three roles:

- `patient`
- `doctor`
- `admin`

The project covers the full consultation flow: email OTP authentication, profile completion, doctor discovery, appointment booking, appointment chat, prescription management, payment tracking, and doctor verification by an admin.

## What The Project Includes

### Patient flow
- Sign up or log in with email OTP
- Complete a personal profile after first login
- Browse verified doctors by specialty
- Check doctor availability and book appointments
- View upcoming and past appointments
- Chat with the assigned doctor inside an appointment
- View prescriptions and download PDF copies
- View payments and download receipt PDFs
- Update profile details and profile photo

### Doctor flow
- Log in with email OTP
- Submit a doctor profile for verification
- Wait for admin approval before becoming publicly bookable
- Manage weekly schedule and available days
- View appointment queues and appointment history
- Chat with patients inside appointment threads
- Create, update, and delete prescriptions
- View payment and revenue summaries
- Update profile details and profile photo

### Admin flow
- Log in with email OTP
- Access the doctor verification queue
- Approve or reject submitted doctor profiles
- Keep the platform limited to a single admin account

### Shared system behavior
- JWT-based authentication
- Role-based API protection
- Email OTP delivery through EmailJS
- Responsive UI with light and dark theme support
- Real database-driven homepage statistics
- Profile photo uploads with validation
- Generated PDF receipts and prescriptions

## Tech Stack

- `PHP 8+`
- `MySQL`
- `PDO`
- `HTML5`
- `CSS3`
- `JavaScript (ES6+)`
- `Font Awesome`
- `EmailJS`

## How The App Is Structured

MediSeba is a classic no-framework web app:

1. Root-level `.html` files render the UI for each page.
2. Shared logic in `js/` calls the backend with `fetch`.
3. All API requests go through `backend/index.php`.
4. The router sends requests to controllers.
5. Middleware checks JWT tokens and user roles.
6. Controllers validate input and call models.
7. Models query MySQL using PDO prepared statements.
8. Utilities handle security, validation, rate limiting, JSON responses, and PDF generation.

## Project Structure

```text
htdocs/
├── backend/
│   ├── config/
│   │   ├── database.php
│   │   └── environment.php
│   ├── controllers/
│   │   ├── AdminController.php
│   │   ├── AppointmentController.php
│   │   ├── AuthController.php
│   │   ├── ChatController.php
│   │   ├── ConfigController.php
│   │   ├── DoctorController.php
│   │   ├── PaymentController.php
│   │   ├── PrescriptionController.php
│   │   └── UploadController.php
│   ├── middleware/
│   │   └── AuthMiddleware.php
│   ├── models/
│   │   ├── Appointment.php
│   │   ├── AppointmentChatMessage.php
│   │   ├── DoctorProfile.php
│   │   ├── DoctorReview.php
│   │   ├── Model.php
│   │   ├── OTPRequest.php
│   │   ├── PatientProfile.php
│   │   ├── Payment.php
│   │   ├── Prescription.php
│   │   └── User.php
│   ├── utils/
│   │   ├── RateLimiter.php
│   │   ├── Response.php
│   │   ├── Security.php
│   │   ├── SimplePdfDocument.php
│   │   └── Validator.php
│   └── index.php
├── css/
│   ├── auth.css
│   ├── chat.css
│   ├── responsive.css
│   └── style.css
├── js/
│   ├── api.js
│   ├── app.js
│   ├── auth.js
│   ├── chat.js
│   └── email-otp.js
├── images/
├── uploads/
├── database/
│   └── mediseba_local.sql
├── *.html
├── .env
├── .env.awardspace.example
├── .env.infinityfree.example
├── DEPLOY_AWARDSPACE.md
├── DEPLOY_INFINITYFREE.md
└── .htaccess
```

## Important Files By Responsibility

### Frontend
- `index.html`: public homepage with live stats, specialties, featured doctors, and testimonials
- `doctors.html`: searchable doctor listing page
- `doctor-profile.html`: public doctor details and booking entry point
- `login.html`, `doctor-login.html`, `admin-login.html`: role-specific OTP login pages
- `dashboard.html`, `doctor-dashboard.html`, `admin-dashboard.html`: role dashboards
- `appointment.html`, `appointments.html`, `doctor-appointments.html`: appointment workflow screens
- `prescriptions.html`, `prescription.html`, `doctor-prescriptions.html`: prescription listing and detail screens
- `payments.html`: patient payment history and receipt downloads
- `profile.html`, `doctor-my-profile.html`: editable profile pages
- `chat.html`: appointment-specific consultation chat

### Frontend JavaScript
- `js/api.js`: central API client and endpoint wrappers
- `js/auth.js`: token storage, route protection, and auth helpers
- `js/app.js`: shared UI behavior, dashboard loading, doctor listing, theme, and homepage data loading
- `js/chat.js`: polling-based appointment chat client
- `js/email-otp.js`: EmailJS-based OTP delivery support for client-side OTP mode

### Backend
- `backend/index.php`: API entry point, autoloader, headers, route map, and controller dispatch
- `backend/config/environment.php`: `.env` loading and runtime configuration
- `backend/config/database.php`: PDO connection management
- `backend/middleware/AuthMiddleware.php`: JWT auth and role enforcement
- `backend/controllers/*`: request handlers for each feature area
- `backend/models/*`: database access layer
- `backend/utils/Response.php`: consistent JSON and file download responses
- `backend/utils/Security.php`: JWT generation, session security, OTP helpers, and secure identifiers
- `backend/utils/RateLimiter.php`: OTP and login throttling
- `backend/utils/SimplePdfDocument.php`: lightweight PDF output used for receipts and prescriptions

### Data And Assets
- `database/mediseba_local.sql`: complete schema and seed data dump for local setup
- `images/`: static branding and illustration assets
- `uploads/`: runtime user-uploaded profile photos
- `.htaccess`: blocks sensitive file access and disables directory listing

## API Overview

All backend requests go through:

```text
/backend/index.php
```

Main route groups:

- `api/auth/*`
- `api/config/*`
- `api/uploads/*`
- `api/doctors/*`
- `api/appointments/*`
- `api/chats/*`
- `api/prescriptions/*`
- `api/payments/*`
- `api/admin/*`

Authentication is JWT-based. The frontend stores the token in `localStorage` and sends it with the `Authorization` header.

## Database Overview

The provided SQL file creates the main tables used by the app, including:

- `users`
- `patient_profiles`
- `doctor_profiles`
- `otp_requests`
- `rate_limits`
- `doctor_schedules`
- `doctor_timeoffs`
- `appointments`
- `appointment_status_history`
- `doctor_reviews`
- `payments`
- `prescriptions`
- `appointment_chat_messages`
- `activity_logs`
- `notifications`
- `system_settings`

The included dump already contains the appointment chat table, so importing `database/mediseba_local.sql` is the main database setup step.

## Local Setup

### 1. Place the project in XAMPP

You can run it directly from:

```text
C:\xampp\htdocs
```

or from a subfolder such as:

```text
C:\xampp\htdocs\mediseba
```

The frontend API client auto-detects the base path, so both root and subfolder setups can work.

### 2. Create the database

Create a MySQL database, for example:

```text
mediseba_local
```

### 3. Import the database dump

Import:

```text
database/mediseba_local.sql
```

### 4. Configure `.env`

Create or update `.env` with your local values.

Example local configuration:

```env
APP_NAME=MediSeba
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost
APP_TIMEZONE=Asia/Dhaka

DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=mediseba_local
DB_USERNAME=root
DB_PASSWORD=
DB_CHARSET=utf8mb4

JWT_SECRET=replace_with_a_long_random_secret_at_least_32_characters
JWT_EXPIRY=86400
SESSION_LIFETIME=86400
CSRF_TOKEN_LIFETIME=3600

OTP_EXPIRY_MINUTES=5
OTP_MAX_ATTEMPTS=3
OTP_RATE_LIMIT_PER_HOUR=5
OTP_DELIVERY_MODE=server_emailjs

EMAILJS_PUBLIC_KEY=your_emailjs_public_key
EMAILJS_PRIVATE_KEY=your_emailjs_private_key_optional
EMAILJS_SERVICE_ID=your_emailjs_service_id
EMAILJS_TEMPLATE_ID=your_emailjs_template_id

RATE_LIMIT_ENABLED=true
RATE_LIMIT_OTP=5
RATE_LIMIT_LOGIN=5
RATE_LIMIT_API=100

MAX_UPLOAD_SIZE=5242880
ALLOWED_IMAGE_TYPES=jpg,jpeg,png

CORS_ALLOWED_ORIGINS=http://localhost
CORS_ALLOWED_METHODS=GET,POST,PUT,PATCH,DELETE,OPTIONS
CORS_ALLOWED_HEADERS=Content-Type,Authorization,X-Authorization,X-Auth-Token,X-Requested-With,X-CSRF-Token

PAYMENT_GATEWAY=demo
PAYMENT_GATEWAY_SECRET=replace_with_gateway_secret_for_live_callbacks
```

### 5. Start XAMPP services

Start:

- `Apache`
- `MySQL`

### 6. Open the app

If the project is directly inside `htdocs`:

```text
http://localhost/
```

If the project is inside a subfolder:

```text
http://localhost/your-folder-name/
```

Useful pages:

- Home: `/index.html`
- Patient login: `/login.html`
- Doctor login: `/doctor-login.html`
- Admin login: `/admin-login.html`

## Required Environment Variables

### Core application
- `APP_NAME`: application name shown in UI and emails
- `APP_ENV`: usually `local` or `production`
- `APP_DEBUG`: enables detailed backend error output when `true`
- `APP_URL`: public base URL
- `APP_TIMEZONE`: PHP and MySQL timezone alignment

### Database
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`
- `DB_CHARSET`

### Security
- `JWT_SECRET`: must be strong and at least 32 characters
- `JWT_EXPIRY`
- `SESSION_LIFETIME`
- `CSRF_TOKEN_LIFETIME`

### OTP and EmailJS
- `OTP_EXPIRY_MINUTES`
- `OTP_MAX_ATTEMPTS`
- `OTP_RATE_LIMIT_PER_HOUR`
- `OTP_DELIVERY_MODE`
- `EMAILJS_PUBLIC_KEY`
- `EMAILJS_PRIVATE_KEY`
- `EMAILJS_SERVICE_ID`
- `EMAILJS_TEMPLATE_ID`

### Uploads and rate limiting
- `MAX_UPLOAD_SIZE`
- `ALLOWED_IMAGE_TYPES`
- `RATE_LIMIT_ENABLED`
- `RATE_LIMIT_OTP`
- `RATE_LIMIT_LOGIN`
- `RATE_LIMIT_API`

### CORS and payments
- `CORS_ALLOWED_ORIGINS`
- `CORS_ALLOWED_METHODS`
- `CORS_ALLOWED_HEADERS`
- `PAYMENT_GATEWAY`
- `PAYMENT_GATEWAY_SECRET`

## OTP Delivery Modes

MediSeba uses email-based OTP authentication.

### `server_emailjs`
- The backend generates the OTP and sends the email through the EmailJS REST API
- Recommended for local setup and hosts that allow outgoing PHP requests
- Used in the InfinityFree example environment

### `client_emailjs`
- The backend generates the OTP and returns it to the browser
- The browser sends the email through EmailJS
- Less secure than server-side delivery
- Intended for restricted free hosts such as AwardSpace free hosting

## Admin Account Setup

The system expects a single admin user in the `users` table.

If the imported database does not already contain an admin, create one manually:

```sql
INSERT INTO users (email, password_hash, role, status, email_verified_at, created_at, updated_at)
VALUES ('admin@mediseba.com', NULL, 'admin', 'active', NOW(), NOW(), NOW());
```

Admin authentication is also OTP-based, so the account must exist before admin login can work.

## Payment Behavior

The current payment flow is project/demo friendly:

- `cash` payments remain manual and are paid at the clinic
- In `demo`, `sandbox`, or `local` payment mode, `card`, `mobile_banking`, and `online` payments are completed directly inside MediSeba without an external gateway
- Receipt PDFs are generated inside the application
- A live gateway callback structure exists, but real gateway integration still requires production callback implementation and secret verification

Because of that, this project is ready for demos and academic presentation, but it is not yet a full production payment gateway integration.

## Security Notes

Current security-related features in the codebase include:

- PDO prepared statements
- JWT authentication with role checks
- OTP hashing and verification
- OTP and login rate limiting
- Upload size, extension, MIME type, and image validation
- CORS and common security headers set in `backend/index.php`
- Sensitive file blocking through `.htaccess`

For deployment, do not expose real secrets from:

- `.env`
- database credentials
- `JWT_SECRET`
- EmailJS keys
- payment callback secrets

## Deployment Notes

### AwardSpace
- Use `.env.awardspace.example`
- Use `OTP_DELIVERY_MODE=client_emailjs`
- Free hosting usually requires `http://`
- See `DEPLOY_AWARDSPACE.md`

### InfinityFree
- Use `.env.infinityfree.example`
- Current project docs use `OTP_DELIVERY_MODE=server_emailjs`
- Configure EmailJS API access for server-side requests
- See `DEPLOY_INFINITYFREE.md`

## Runtime Notes

- The app has no Composer or npm requirement in the current repository
- Frontend pages are static `.html` files enhanced by shared JavaScript
- Chat is appointment-specific and polling-based
- Profile photos are stored in `uploads/profile-photos/...`
- Some files such as `doctor-prescriptions.zip` and `__index_full_check.png` are support artifacts and are not required for the main application runtime

## License

This repository appears to be maintained as an academic or portfolio project unless a separate license is added later.
