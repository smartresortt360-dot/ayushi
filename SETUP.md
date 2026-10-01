# Contact and reply system setup

## Stack

This implementation uses PHP 8.1+, PHPMailer over authenticated SMTP, and a static HTML/CSS/JavaScript frontend. Deploy it to PHP-capable hosting such as cPanel/shared hosting. A static-only host will not run the PHP endpoints.

## Install

1. Point your domain's document root at this project and confirm that PHP 8.1+ and Composer are available. Enable PHP OpenSSL and allow PHP to make outbound SMTP connections.
2. Install the mail dependencies from the project root:

   ```sh
   composer install --no-dev --optimize-autoloader
   ```

3. Create a private data directory writable by the PHP process, preferably outside `public_html`/the document root. For example, use `/home/ACCOUNT/ayushi-mail-data` on cPanel. Set its permissions so the PHP user can read and write it.
4. Copy `.env.example` to `.env` on the server. Set `APP_BASE_URL` to the portfolio's public base URL without a trailing slash (include its subdirectory if applicable), and set `APP_STORAGE_DIR` to the private directory from step 3. Use a unique random `APP_KEY` with at least 32 characters. Do not publish or commit `.env`.
5. Set `OWNER_NAME` and `OWNER_EMAIL`. Configure `SMTP_HOST`, `SMTP_PORT`, `SMTP_SECURE`, `SMTP_USERNAME`, and `SMTP_PASSWORD` with your provider's SMTP settings. The provider must authorize `OWNER_EMAIL` as a verified sender, because that is the From address clients see. For Gmail, use `smtp.gmail.com`, port `587`, `tls`, your Gmail address, and a Google App Password; never use your normal account password.
6. Upload `index.html`, `reply.html`, `api/`, `.htaccess`, `storage/.htaccess`, `composer.json`, and the Composer-generated `vendor/` directory. Keep `.env` and the storage data private. If your host cannot set environment variables or keep files outside the web root, use the included Apache access rules and ask the host to confirm equivalent protection.
7. Visit the portfolio over HTTPS. The contact and reply pages call relative `/api` paths; if the site lives in a subdirectory, configure `APP_BASE_URL` to include that subdirectory and keep the `api/` directory alongside `reply.html`.

The contact notification contains a **REPLY** link to `reply.html`. Each inquiry gets an unguessable random token; the client email, name, and original message are stored server-side, not encoded in the URL. The token expires after 90 days and acts as a bearer link, so do not forward the notification or reply URL. SMTP credentials never reach the browser. Both outbound emails have HTML and plain-text alternatives; replies set From to the verified owner address and Reply-To to that same address.

The included rate limit allows six contact submissions and six replies per IP in a 15-minute window. The contact form also has a honeypot field. The limit store is file-based and suitable for a single PHP host; use a shared store such as Redis or a database if you deploy multiple PHP servers.

## Test checklist

- [ ] Submit the contact form with a valid name, email, and message; verify a confirmation appears and the owner receives the email.
- [ ] Verify the owner email displays the original message and its **REPLY** button opens the HTTPS website, not a `mailto:` link.
- [ ] Open the button link; verify the client's name, email, and original message are shown and cannot be edited.
- [ ] Send a reply; verify the client receives it with the configured owner From name/address, the original message quoted, and a working normal email Reply action back to the owner.
- [ ] Try blank values, malformed email, and messages beyond the configured limit; verify the server rejects invalid input.
- [ ] Submit with a filled honeypot, a non-POST request to either send endpoint, and a request with a different Origin; verify no email is sent.
- [ ] Send HTML-like text such as `<script>alert(1)</script>` in the original message and reply; verify it displays as text in the received emails.
- [ ] Reuse a reply token after 90 days (or temporarily set an expired record); verify the API rejects it. Confirm a burst over six sends from one IP receives HTTP 429.
- [ ] Confirm `.env` cannot be fetched over HTTP, and verify both contact and reply emails arrive in the inbox rather than spam.

## Troubleshooting

Check the PHP/server error log for mail and storage failures. Confirm SMTP credentials, sender verification, outbound SMTP access, the private storage directory permissions, and that Composer dependencies were installed on the server. Do not expose SMTP credentials or detailed server errors in browser responses.