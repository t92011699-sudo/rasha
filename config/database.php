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
define('SUPABASE_URL', getenv('SUPABASE_URL') ?: 'https://njsuptpdtllgxefebgtn.supabase.co');
define('SUPABASE_ANON_KEY', getenv('SUPABASE_ANON_KEY') ?: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im5qc3VwdHBkdGxsZ3hlZmViZ3RuIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODM2MDU4NDMsImV4cCI6MjA5OTE4MTg0M30.jD6s95_qwchtNWRAHrzgoMpT7qJKVgdMa1m3V90YAlE');
define('SUPABASE_SERVICE_KEY', getenv('SUPABASE_SERVICE_KEY') ?: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im5qc3VwdHBkdGxsZ3hlZmViZ3RuIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc4MzYwNTg0MywiZXhwIjoyMDk5MTgxODQzfQ.BgxyZdeGWet7OLn46OE6MKpSNlIPpY0l6f0QJahj9xY');
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

class Database {
    public function request($endpoint, $method = 'GET', $data = null, $useServiceKey = false) {
        try {
            $result = supabaseRequest($method, $endpoint, $data, [], $useServiceKey);
            return [
                'status' => 200,
                'data' => $result
            ];
        } catch (Exception $e) {
            return [
                'status' => 500,
                'message' => $e->getMessage()
            ];
        }
    }
}