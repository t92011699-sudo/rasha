<?php
// api/auth.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/jwt.php';
require_once __DIR__ . '/../helpers/http.php';
require_once __DIR__ . '/../helpers/supabase.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, apikey');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = getJsonBody();
    $email = $body['email'] ?? null;
    $password = $body['password'] ?? null;

    if (!$email || !$password) {
        jsonError('البريد الإلكتروني وكلمة المرور مطلوبان', 400);
    }

    try {
        $admins = supabaseGet('admins', [
            'email' => 'eq.' . $email,
            'select' => '*'
        ], true);
        
        if (empty($admins)) {
            jsonError('بيانات الدخول غير صحيحة', 401);
        }
        
        $admin = $admins[0];
        
        if ($password !== $admin['password']) {
            jsonError('بيانات الدخول غير صحيحة', 401);
        }

        $token = jwtSign([
            'id' => $admin['id'],
            'email' => $admin['email'],
            'role' => $admin['role'] ?? 'admin',
        ], JWT_SECRET);

        jsonResponse([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $admin['id'],
                'email' => $admin['email'],
                'role' => $admin['role'] ?? 'admin',
            ],
        ]);
    } catch (Exception $e) {
        jsonError('حدث خطأ أثناء تسجيل الدخول', 500);
    }
}