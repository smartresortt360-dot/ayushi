# Frontend-only EmailJS setup

The portfolio and reply page send directly from the browser using the EmailJS SDK. No PHP, serverless functions, Composer, Node backend, SMTP settings, or Vercel environment variables are used.

## EmailJS dashboard

The existing EmailJS service and contact template IDs are configured in `index.html`. The same service and public key are configured in `reply.html`. The public key is designed to be used in browser code.

1. In the existing contact template (`template_jil5fue`), set **To Email** to `{{to_email}}`, **From Name** to `{{from_name}}`, **Reply To** to `{{reply_to}}`, and the subject to `New portfolio message from {{from_name}}`.
2. Use this contact template HTML:

   ```html
   <div style="margin:0;padding:32px 12px;background:#FFF9F7;font-family:Arial,Helvetica,sans-serif;color:#211D23">
     <div style="max-width:600px;margin:0 auto;padding:32px;background:#FFFFFF;border:1px solid #f3e5e8;border-radius:16px">
       <p style="margin:0;color:#77717A;font-size:12px;letter-spacing:2px">CONTENT • SOCIAL MEDIA • DIGITAL</p>
       <h1 style="margin:12px 0 24px;font-family:Georgia,serif;font-size:28px">A new note for AYUSHI</h1>
       <p><strong>From:</strong> {{from_name}} &lt;{{from_email}}&gt;</p>
       <div style="margin:20px 0;padding:18px;background:#FFF4EC;border-radius:10px;line-height:1.7;white-space:pre-wrap">{{message}}</div>
       <a href="{{reply_url}}" style="display:inline-block;padding:13px 24px;background:#E8A6BE;border-radius:8px;color:#211D23;text-decoration:none;font-weight:bold">REPLY</a>
     </div>
   </div>
   ```

3. Create a second template for replies. Set **To Email** to `{{to_email}}`, **From Name** to `{{owner_name}}`, **Reply To** to `{{reply_to}}`, and subject to `A reply from {{owner_name}}`. The **From Email** must be the address authorized by your EmailJS email service. Use this HTML:

   ```html
   <div style="margin:0;padding:32px 12px;background:#FFF9F7;font-family:Arial,Helvetica,sans-serif;color:#211D23">
     <div style="max-width:600px;margin:0 auto;padding:32px;background:#FFFFFF;border:1px solid #f3e5e8;border-radius:16px">
       <p style="margin:0;color:#77717A;font-size:12px;letter-spacing:2px">AYUSHI ✦ &nbsp; CONTENT • SOCIAL MEDIA • DIGITAL</p>
       <h1 style="margin:14px 0 8px;font-family:Georgia,serif;font-size:28px;font-weight:normal">A note for you, {{client_name}}</h1>
       <div style="font-size:16px;line-height:1.8;white-space:pre-wrap">{{reply}}</div>
       <div style="margin:28px 0 0;padding:18px;background:#FFF4EC;border-left:3px solid #E8A6BE;border-radius:4px;color:#77717A;line-height:1.7;white-space:pre-wrap">
         <p style="margin:0 0 8px;font-size:12px;letter-spacing:1px">YOUR ORIGINAL MESSAGE</p>{{original_message}}
       </div>
       <p style="margin:24px 0 0;color:#77717A;font-size:13px">Reply to this email to reach {{owner_name}}.</p>
     </div>
   </div>
   ```

4. Copy the new reply template ID into `reply.html`, replacing `template_replace_with_reply_template_id` in `EMAILJS_CONFIG`.
5. Add your exact Vercel production domain to the EmailJS allowed origins. Keep the connected service's From address set to your authorized owner email.

EmailJS escapes normal `{{variables}}` in HTML templates. Keep these double braces; do not use triple braces for user-provided values.

## Deploy and test

1. Save the reply template ID change, then redeploy the static site on Vercel. No environment variables or backend build settings are needed.
2. Submit a contact message. Confirm the notification arrives and **REPLY** opens the portfolio's `reply.html` with client details and the original message filled in.
3. Send a reply and confirm it reaches the client, shows the original message, and has Reply-To set to your owner email.
4. Test blank fields, an invalid email, a filled honeypot, and HTML-like message text.

## Important security limitation

With no backend, client name, email, and message are placed in the reply URL so the reply page can prefill them. URL parameters and read-only fields can be edited, and the EmailJS public key/template IDs are visible to visitors. This version cannot cryptographically bind the recipient to the original inquiry or enforce server-side validation/rate limits. Use EmailJS's allowed-origin and anti-abuse controls, and do not put confidential information in the contact form. A server endpoint is required for a tamper-resistant production reply flow.