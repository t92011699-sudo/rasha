<?php
// api/admin/bookings.php

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

$method = $_SERVER['REQUEST_METHOD'];
$id = $_GET['id'] ?? null;

// GET
if ($method === 'GET') {
    $result = supabaseRequest('GET', 'bookings', null, [
        'select' => '*,slots:slot_id(id,date,from_time,to_time,capacity,doctor_types:doctor_type_id(id,type,label,departments:department_id(id,name)))',
        'order' => 'created_at.desc'
    ]);
    jsonResponse($result['data'] ?? []);
}

// DELETE
if ($method === 'DELETE') {
    if (!$id) {
        jsonResponse(['error' => 'معرف الحجز مطلوب'], 400);
    }
    
    $result = supabaseRequest('DELETE', 'bookings?id=eq.' . $id);
    
    if ($result['status'] !== 200 && $result['status'] !== 204) {
        jsonResponse(['error' => 'فشل إلغاء الحجز'], 500);
    }
    
    jsonResponse(['message' => 'تم إلغاء الحجز بنجاح']);
}

jsonResponse(['error' => 'Method not allowed'], 405);
?>