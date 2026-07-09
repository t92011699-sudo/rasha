<?php
/**
 * ===== Supabase Connection for Vercel =====
 */

function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// تحميل .env من المسار الصحيح
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    loadEnv($envPath);
}

// متغيرات Supabase
define('SUPABASE_URL', getenv('SUPABASE_URL') ?: 'https://qlnnotrkotkmqdesjfmm.supabase.co');
define('SUPABASE_ANON_KEY', getenv('SUPABASE_ANON_KEY') ?: '');
define('SUPABASE_SERVICE_KEY', getenv('SUPABASE_SERVICE_KEY') ?: '');
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'your-super-secret-jwt-key-change-this');

function isDbConfigured(): bool
{
    return SUPABASE_URL !== '' && SUPABASE_ANON_KEY !== '';
}

function getSupabaseConfig(): array
{
    return [
        'url' => SUPABASE_URL,
        'anon_key' => SUPABASE_ANON_KEY,
        'service_key' => SUPABASE_SERVICE_KEY,
    ];
}
