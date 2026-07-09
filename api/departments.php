 <?php
// api/departments.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$db = new Database();

// جلب قسم محدد مع تفاصيله
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $department_id = intval($_GET['id']);
    
    // 1. جلب بيانات القسم
    $deptResult = $db->request("departments?id=eq.{$department_id}&select=*", 'GET', null, true);
    
    if ($deptResult['status'] !== 200 || empty($deptResult['data'])) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'القسم غير موجود']);
        exit();
    }
    
    $department = $deptResult['data'][0];
    
    // 2. جلب أنواع الأطباء لهذا القسم مع الفترات
    $typesResult = $db->request("doctor_types?department_id=eq.{$department_id}&select=*", 'GET', null, true);
    $doctorTypes = $typesResult['status'] === 200 ? $typesResult['data'] : [];
    
    // 3. جلب الفترات المخصصة لهذا القسم
    $slotsResult = $db->request("custom_slots?department_id=eq.{$department_id}&select=*&order=slot_date.asc,from_time.asc", 'GET', null, true);
    $customSlots = $slotsResult['status'] === 200 ? $slotsResult['data'] : [];
    
    // 4. بناء الرد بالهيكل المطلوب
    $response = [
        'name' => $department['name'],
        'icon_url' => $department['icon_url'] ?? 'https://example.com/icons/default.svg',
        'doctor_types' => []
    ];
    
    foreach ($doctorTypes as $type) {
        // فلترة الفترات حسب نوع الطبيب
        $typeSlots = array_filter($customSlots, function($slot) use ($type) {
            return $slot['doctor_type'] === $type['type'];
        });
        
        $formattedSlots = [];
        foreach ($typeSlots as $slot) {
            // حساب عدد الحجوزات الحالية لهذه الفترة
            $bookingCountResult = $db->request(
                "appointments?department_id=eq.{$department_id}&date=eq.{$slot['slot_date']}&time=eq.{$slot['from_time']}&status=neq.cancelled&select=count",
                'GET',
                null,
                true
            );
            $currentBookings = isset($bookingCountResult['data'][0]['count']) ? intval($bookingCountResult['data'][0]['count']) : 0;
            $remaining = intval($slot['capacity']) - $currentBookings;
            
            $formattedSlots[] = [
                'id' => intval($slot['id']),
                'doctor_type_id' => intval($type['id']),
                'date' => $slot['slot_date'],
                'capacity' => intval($slot['capacity']),
                'from_time' => $slot['from_time'],
                'to_time' => $slot['to_time'],
                'created_at' => $slot['created_at'] ?? date('Y-m-d H:i:s'),
                'updated_at' => $slot['updated_at'] ?? date('Y-m-d H:i:s'),
                'current_bookings' => $currentBookings,
                'remaining' => $remaining,
                'available' => $remaining > 0,
                'time_range' => date('H:i', strtotime($slot['from_time'])) . ' - ' . date('H:i', strtotime($slot['to_time'])),
                'time_display' => 'من ' . date('H:i', strtotime($slot['from_time'])) . ' إلى ' . date('H:i', strtotime($slot['to_time'])),
                'from_time_formatted' => date('H:i', strtotime($slot['from_time'])),
                'to_time_formatted' => date('H:i', strtotime($slot['to_time'])),
                'slot_display' => date('H:i', strtotime($slot['from_time'])) . ' - ' . date('H:i', strtotime($slot['to_time']))
            ];
        }
        
        $response['doctor_types'][] = [
            'id' => intval($type['id']),
            'department_id' => intval($type['department_id']),
            'type' => $type['type'],
            'label' => $type['label'],
            'enabled' => $type['enabled'] ?? true,
            'created_at' => $type['created_at'] ?? date('Y-m-d H:i:s'),
            'updated_at' => $type['updated_at'] ?? date('Y-m-d H:i:s'),
            'custom_slots' => $formattedSlots
        ];
    }
    
    echo json_encode($response);
    exit();
}

// جلب جميع الأقسام (للمشرف)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = $db->request('departments?select=*', 'GET', null, true);
    echo json_encode($result['status'] === 200 ? $result['data'] : []);
    exit();
}

// إضافة قسم جديد (للمشرف)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['name']) || empty($data['name'])) {
        echo json_encode(['status' => 'error', 'message' => 'اسم القسم مطلوب']);
        exit();
    }
    
    $result = $db->request('departments', 'POST', [
        'name' => $data['name'],
        'description' => $data['description'] ?? null,
        'icon_url' => $data['icon_url'] ?? null
    ], true);
    
    if ($result['status'] === 201) {
        echo json_encode(['status' => 'success', 'message' => 'تم إضافة القسم بنجاح', 'data' => $result['data']]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'فشل في إضافة القسم']);
    }
    exit();
}

// تحديث قسم (للمشرف)
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = intval($params['id'] ?? 0);
    
    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'معرف القسم مطلوب']);
        exit();
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $result = $db->request("departments?id=eq.{$id}", 'PATCH', $data, true);
    
    echo json_encode([
        'status' => $result['status'] === 200 ? 'success' : 'error',
        'message' => $result['status'] === 200 ? 'تم تحديث القسم' : 'فشل في التحديث'
    ]);
    exit();
}

// حذف قسم (للمشرف)
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = intval($params['id'] ?? 0);
    
    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'معرف القسم مطلوب']);
        exit();
    }
    
    $result = $db->request("departments?id=eq.{$id}", 'DELETE', null, true);
    
    echo json_encode([
        'status' => $result['status'] === 204 ? 'success' : 'error',
        'message' => $result['status'] === 204 ? 'تم حذف القسم' : 'فشل في الحذف'
    ]);
    exit();
}
?>
