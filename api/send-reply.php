<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_method('POST');
require_same_origin();

try {
    $data = request_data();
    if (!rate_limit('reply')) {
        json_response(429, ['error' => 'Too many replies. Please try again later.']);
    }
    $token = clean_text($data['token'] ?? null, 64, false);
    $reply = clean_text($data['reply'] ?? null, 10000);
    $inquiry = find_inquiry($token);
    if ($inquiry === null) {
        json_response(404, ['error' => 'This reply link is invalid or has expired.']);
    }
    if ($reply === '') {
        json_response(422, ['error' => 'Write a reply before sending.']);
    }

    $name = (string) $inquiry['name'];
    $ownerName = setting('OWNER_NAME', 'Ayushi Malviya');
    $safeName = escape_html($name);
    $safeReply = nl2br(escape_html($reply), false);
    $safeOriginal = nl2br(escape_html((string) $inquiry['message']), false);
    $html = '<div style="margin:0;padding:32px 12px;background:#FFF9F7;font-family:Arial,Helvetica,sans-serif;color:#211D23">'
        . '<div style="max-width:600px;margin:0 auto;padding:32px;background:#FFFFFF;border:1px solid #f3e5e8;border-radius:16px">'
        . '<p style="margin:0;color:#77717A;font-size:12px;letter-spacing:2px">AYUSHI ✦ &nbsp; CONTENT • SOCIAL MEDIA • DIGITAL</p>'
        . '<h1 style="margin:14px 0 8px;font-family:Georgia,serif;font-size:28px;font-weight:normal">A note for you, ' . $safeName . '</h1>'
        . '<div style="font-size:16px;line-height:1.8">' . $safeReply . '</div>'
        . '<div style="margin:28px 0 0;padding:18px;background:#FFF4EC;border-left:3px solid #E8A6BE;border-radius:4px;color:#77717A;line-height:1.7">'
        . '<p style="margin:0 0 8px;font-size:12px;letter-spacing:1px">YOUR ORIGINAL MESSAGE</p>' . $safeOriginal . '</div>'
        . '<p style="margin:24px 0 0;color:#77717A;font-size:13px">You can reply directly to this email to reach ' . escape_html($ownerName) . '.</p>'
        . '</div></div>';
    $text = "Hi {$name},\n\n{$reply}\n\nYour original message:\n{$inquiry['message']}\n\nYou can reply directly to this email to reach {$ownerName}.";

    send_email(
        (string) $inquiry['email'],
        $name,
        'A reply from ' . $ownerName,
        $html,
        $text,
        (string) setting('OWNER_EMAIL'),
        $ownerName
    );
    json_response(200, ['ok' => true]);
} catch (Throwable $error) {
    error_log('Reply send error: ' . $error->getMessage());
    json_response(500, ['error' => 'Your reply could not be sent right now. Please try again later.']);
}