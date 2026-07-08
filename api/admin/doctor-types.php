<?php
// api/admin/doctor-types.php

require_once __DIR__ . '/../../config/supabase.php';

header('Content-Type: application/json');

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';
$token = str_replace('Bearer ', '', $authHeader);

if (!$token) {
    jsonResponse(['error' => 'لم يتم توفير التوكن'], 401);
}

$user = verifyToken($token);
if (!$user) {
    jsonResponse(['error' => 'توكن غير صالح'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);

$department_id = $input['department_id'] ?? null;
$type = $input['type'] ?? null;
$label = $input['label'] ?? null;

if (!$department_id || !$type || !$label) {
    jsonResponse(['error' => 'جميع الحقول مطلوبة'], 400);
}

if (!in_array($type, ['male', 'female'])) {
    jsonResponse(['error' => 'type يجب أن يكون male أو female'], 400);
}

$doctorTypeData = [
    'department_id' => $department_id,
    'type' => $type,
    'label' => $label,
    'enabled' => true,
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s')
];

$result = supabaseRequest('POST', 'doctor_types', $doctorTypeData);

if ($result['status'] !== 201) {
    jsonResponse(['error' => 'فشل إضافة نوع الطبيب'], 500);
}

jsonResponse($result['data'][0] ?? [], 201);
?>