<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Load .env values into $_ENV, $_SERVER and putenv().
 * Supports: KEY=value, quoted values, and comments.
 */
function loadEnv(string $envPath): void
{
    static $loaded = false;
    if ($loaded || !is_file($envPath) || !is_readable($envPath)) {
        return;
    }

    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));

        if ($key === '') {
            continue;
        }

        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key . '=' . $value);
    }

    $loaded = true;
}

loadEnv(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

/**
 * Get an environment variable safely.
 */
function env(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return (string) $value;
}

function appUrl(string $path = ''): string
{
    $normalized = ltrim($path, '/');
    return $normalized === '' ? './' : $normalized;
}

function redirect(string $path): never
{
    header('Location: ' . appUrl($path));
    exit;
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

/**
 * @return array{type:string,message:string}|null
 */
function getFlash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    if (isset($_SESSION['flash'])) {
        unset($_SESSION['flash']);
    }

    return is_array($flash) ? $flash : null;
}

function e(?string $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function studentAuth(): ?array
{
    return isset($_SESSION['student_auth']) && is_array($_SESSION['student_auth']) ? $_SESSION['student_auth'] : null;
}

function adminAuth(): ?array
{
    if (isset($_SESSION['admin_session']) && is_array($_SESSION['admin_session'])) {
        return $_SESSION['admin_session'];
    }

    return isset($_SESSION['admin_auth']) && is_array($_SESSION['admin_auth']) ? $_SESSION['admin_auth'] : null;
}

function requireStudentAuth(): void
{
    if (!is_array(studentAuth())) {
        setFlash('error', 'Student authorization required before accessing this terminal.');
        redirect('login.php');
    }
}

function requireAdminAuth(): void
{
    if (!is_array(adminAuth())) {
        setFlash('error', 'Administrative authorization required before accessing this control panel.');
        redirect('admin.php');
    }
}

/**
 * Fetch latest news items from Supabase table berita.
 *
 * @return array<int, array<string, mixed>>
 */
function fetchLatestNews(int $limit = 3): array
{
    $safeLimit = max(1, min($limit, 20));
    $endpoint = 'berita?select=id,title,content,created_at&order=created_at.desc&limit=' . $safeLimit;
    $result = supabaseRequest('GET', $endpoint);

    if ($result['success'] && is_array($result['data'])) {
        return $result['data'];
    }

    return [];
}

function formatNewsDate(?string $isoDate): string
{
    if ($isoDate === null || trim($isoDate) === '') {
        return '-';
    }

    try {
        $date = new DateTime($isoDate);
        return $date->format('d M Y, H:i');
    } catch (Exception $e) {
        return (string) $isoDate;
    }
}

function retroCss(): string
{
    return <<<CSS
    body { font-family: 'Montserrat', sans-serif; background: #FFF6DE; color: #1A1A1A; }
    .font-head { font-family: 'Oswald', sans-serif; }
    .neo-border { border: 4px solid #1A1A1A; }
    .neo-shadow { box-shadow: 8px 8px 0 0 rgba(26, 26, 26, 1); }
    .neo-input { border: 4px solid #1A1A1A; border-radius: 0.125rem; padding: 0.65rem 0.75rem; width: 100%; background: #fff; }
    .neo-input:focus { outline: none; box-shadow: 4px 4px 0 0 rgba(26, 26, 26, 1); }
    .neo-btn { border: 4px solid #1A1A1A; box-shadow: 6px 6px 0 0 rgba(26, 26, 26, 1); font-weight: 700; transition: transform .08s ease; }
    .neo-btn:hover { transform: translate(-2px, -2px); box-shadow: 8px 8px 0 0 rgba(26, 26, 26, 1); }
    CSS;
}

/**
 * Bullworth Academy brutalist theme — include ONLY on student-facing pages (not admin).
 */
function bullworthStudentCss(): string
{
    return <<<'CSS'
@import url('https://fonts.googleapis.com/css2?family=Courier+Prime:ital,wght@0,400;0,700;1,400&family=Oswald:wght@500;700&family=Special+Elite&display=swap');

:root {
  --bw-accent: #E5A823;
  --bw-navy: #0B1E3B;
  --bw-paper: #F4F1E1;
  --bw-danger: #B22222;
  --bw-cleared: #2E4A31;
}

body.bullworth-student {
  font-family: 'Montserrat', sans-serif;
  background: #1A1C1E !important;
  color: var(--bw-navy) !important;
}

body.bullworth-student .font-head {
  font-family: 'Oswald', sans-serif;
}

body.bullworth-student h1.font-head,
body.bullworth-student h2.font-head,
body.bullworth-student h3.font-head,
body.bullworth-student h4.font-head {
  font-weight: 700;
  text-transform: uppercase;
}

body.bullworth-student .bw-oath,
body.bullworth-student .bw-rules,
body.bullworth-student .bw-fine-print {
  font-family: 'Special Elite', 'Courier Prime', 'Courier New', monospace;
}

body.bullworth-student button,
body.bullworth-student input:not([type="checkbox"]):not([type="radio"]),
body.bullworth-student textarea,
body.bullworth-student select,
body.bullworth-student .neo-btn,
body.bullworth-student .neo-input,
body.bullworth-student .neo-border,
body.bullworth-student .bullworth-card,
body.bullworth-student .bullworth-input {
  border-radius: 0 !important;
}

body.bullworth-student .rounded-sm,
body.bullworth-student .rounded-none,
body.bullworth-student .rounded-full {
  border-radius: 0 !important;
}

body.bullworth-student .neo-border {
  border: 5px solid var(--bw-navy) !important;
}

body.bullworth-student .neo-shadow,
body.bullworth-student .bw-hard-shadow {
  box-shadow: 8px 8px 0px #000000 !important;
}

body.bullworth-student .neo-input {
  border: 5px solid var(--bw-navy) !important;
  background: var(--bw-paper) !important;
  color: var(--bw-navy) !important;
}

body.bullworth-student .neo-input:focus {
  outline: none;
  box-shadow: 8px 8px 0px #000000 !important;
}

body.bullworth-student .neo-btn {
  border: 5px solid var(--bw-navy) !important;
  box-shadow: 8px 8px 0px #000000 !important;
  background: var(--bw-accent) !important;
  color: var(--bw-navy) !important;
}

body.bullworth-student .neo-btn:hover {
  transform: translate(-2px, -2px);
  box-shadow: 8px 8px 0px #000000 !important;
}

body.bullworth-student .neo-btn.bg-white {
  background: var(--bw-paper) !important;
}

body.bullworth-student .bg-\[\#4CA7B8\] {
  background-color: var(--bw-navy) !important;
  color: var(--bw-paper) !important;
}

body.bullworth-student .bg-\[\#FFD65A\] {
  background-color: var(--bw-accent) !important;
  color: var(--bw-navy) !important;
}

body.bullworth-student .bg-\[\#FFF6DE\],
body.bullworth-student .bg-white,
body.bullworth-student .bg-\[\#FDF6E3\] {
  background-color: var(--bw-paper) !important;
  color: var(--bw-navy) !important;
}

body.bullworth-student .bg-black {
  background-color: var(--bw-navy) !important;
}

body.bullworth-student .border-black,
body.bullworth-student .border-l-\[15px\].border-black {
  border-color: var(--bw-navy) !important;
}

body.bullworth-student .text-green-700,
body.bullworth-student .text-green-400 {
  color: var(--bw-cleared) !important;
}

body.bullworth-student .text-red-600 {
  color: var(--bw-danger) !important;
}

body.bullworth-student .bg-\[\#F05D4B\] {
  background-color: var(--bw-danger) !important;
  color: var(--bw-paper) !important;
}

body.bullworth-student .bg-\[\#B22222\] {
  background-color: var(--bw-danger) !important;
  color: var(--bw-paper) !important;
}

body.bullworth-student .bg-\[\#2E4A31\] {
  background-color: var(--bw-cleared) !important;
  color: var(--bw-paper) !important;
}

body.bullworth-student .bg-\[\#E5A823\] {
  background-color: var(--bw-accent) !important;
  color: var(--bw-navy) !important;
}

body.bullworth-student .border-red-600 {
  border-color: var(--bw-danger) !important;
}

body.bullworth-student .bg-red-100 {
  background-color: var(--bw-paper) !important;
  color: var(--bw-danger) !important;
}

body.bullworth-student a.neo-btn.bg-\[\#4CA7B8\],
body.bullworth-student .neo-btn.bg-\[\#4CA7B8\] {
  background-color: var(--bw-navy) !important;
  color: var(--bw-paper) !important;
}

body.bullworth-student .shadow-\[4px_4px_0px_black\],
body.bullworth-student .shadow-\[4px_4px_0px_rgba\(0\,0\,0\,1\)\],
body.bullworth-student .shadow-\[6px_6px_0px_rgba\(0\,0\,0\,1\)\],
body.bullworth-student .shadow-\[8px_8px_0px_rgba\(0\,0\,0\,1\)\],
body.bullworth-student .shadow-\[8px_8px_0px_\#000\],
body.bullworth-student .shadow-\[4px_4px_0px_\#000\] {
  box-shadow: 8px 8px 0px #000000 !important;
}

body.bullworth-student .drop-shadow-\[3px_3px_0px_black\],
body.bullworth-student .drop-shadow-\[4px_4px_0px_rgba\(0\,0\,0\,1\)\],
body.bullworth-student .drop-shadow-\[4px_4px_0px_rgba\(0\,0\,0\,0\.8\)\],
body.bullworth-student .drop-shadow-\[10px_10px_0px_black\] {
  filter: drop-shadow(8px 8px 0 var(--bw-navy)) !important;
}

body.bullworth-student .drop-shadow-\[4px_4px_0px_rgba\(255\,255\,255\,0\.3\)\] {
  filter: drop-shadow(4px 4px 0 rgba(244, 241, 225, 0.35)) !important;
}

body.bullworth-student .decoration-\[\#FFD65A\] {
  text-decoration-color: var(--bw-accent) !important;
}

body.bullworth-student .border-white\/50 {
  border-color: rgba(244, 241, 225, 0.55) !important;
}

body.bullworth-student .border-white\/40 {
  border-color: rgba(244, 241, 225, 0.45) !important;
}

body.bullworth-student .border-white\/30 {
  border-color: rgba(244, 241, 225, 0.35) !important;
}

body.bullworth-student .border-l-4.border-white {
  border-left-color: var(--bw-paper) !important;
}

body.bullworth-student .border-l-4.border-\[\#FFD65A\] {
  border-left-color: var(--bw-accent) !important;
}

body.bullworth-student .text-\[\#FFD65A\] {
  color: var(--bw-accent) !important;
}

.academy-shadow {
  text-shadow: 4px 4px 0 var(--bw-navy);
}

body.bullworth-student .bw-drop-logo {
  filter: drop-shadow(8px 8px 0 var(--bw-navy)) !important;
}

body.bullworth-student .bw-text-hard-lite {
  text-shadow: 4px 4px 0 rgba(244, 241, 225, 0.35);
}
CSS;
}

/**
 * Supabase URL and API key loaded from .env file.
 */
const SUPABASE_URL = 'https://vjrqwscqglqllbylzjgh.supabase.co';
const SUPABASE_KEY = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InZqcnF3c2NxZ2xxbGxieWx6amdoIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzgxMzM1NjgsImV4cCI6MjA5MzcwOTU2OH0.d4MYlu1by9W6d8IfQqOz_quMffZ_5gZIvox3p89Uie4';

/**
 * Perform REST requests to Supabase using cURL only.
 *
 * @param string $method HTTP method (GET, POST, PATCH, DELETE)
 * @param string $endpoint REST endpoint relative to /rest/v1
 * @param array<string, mixed>|null $payload Request payload for write methods
 * @return array{success: bool, status: int, data: mixed, error: string|null}
 */
function supabaseRequest(string $method, string $endpoint, ?array $payload = null): array
{
    $method = strtoupper(trim($method));
    $baseUrl = rtrim((string) env('SUPABASE_URL', SUPABASE_URL), '/');
    $apiKey = (string) env('SUPABASE_KEY', SUPABASE_KEY);

    if ($baseUrl === '' || $apiKey === '') {
        return [
            'success' => false,
            'status' => 0,
            'data' => null,
            'error' => 'SUPABASE_URL or SUPABASE_KEY is missing in .env file.',
        ];
    }

    $endpoint = ltrim($endpoint, '/');

    $url = $baseUrl . '/rest/v1/' . $endpoint;

    $ch = curl_init($url);
    if ($ch === false) {
        return [
            'success' => false,
            'status' => 0,
            'data' => null,
            'error' => 'Failed to initialize cURL.',
        ];
    }

    $headers = [
        'apikey: ' . $apiKey,
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json',
        'Prefer: return=representation',
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 30,
    ]);

    if ($payload !== null && in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'], true)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    }

    $responseBody = curl_exec($ch);
    $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($responseBody === false || $curlError !== '') {
        return [
            'success' => false,
            'status' => $httpStatus,
            'data' => null,
            'error' => $curlError !== '' ? $curlError : 'Unknown cURL error.',
        ];
    }

    $decoded = json_decode($responseBody, true);
    $jsonError = json_last_error();

    if ($jsonError !== JSON_ERROR_NONE && trim($responseBody) !== '') {
        $decoded = $responseBody;
    }

    $isSuccess = $httpStatus >= 200 && $httpStatus < 300;

    return [
        'success' => $isSuccess,
        'status' => $httpStatus,
        'data' => $decoded,
        'error' => $isSuccess ? null : (is_array($decoded) ? ($decoded['message'] ?? 'Request failed.') : 'Request failed.'),
    ];
}

