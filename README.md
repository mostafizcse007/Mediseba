# MediSeba

MediSeba is a role-based healthcare web application built with PHP, MySQL, HTML, CSS, and JavaScript. It supports three user roles:

- `Patient`
- `Doctor`
- `Admin`

The platform is designed for appointment booking, digital prescriptions, payment tracking, profile management, and doctor-patient consultation chat.

## Features

### Patient Features
- OTP-based login and sign up
- Browse doctors by specialty
- Book appointments
- View upcoming and past appointments
- Consultation chat with the assigned doctor
- View prescriptions and download prescription PDFs
- View payments and download receipt PDFs
- Manage personal profile and profile photo

### Doctor Features
- OTP-based login and registration
- Dashboard with queue and appointment summary
- Manage appointments
- Consultation chat with patients
- Create and manage prescriptions
- Manage weekly schedule
- View patient details inside workflow pages
- Manage professional profile and profile photo

### Admin Features
- OTP-based login for the single system admin
- Review pending doctor profiles
- Approve or reject doctor verification requests

### Shared Features
- Light mode and dark mode
- Responsive layout
- OTP-based authentication
- Profile photo upload support
- Real database-driven homepage stats
- Role-specific dashboards

## Tech Stack

- `PHP`
- `MySQL`
- `PDO`
- `HTML5`
- `CSS3`
- `JavaScript (ES6+)`
- `Font Awesome`
- `EmailJS` for OTP email delivery

## Project Structure

```text
htdocs/
├── backend/
│   ├── config/
│   ├── controllers/
│   ├── middleware/
│   ├── models/
│   ├── utils/
│   └── index.php
├── css/
├── images/
├── js/
├── uploads/
├── database/
│   └── mediseba_local.sql
├── *.html
├── .env
├── .env.awardspace.example
├── .env.infinityfree.example
├── DEPLOY_AWARDSPACE.md
└── DEPLOY_INFINITYFREE.md
```

## Local Setup

### 1. Put the project in XAMPP
Place the project inside:

```text
C:\xampp\htdocs
```

### 2. Create the database
Create a local MySQL database named:

```text
mediseba_local
```

### 3. Import the SQL file
Import:

[`database/mediseba_local.sql`](database/mediseba_local.sql)

### 4. Configure the environment
Update [`.env`](.env) for your local setup.

Typical local XAMPP values:

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
```

You will also need working EmailJS keys for OTP email delivery.

### 5. Start XAMPP
Start:

- `Apache`
- `MySQL`

### 6. Open the app

```text
http://localhost/
```

Useful local URLs:

- Home: `http://localhost/`
- Patient Login: `http://localhost/login.html`
- Doctor Login: `http://localhost/doctor-login.html`
- Admin Login: `http://localhost/admin-login.html`
- phpMyAdmin: `http://localhost/phpmyadmin/`

### 7. Create the single admin account

MediSeba allows only one admin user in the `users` table.

If your imported database does not already contain an admin row, create it once in phpMyAdmin:

```sql
INSERT INTO users (email, password_hash, role, status, email_verified_at, created_at, updated_at)
VALUES ('admin@mediseba.com', NULL, 'admin', 'active', NOW(), NOW(), NOW());
```

If you already have an existing database and want the database itself to block a second admin row, run:

- [`database/single_admin_migration.sql`](database/single_admin_migration.sql)

Then log in through:

```text
http://localhost/admin-login.html
```

## OTP Delivery

MediSeba supports two OTP delivery modes:

### 1. `server_emailjs`
The backend sends OTP emails using EmailJS from PHP.

Best for:
- localhost
- standard PHP hosts that allow outgoing requests

### 2. `client_emailjs`
The backend generates the OTP and the browser sends the email using EmailJS.

Best for:
- restricted free hosts where PHP outbound requests are blocked

Important:
- `client_emailjs` is weaker than `server_emailjs`
- it is acceptable for demo/semester deployments, not ideal for real production security

## Deployment Notes

### AwardSpace
Use:

[`DEPLOY_AWARDSPACE.md`](DEPLOY_AWARDSPACE.md)

Use:

[`.env.awardspace.example`](.env.awardspace.example)

Recommended OTP mode on AwardSpace free hosting:

```env
OTP_DELIVERY_MODE=client_emailjs
```

### InfinityFree
Use:

[`DEPLOY_INFINITYFREE.md`](DEPLOY_INFINITYFREE.md)

Use:

[`.env.infinityfree.example`](.env.infinityfree.example)

Important:
- InfinityFree blocks URLs and features related to `chat`
- if consultation chat is required, InfinityFree is not recommended for this project

## Main Pages

### Public Pages
- [index.html](index.html)
- [doctors.html](doctors.html)
- [about.html](about.html)
- [how-it-works.html](how-it-works.html)

### Patient Pages
- [login.html](login.html)
- [dashboard.html](dashboard.html)
- [appointments.html](appointments.html)
- [prescriptions.html](prescriptions.html)
- [payments.html](payments.html)
- [profile.html](profile.html)

### Doctor Pages
- [doctor-login.html](doctor-login.html)
- [doctor-dashboard.html](doctor-dashboard.html)
- [doctor-appointments.html](doctor-appointments.html)
- [doctor-prescriptions.html](doctor-prescriptions.html)
- [doctor-schedule.html](doctor-schedule.html)
- [doctor-my-profile.html](doctor-my-profile.html)

### Admin Pages
- [admin-login.html](admin-login.html)
- [admin-dashboard.html](admin-dashboard.html)

### Shared Workflow Page
- [chat.html](chat.html)

## Database

The database dump included in this project is:

- [mediseba_local.sql](database/mediseba_local.sql)

Core tables include:

- `users`
- `patient_profiles`
- `doctor_profiles`
- `appointments`
- `appointment_status_history`
- `prescriptions`
- `payments`
- `appointment_chat_messages`

## Notes

- Payment flow is configured for demo/project use unless connected to a real payment gateway
- OTP requires valid EmailJS configuration
- Chat works locally and on hosts that do not block chat-like routes/features
- Profile photos are stored in the `uploads` directory

## GitHub Upload Note

Before pushing to GitHub, do **not** publish your real production secrets.

Do not commit real values from:

- [`.env`](.env)
- database passwords
- JWT secrets
- EmailJS keys you do not want publicly exposed

Use the example env files for documentation:

- [`.env.awardspace.example`](.env.awardspace.example)
- [`.env.infinityfree.example`](.env.infinityfree.example)

## License

This project is currently maintained as an academic / portfolio project unless you define a separate license for public reuse.
