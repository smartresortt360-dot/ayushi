<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_method('POST');
require_same_origin();

try {
    $data = request_data();
    if (clean_text($data['website'] ?? '', 200) !== '') {
        json_response(200, ['ok' => true]);
    }
    if (!rate_limit('contact')) {
        json_response(429, ['error' => 'Too many messages. Please try again later.']);
    }

    $name = clean_text($data['name'] ?? null, 200, false);
    $email = clean_text($data['email'] ?? null, 254, false);
    $message = clean_text($data['message'] ?? null, 8000);
    if ($name === '' || $email === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_response(422, ['error' => 'Enter your name, a valid email, and a message.']);
    }

    $token = create_reply_token([
        'name' => $name,
        'email' => $email,
        'message' => $message,
        'created_at' => time(),
    ]);
    $replyUrl = rtrim((string) setting('APP_BASE_URL'), '/') . '/reply.html?token=' . rawurlencode($token);
    $safeName = escape_html($name);
    $safeEmail = escape_html($email);
    $safeMessage = nl2br(escape_html($message), false);
    $ownerName = setting('OWNER_NAME', 'Ayushi Malviya');
    $subject = 'New portfolio message from ' . $name;
    $html = '<div style="margin:0;padding:32px 12px;background:#FFF9F7;font-family:Arial,Helvetica,sans-serif;color:#211D23">'
        . '<div style="max-width:600px;margin:0 auto;padding:32px;background:#FFFFFF;border:1px solid #f3e5e8;border-radius:16px">'
        . '<p style="margin:0;color:#77717A;font-size:12px;letter-spacing:2px">CONTENT • SOCIAL MEDIA • DIGITAL</p>'
        . '<h1 style="margin:12px 0 24px;font-family:Georgia,serif;font-size:28px">A new note for ' . escape_html($ownerName) . '</h1>'
        . '<p style="margin:0 0 8px"><strong>From:</strong> ' . $safeName . ' &lt;' . $safeEmail . '&gt;</p>'
        . '<div style="margin:20px 0;padding:18px;background:#FFF4EC;border-radius:10px;line-height:1.7">' . $safeMessage . '</div>'
        . '<a href="' . escape_html($replyUrl) . '" style="display:inline-block;padding:13px 24px;background:#E8A6BE;border-radius:8px;color:#211D23;text-decoration:none;font-weight:bold">REPLY</a>'
        . '<p style="margin:22px 0 0;color:#77717A;font-size:12px">This reply link expires in 90 days. You can also reply to this email normally.</p>'
        . '</div></div>';
    $text = "New portfolio message from {$name} <{$email}>\n\n{$message}\n\nReply on your website: {$replyUrl}";

    send_email(
        (string) setting('OWNER_EMAIL'),
        $ownerName,
        $subject,
        $html,
        $text,
        $email,
        $name
    );
    json_response(200, ['ok' => true]);
} catch (Throwable $error) {
    error_log('Contact form error: ' . $error->getMessage());
    json_response(500, ['error' => 'Your message could not be sent right now. Please try again later.']);
}