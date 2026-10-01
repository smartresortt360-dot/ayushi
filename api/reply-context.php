<?php
declare(strict_types=1);

require_once __DIR__ . '/common.php';
require_method('GET');

try {
    $inquiry = find_inquiry((string) ($_GET['token'] ?? ''));
    if ($inquiry === null) {
        json_response(404, ['error' => 'This reply link is invalid or has expired.']);
    }
    json_response(200, [
        'name' => $inquiry['name'],
        'email' => $inquiry['email'],
        'message' => $inquiry['message'],
    ]);
} catch (Throwable $error) {
    error_log('Reply context error: ' . $error->getMessage());
    json_response(500, ['error' => 'Reply details are temporarily unavailable.']);
}