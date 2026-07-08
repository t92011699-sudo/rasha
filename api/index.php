 <?php
// api/index.php

// تحميل autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// تحميل .env
use Dotenv\Dotenv;
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// الحصول على المسار المطلوب
$path = $_SERVER['REQUEST_URI'] ?? '/';

// توجيه الطلبات
if ($path === '/' || $path === '') {
    header('Content-Type: application/json');
    echo json_encode([
        'message' => '🚀 Rasha Clinic API',
        'version' => '1.0.0',
        'endpoints' => [
            'POST /api/admin/login',
            'GET /api/departments',
            'GET /api/slots',
            'POST /api/bookings'
        ]
    ]);
    exit;
}

// محاولة تحميل الملف المطلوب
$file = __DIR__ . $path . '.php';
if (file_exists($file)) {
    require $file;
    exit;
}

// 404
http_response_code(404);
echo json_encode(['error' => 'Not found']);