<?php
// api/admin/slots.php

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

// GET - جلب كل الفترات
if ($method === 'GET') {
    $result = supabaseRequest('GET', 'slots', null, [
        'select' => '*,doctor_types:doctor_type_id(id,type,label,departments:department_id(id,name))',
        'order' => 'date.desc'
    ]);
    
    $slots = $result['data'] ?? [];
    
    $slotsWithBookings = array_map(function($slot) {
        $bookingsResult = supabaseRequest('GET', 'bookings', null, [
            'slot_id' => 'eq.' . $slot['id'],
            'select' => 'id'
        ]);
        
        $currentBookings = 0;
        if ($bookingsResult['status'] === 200 && is_array($bookingsResult['data'])) {
            $currentBookings = count($bookingsResult['data']);
        }
        
        return [
            ...$slot,
            'current_bookings' => $currentBookings,
            'remaining' => $slot['capacity'] - $currentBookings
        ];
    }, $slots);
    
    jsonResponse($slotsWithBookings);
}

// POST - إضافة فترة جديدة
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $doctor_type_id = $input['doctor_type_id'] ?? null;
    $date = $input['date'] ?? null;
    $from_time = $input['from_time'] ?? null;
    $to_time = $input['to_time'] ?? null;
    $capacity = $input['capacity'] ?? null;
    
    if (!$doctor_type_id || !$date || !$from_time || !$to_time || !$capacity) {
        jsonResponse(['error' => 'جميع الحقول مطلوبة'], 400);
    }
    
    $slotData = [
        'doctor_type_id' => $doctor_type_id,
        'date' => $date,
        'from_time' => $from_time,
        'to_time' => $to_time,
        'capacity' => (int)$capacity,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $result = supabaseRequest('POST', 'slots', $slotData);
    
    if ($result['status'] !== 201) {
        jsonResponse(['error' => 'فشل إضافة الفترة'], 500);
    }
    
    jsonResponse($result['data'][0] ?? [], 201);
}

// PUT - تعديل فترة
if ($method === 'PUT') {
    if (!$id) {
        jsonResponse(['error' => 'معرف الفترة مطلوب'], 400);
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    $updateData = [];
    if (isset($input['date'])) $updateData['date'] = $input['date'];
    if (isset($input['from_time'])) $updateData['from_time'] = $input['from_time'];
    if (isset($input['to_time'])) $updateData['to_time'] = $input['to_time'];
    if (isset($input['capacity'])) $updateData['capacity'] = (int)$input['capacity'];
    $updateData['updated_at'] = date('Y-m-d H:i:s');
    
    if (empty($updateData)) {
        jsonResponse(['error' => 'لا توجد بيانات للتحديث'], 400);
    }
    
    $result = supabaseRequest('PATCH', 'slots?id=eq.' . $id, $updateData);
    
    if ($result['status'] !== 200) {
        jsonResponse(['error' => 'فشل تحديث الفترة'], 500);
    }
    
    jsonResponse($result['data'][0] ?? []);
}

// DELETE - حذف فترة
if ($method === 'DELETE') {
    if (!$id) {
        jsonResponse(['error' => 'معرف الفترة مطلوب'], 400);
    }
    
    $bookingsResult = supabaseRequest('GET', 'bookings', null, [
        'slot_id' => 'eq.' . $id,
        'select' => 'id'
    ]);
    
    $count = 0;
    if ($bookingsResult['status'] === 200 && is_array($bookingsResult['data'])) {
        $count = count($bookingsResult['data']);
    }
    
    if ($count > 0) {
        jsonResponse([
            'error' => 'لا يمكن حذف الفترة لأنها تحتوي على حجوزات',
            'bookings_count' => $count
        ], 400);
    }
    
    $result = supabaseRequest('DELETE', 'slots?id=eq.' . $id);
    
    if ($result['status'] !== 200 && $result['status'] !== 204) {
        jsonResponse(['error' => 'فشل حذف الفترة'], 500);
    }
    
    jsonResponse(['message' => 'تم حذف الفترة بنجاح']);
}

jsonResponse(['error' => 'Method not allowed'], 405);
?>