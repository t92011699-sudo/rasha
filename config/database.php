 <?php
/**
 * ===== Database connection for Supabase (PostgreSQL) =====
 * Uses REST API calls instead of PDO
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

loadEnv(__DIR__ . '/../.env');

// ===== Supabase Configuration =====
define('SUPABASE_URL', getenv('SUPABASE_URL') ?: 'https://qlnnotrkotkmqdesjfmm.supabase.co/rest/v1/');
define('SUPABASE_ANON_KEY', getenv('SUPABASE_ANON_KEY') ?: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InFsbm5vdHJrb3RrbXFkZXNqZm1tIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODMzODI2NjEsImV4cCI6MjA5ODk1ODY2MX0.gbHxqDfz5Gj2rci_s-ht0KxUZ6qS1jbpkM3v1_M2fDM');
define('SUPABASE_SERVICE_ROLE_KEY', getenv('SUPABASE_SERVICE_ROLE_KEY') ?: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InFsbm5vdHJrb3RrbXFkZXNqZm1tIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc4MzM4MjY2MSwiZXhwIjoyMDk4OTU4NjYxfQ.55CLw9AcUjqYzW2QwVjFRSszbRd_-nZnkl-O-Z_APtg');
define('JWT_SECRET', getenv('JWT_SECRET') ?: 'your-jwt-secret-key-change-this');

if (SUPABASE_URL === '') {
    error_log('❌ SUPABASE_URL غير موجودة!');
}

/**
 * Makes a request to Supabase REST API
 */
function supabaseRequest(string $endpoint, string $method = 'GET', $data = null, bool $useServiceRole = true)
{
    $url = SUPABASE_URL . $endpoint;
    $apiKey = $useServiceRole ? SUPABASE_SERVICE_ROLE_KEY : SUPABASE_ANON_KEY;
    
    $headers = [
        'apikey: ' . $apiKey,
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
        'Prefer: return=representation'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'PATCH') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("❌ cURL Error: $error");
        return ['status' => 500, 'data' => null, 'error' => $error];
    }

    $decoded = json_decode($response, true);
    
    // Handle empty responses
    if ($response === '' || $response === null) {
        return ['status' => $httpCode, 'data' => null];
    }

    // Handle Supabase error responses
    if ($httpCode >= 400 && isset($decoded['error'])) {
        error_log("❌ Supabase Error: " . json_encode($decoded));
        return ['status' => $httpCode, 'data' => null, 'error' => $decoded['error']];
    }

    return ['status' => $httpCode, 'data' => $decoded];
}

/**
 * Fetches a single row by ID from a Supabase table
 */
function fetchRowById(string $table, int|string $id): ?array
{
    $allowed = ['departments', 'doctor_types', 'custom_slots', 'bookings', 'admins'];
    if (!in_array($table, $allowed, true)) {
        throw new InvalidArgumentException("جدول غير مسموح: $table");
    }
    $result = supabaseRequest("$table?id=eq.$id&select=*");
    if ($result['status'] === 200 && !empty($result['data'])) {
        return $result['data'][0];
    }
    return null;
}

/**
 * Check if Supabase is configured
 */
function isDbConfigured(): bool
{
    return SUPABASE_URL !== '';
}