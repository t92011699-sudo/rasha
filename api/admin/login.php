<?php
// api/admin/login.php

require_once __DIR__ . '/../../config/supabase.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT;

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? null;
$password = $input['password'] ?? null;

if (!$email || !$password) {
    jsonResponse(['error' => 'البريد الإلكتروني وكلمة المرور مطلوبان'], 400);
}

$result = supabaseRequest('GET', 'admins?email=eq.' . urlencode($email) . '&select=*');

if ($result['status'] !== 200 || empty($result['data'])) {
    jsonResponse(['error' => 'بيانات الدخول غير صحيحة'], 401);
}

$admin = $result['data'][0];

if ($admin['password'] !== $password) {
    jsonResponse(['error' => 'بيانات الدخول غير صحيحة'], 401);
}

$payload = [
    'id' => $admin['id'],
    'email' => $admin['email'],
    'role' => 'admin',
    'iat' => time(),
    'exp' => time() + (7 * 24 * 60 * 60)
];

$token = JWT::encode($payload, $_ENV['JWT_SECRET'], 'HS256');

jsonResponse([
    'success' => true,
    'message' => 'تم تسجيل الدخول بنجاح',
    'token' => $token,
    'admin' => ['id' => $admin['id'], 'email' => $admin['email']]
]);
?>