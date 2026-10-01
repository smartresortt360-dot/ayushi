<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

require_once dirname(__DIR__) . '/vendor/autoload.php';

Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();

function setting(string $key, ?string $fallback = null): ?string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return $value === false || $value === '' ? $fallback : (string) $value;
}

function json_response(int $status, array $data): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        header('Allow: ' . $method);
        json_response(405, ['error' => 'Method not allowed.']);
    }
}

function require_same_origin(): void
{
    $baseUrl = setting('APP_BASE_URL');
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $base = $baseUrl ? parse_url($baseUrl) : false;
    $expected = is_array($base) && isset($base['scheme'], $base['host'])
        ? strtolower($base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : ''))
        : '';

    if ($expected === '' || $origin === '' || !hash_equals($expected, strtolower(rtrim($origin, '/')))) {
        json_response(403, ['error' => 'Request origin is not allowed.']);
    }
}

function request_data(): array
{
    $contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if ($contentType !== 'application/json') {
        json_response(415, ['error' => 'Send the form as JSON.']);
    }

    $body = file_get_contents('php://input', false, null, 0, 32769);
    if ($body === false || strlen($body) > 32768) {
        json_response(413, ['error' => 'Request is too large.']);
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        json_response(400, ['error' => 'Invalid request.']);
    }

    return $data;
}

function clean_text(mixed $value, int $maxBytes, bool $allowNewlines = true): string
{
    if (!is_string($value)) {
        return '';
    }

    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    if (!$allowNewlines) {
        $value = str_replace(["\r", "\n"], ' ', $value);
    }
    $value = trim($value);
    return strlen($value) <= $maxBytes ? $value : '';
}

function storage_dir(string $child = ''): string
{
    $root = setting('APP_STORAGE_DIR', dirname(__DIR__) . '/storage');
    $path = rtrim($root, DIRECTORY_SEPARATOR);
    if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
        throw new RuntimeException('Cannot create private storage directory.');
    }
    if ($child !== '') {
        $path .= DIRECTORY_SEPARATOR . $child;
        if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
            throw new RuntimeException('Cannot create private storage directory.');
        }
    }
    return $path;
}

function rate_limit(string $scope, int $limit = 6, int $windowSeconds = 900): bool
{
    $key = setting('APP_KEY', '');
    if (strlen($key) < 32) {
        throw new RuntimeException('APP_KEY must contain at least 32 characters.');
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $fileKey = hash_hmac('sha256', $scope . '|' . $ip, $key);
    $handle = fopen(storage_dir('limits') . '/' . $fileKey . '.json', 'c+');
    if ($handle === false) {
        throw new RuntimeException('Cannot open rate limit store.');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Cannot lock rate limit store.');
        }
        $contents = stream_get_contents($handle);
        $hits = is_string($contents) ? json_decode($contents, true) : [];
        $now = time();
        $hits = is_array($hits) ? array_values(array_filter($hits, static fn ($hit) => is_int($hit) && $hit > $now - $windowSeconds)) : [];
        if (count($hits) >= $limit) {
            return false;
        }
        $hits[] = $now;
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($hits));
        fflush($handle);
        return true;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function create_reply_token(array $inquiry): string
{
    $token = bin2hex(random_bytes(32));
    $record = $inquiry + ['expires_at' => time() + (90 * 24 * 60 * 60)];
    $path = storage_dir('inquiries') . '/' . hash('sha256', $token) . '.json';
    if (file_put_contents($path, json_encode($record, JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
        throw new RuntimeException('Cannot save inquiry.');
    }
    chmod($path, 0600);
    return $token;
}

function find_inquiry(string $token): ?array
{
    if (!preg_match('/\A[a-f0-9]{64}\z/', $token)) {
        return null;
    }
    $path = storage_dir('inquiries') . '/' . hash('sha256', $token) . '.json';
    $json = is_file($path) ? file_get_contents($path) : false;
    $record = is_string($json) ? json_decode($json, true) : null;
    if (!is_array($record) || ($record['expires_at'] ?? 0) < time()) {
        return null;
    }
    return $record;
}

function escape_html(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function send_email(string $toEmail, string $toName, string $subject, string $html, string $text, string $replyToEmail, string $replyToName): void
{
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = setting('SMTP_HOST', '');
    $mail->SMTPAuth = true;
    $mail->Username = setting('SMTP_USERNAME', '');
    $mail->Password = setting('SMTP_PASSWORD', '');
    $mail->Port = (int) setting('SMTP_PORT', '587');
    $mail->SMTPSecure = strtolower(setting('SMTP_SECURE', 'tls') ?? 'tls') === 'ssl'
        ? PHPMailer::ENCRYPTION_SMTPS
        : PHPMailer::ENCRYPTION_STARTTLS;
    $mail->CharSet = PHPMailer::CHARSET_UTF8;
    $mail->setFrom(setting('OWNER_EMAIL', ''), setting('OWNER_NAME', 'Ayushi Malviya'));
    $mail->addAddress($toEmail, $toName);
    $mail->addReplyTo($replyToEmail, $replyToName);
    $mail->Subject = $subject;
    $mail->isHTML(true);
    $mail->Body = $html;
    $mail->AltBody = $text;
    $mail->send();
}