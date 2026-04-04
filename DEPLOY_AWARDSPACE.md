# MediSeba AwardSpace Deployment

## Why AwardSpace needs a different OTP mode
- AwardSpace free hosting blocks outgoing PHP connections and disables `curl_init` on the free plan.
- Because of that, backend EmailJS delivery will not work there.
- For AwardSpace free hosting, use `OTP_DELIVERY_MODE=client_emailjs`.

Important:
- In `client_emailjs` mode, the server returns the generated OTP to the browser and the browser sends the email with EmailJS.
- This is acceptable for a semester/demo deployment, but it is weaker than server-side OTP delivery.

## 1. Prepare the environment
- Copy [.env.awardspace.example](/C:/xampp/htdocs/.env.awardspace.example) to `.env`
- Replace the placeholders with your AwardSpace domain, database name, username, and password
- Keep `OTP_DELIVERY_MODE=client_emailjs`
- On AwardSpace free hosting, use `http://` in `APP_URL` and `CORS_ALLOWED_ORIGINS` unless you move to a setup that actually provides HTTPS

## 2. Database
- Create a MySQL database in AwardSpace
- Import [mediseba_local.sql](/C:/xampp/htdocs/database/mediseba_local.sql)

## 3. Files to upload
- Upload the public project contents from [htdocs](/C:/xampp/htdocs)
- Keep `backend`, `css`, `images`, `js`, `uploads`, `.htaccess`, `.env`, and the HTML files together in the web root

## 4. EmailJS setup
- Configure these values in the server `.env`:
  - `EMAILJS_PUBLIC_KEY`
  - `EMAILJS_SERVICE_ID`
  - `EMAILJS_TEMPLATE_ID`
- The login pages will send OTP emails from the browser when AwardSpace mode is active
- Make sure your AwardSpace site domain is allowed in the EmailJS security domain list

## 5. CORS
- Set `CORS_ALLOWED_ORIGINS` to your real AwardSpace domain
- Example:
  - `http://your-subdomain.awardspace.info`
  - `https://your-custom-domain.example`

## 6. Payment mode
- Keep `PAYMENT_GATEWAY=demo` for this project unless you later add a real payment gateway callback

## 7. Recommended final checks
- Confirm the homepage opens
- Request patient OTP
- Request doctor OTP
- Complete patient login
- Complete doctor login
- Book an appointment
- Open consultation chat
- Create a prescription
- Download prescription and receipt PDFs

## 8. Hosting limitations to remember
- AwardSpace free hosting does not support SSL on the free plan, so your public site URL is usually `http://...`
- Free hosting may apply stricter limits to AJAX-heavy features like chat polling
- For a stronger production setup later, move to a host that supports:
  - server-side EmailJS or SMTP
  - HTTPS by default
  - unrestricted PHP outbound requests
