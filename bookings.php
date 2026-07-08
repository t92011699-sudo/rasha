<?php
// api/bookings.php

require_once __DIR__ . '/../config/supabase.php';

header('Content-Type: application/json');

// POST - إنشاء حجز
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $slot_id = $input['slot_id'] ?? null;
    $patient_name = $input['patient_name'] ?? null;
    $patient_age = $input['patient_age'] ?? null;
    $patient_phone = $input['patient_phone'] ?? null;
    $patient_gender = $input['patient_gender'] ?? null;
    
    if (!$slot_id || !$patient_name || !$patient_age || !$patient_phone || !$patient_gender) {
        jsonResponse(['error' => 'جميع الحقول مطلوبة'], 400);
    }
    
    if (!in_array($patient_gender, ['male', 'female'])) {
        jsonResponse(['error' => 'الجنس يجب أن يكون male أو female'], 400);
    }
    
    if (strlen($patient_phone) < 10) {
        jsonResponse(['error' => 'رقم التليفون غير صحيح'], 400);
    }
    
    // التحقق من تكرار رقم التليفون
    $existingResult = supabaseRequest('GET', 'bookings', null, [
        'patient_phone' => 'eq.' . $patient_phone,
        'select' => 'id,patient_name'
    ]);
    
    if ($existingResult['status'] === 200 && !empty($existingResult['data'])) {
        jsonResponse([
            'error' => 'رقم التليفون مستخدم بالفعل في حجز آخر',
            'existing_booking' => [
                'id' => $existingResult['data'][0]['id'],
                'patient_name' => $existingResult['data'][0]['patient_name']
            ]
        ], 400);
    }
    
    // جلب تفاصيل الفترة
    $slotResult = supabaseRequest('GET', 'slots', null, [
        'id' => 'eq.' . $slot_id,
        'select' => '*,doctor_types:doctor_type_id(id,type,label,departments:department_id(id,name))'
    ]);
    
    if ($slotResult['status'] !== 200 || empty($slotResult['data'])) {
        jsonResponse(['error' => 'الموعد غير موجود'], 404);
    }
    
    $slot = $slotResult['data'][0];
    
    // التحقق من السعة
    $bookingsResult = supabaseRequest('GET', 'bookings', null, [
        'slot_id' => 'eq.' . $slot_id,
        'select' => 'id'
    ]);
    
    $currentCount = 0;
    if ($bookingsResult['status'] === 200 && is_array($bookingsResult['data'])) {
        $currentCount = count($bookingsResult['data']);
    }
    
    if ($currentCount >= $slot['capacity']) {
        jsonResponse([
            'error' => 'الموعد مكتمل، لا توجد أماكن متاحة',
            'capacity' => $slot['capacity'],
            'current_bookings' => $currentCount,
            'remaining' => 0
        ], 400);
    }
    
    // إنشاء الحجز
    $bookingData = [
        'slot_id' => (int)$slot_id,
        'patient_name' => $patient_name,
        'patient_age' => (int)$patient_age,
        'patient_phone' => $patient_phone,
        'patient_gender' => $patient_gender,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s')
    ];
    
    $insertResult = supabaseRequest('POST', 'bookings', $bookingData);
    
    if ($insertResult['status'] !== 201 || empty($insertResult['data'])) {
        jsonResponse(['error' => 'فشل إنشاء الحجز'], 500);
    }
    
    $booking = $insertResult['data'][0];
    $newCount = $currentCount + 1;
    
    jsonResponse([
        ...$booking,
        'capacity' => $slot['capacity'],
        'current_bookings' => $newCount,
        'remaining' => $slot['capacity'] - $newCount
    ], 201);
}

// DELETE - إلغاء حجز
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $id = $_GET['id'] ?? null;
    
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