<?php
// api/bookings.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$db = new Database();

// حجز موعد جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // التحقق من البيانات المطلوبة
    $required = ['department_id', 'doctor_type', 'slot_id', 'booking_date', 'booking_time', 'patient_name', 'patient_age', 'patient_phone'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && empty($data[$field]))) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => "الحقل {$field} مطلوب"]);
            exit();
        }
    }
    
    // 1. التحقق من وجود الفترة المخصصة
    $slot_id = intval($data['slot_id']);
    $slotResult = $db->request("custom_slots?id=eq.{$slot_id}&select=*", 'GET', null, true);
    if ($slotResult['status'] !== 200 || empty($slotResult['data'])) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'الفترة المحددة غير موجودة']);
        exit();
    }
    
    $slot = $slotResult['data'][0];
    
    // 2. التحقق من أن الفترة تابعة للقسم ونوع الطبيب
    if (intval($slot['department_id']) !== intval($data['department_id']) || $slot['doctor_type'] !== $data['doctor_type']) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'بيانات الفترة غير متطابقة']);
        exit();
    }
    
    // 3. التحقق من السعة المتبقية
    $bookingCountResult = $db->request(
        "appointments?department_id=eq.{$data['department_id']}&date=eq.{$data['booking_date']}&time=eq.{$data['booking_time']}&status=neq.cancelled&select=count",
        'GET',
        null,
        true
    );
    
    $currentBookings = isset($bookingCountResult['data'][0]['count']) ? intval($bookingCountResult['data'][0]['count']) : 0;
    
    if ($currentBookings >= intval($slot['capacity'])) {
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'لا توجد سعة متاحة في هذا الوقت']);
        exit();
    }
    
    // 4. إنشاء الحجز
    $appointmentData = [
        'name' => $data['patient_name'],
        'phone' => $data['patient_phone'],
        'age' => intval($data['patient_age']),
        'date' => $data['booking_date'],
        'time' => $data['booking_time'],
        'status' => 'pending',
        'department_id' => intval($data['department_id']),
        'notes' => "نوع الطبيب: {$data['doctor_type']}, الجنس: {$data['patient_gender'] ?? 'N/A'}"
    ];
    
    $result = $db->request('appointments', 'POST', $appointmentData, true);
    
    if ($result['status'] === 201) {
        http_response_code(201);
        echo json_encode([
            'status' => 'success',
            'message' => 'تم حجز الموعد بنجاح',
            'booking_id' => $result['data'][0]['id'] ?? null,
            'data' => $result['data'][0] ?? null
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'فشل في إنشاء الحجز']);
    }
    exit();
}

// جلب حجوزات قسم معين (للمشرف)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['department_id'])) {
    $deptId = intval($_GET['department_id']);
    $date = isset($_GET['date']) ? $_GET['date'] : '';
    
    $query = "appointments?department_id=eq.{$deptId}&select=*";
    if (!empty($date)) {
        $query .= "&date=eq.{$date}";
    }
    $query .= "&order=date.asc,time.asc";
    
    $result = $db->request($query, 'GET', null, true);
    echo json_encode($result['status'] === 200 ? $result['data'] : []);
    exit();
}

// جلب جميع الحجوزات (للمشرف)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = $db->request('appointments?select=*&order=date.asc,time.asc', 'GET', null, true);
    echo json_encode($result['status'] === 200 ? $result['data'] : []);
    exit();
}

// إلغاء حجز
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = intval($params['id'] ?? 0);
    
    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'معرف الحجز مطلوب']);
        exit();
    }
    
    $result = $db->request("appointments?id=eq.{$id}", 'PATCH', ['status' => 'cancelled'], true);
    
    if ($result['status'] === 200) {
        http_response_code(200);
    } else {
        http_response_code(404);
    }
    
    echo json_encode([
        'status' => $result['status'] === 200 ? 'success' : 'error',
        'message' => $result['status'] === 200 ? 'تم إلغاء الحجز' : 'فشل في الإلغاء'
    ]);
    exit();
}
?>
