<?php
// api/admin/departments.php

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
    $result = supabaseRequest('GET', 'departments', null, [
        'select' => '*,doctor_types(*)',
        'order' => 'order.asc'
    ]);
    jsonResponse($result['data'] ?? []);
}

// POST
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $name = $input['name'] ?? null;
    $icon_url = $input['icon_url'] ?? null;
    
    if (!$name) {
        jsonResponse(['error' => 'اسم القسم مطلوب'], 400);
    }
    
    $orderResult = supabaseRequest('GET', 'departments', null, [
        'select' => 'order',
        'order' => 'order.desc',
        'limit' => 1
    ]);
    
    $nextOrder = 1;
    if ($orderResult['status'] === 200 && !empty($orderResult['data'])) {
        $nextOrder = $orderResult['data'][0]['order'] + 1;
    }
    
    $deptData = [
        'name' => $name,
        'icon_url' => $icon_url,
        'order' => $nextOrder,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $result = supabaseRequest('POST', 'departments', $deptData);
    
    if ($result['status'] !== 201) {
        jsonResponse(['error' => 'فشل إضافة القسم'], 500);
    }
    
    jsonResponse($result['data'][0] ?? [], 201);
}

// PUT
if ($method === 'PUT') {
    if (!$id) {
        jsonResponse(['error' => 'معرف القسم مطلوب'], 400);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $updateData = [];
    if (isset($input['name'])) $updateData['name'] = $input['name'];
    if (isset($input['icon_url'])) $updateData['icon_url'] = $input['icon_url'];
    $updateData['updated_at'] = date('Y-m-d H:i:s');
    
    if (empty($updateData)) {
        jsonResponse(['error' => 'لا توجد بيانات للتحديث'], 400);
    }
    
    $result = supabaseRequest('PATCH', 'departments?id=eq.' . $id, $updateData);
    
    if ($result['status'] !== 200) {
        jsonResponse(['error' => 'فشل تحديث القسم'], 500);
    }
    
    jsonResponse($result['data'][0] ?? []);
}

// DELETE
if ($method === 'DELETE') {
    if (!$id) {
        jsonResponse(['error' => 'معرف القسم مطلوب'], 400);
    }
    
    $result = supabaseRequest('DELETE', 'departments?id=eq.' . $id);
    
    if ($result['status'] !== 200 && $result['status'] !== 204) {
        jsonResponse(['error' => 'فشل حذف القسم'], 500);
    }
    
    jsonResponse(['message' => 'تم حذف القسم بنجاح']);
}

jsonResponse(['error' => 'Method not allowed'], 405);
?>