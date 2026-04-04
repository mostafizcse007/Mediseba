# MediSeba InfinityFree Deployment

## 1. Prepare environment
- Copy [`.env.infinityfree.example`](/C:/xampp/htdocs/.env.infinityfree.example) to `.env`
- Replace the placeholders with your actual InfinityFree database and domain values

## 2. Database
- Create a MySQL database in InfinityFree
- Import [mediseba_local.sql](/C:/xampp/htdocs/database/mediseba_local.sql)

## 3. Files to upload
- Upload the full project contents from [htdocs](/C:/xampp/htdocs)
- Keep the `backend`, `css`, `js`, `images`, `uploads`, and HTML files together in the public web root

## 4. OTP delivery
- The project now sends OTP emails from the backend using EmailJS REST API
- You must configure:
  - `EMAILJS_PUBLIC_KEY`
  - `EMAILJS_PRIVATE_KEY` (optional, but recommended if enabled in EmailJS)
  - `EMAILJS_SERVICE_ID`
  - `EMAILJS_TEMPLATE_ID`
- In your EmailJS dashboard, enable API access from non-browser environments:
  - `Account -> Security -> API access from non-browser environments`

## 5. Payment mode
- For demo/semester deployment, keep `PAYMENT_GATEWAY=demo`
- For real live payments, change `PAYMENT_GATEWAY=live` only after you add a real payment callback integration

## 6. CORS
- Set `CORS_ALLOWED_ORIGINS` to your real domain(s)
- Example:
  - `https://your-domain.example,https://www.your-domain.example`

## 7. Recommended final checks
- Confirm the site opens from your real domain
- Request patient OTP
- Request doctor OTP
- Upload a profile photo
- Book an appointment
- Open consultation chat
- Generate a prescription
- Download receipt and prescription PDF
