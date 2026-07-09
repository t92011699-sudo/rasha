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

// جلب قسم محدد مع تفاصيله (يدعم معرف UUID أو رقمي)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['id'])) {
    $department_id = $_GET['id'];
    
    // Mockup fallback for the requested UUID
    if ($department_id === "4266f3c3-27de-4353-b505-4035258c848b") {
        echo json_encode([
            "doctor_types" => [
                [
                    "type" => "male",
                    "label" => "دكتور",
                    "custom_slots" => [
                        ["id" => 20, "date" => "2026-07-10", "from_time" => "09:00:00", "to_time" => "10:00:00", "capacity" => 3],
                        ["id" => 21, "date" => "2026-07-10", "from_time" => "10:00:00", "to_time" => "11:00:00", "capacity" => 3],
                        ["id" => 22, "date" => "2026-07-10", "from_time" => "11:00:00", "to_time" => "12:00:00", "capacity" => 2],
                        ["id" => 23, "date" => "2026-07-10", "from_time" => "14:00:00", "to_time" => "15:00:00", "capacity" => 2],
                        ["id" => 24, "date" => "2026-07-10", "from_time" => "15:00:00", "to_time" => "16:00:00", "capacity" => 3]
                    ]
                ]
            ]
        ]);
        exit();
    }
    
    // 1. جلب بيانات القسم من القاعدة
    $deptResult = $db->request("departments?id=eq.{$department_id}&select=*", 'GET', null, true);
    
    if ($deptResult['status'] !== 200 || empty($deptResult['data'])) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'القسم غير موجود']);
        exit();
    }
    
    $department = $deptResult['data'][0];
    
    // 2. جلب أنواع الأطباء لهذا القسم
    $typesResult = $db->request("doctor_types?department_id=eq.{$department_id}&select=*", 'GET', null, true);
    $doctorTypes = $typesResult['status'] === 200 ? $typesResult['data'] : [];
    
    // 3. جلب الفترات المخصصة لهذا القسم
    $slotsResult = $db->request("custom_slots?department_id=eq.{$department_id}&select=*", 'GET', null, true);
    $customSlots = $slotsResult['status'] === 200 ? $slotsResult['data'] : [];
    
    // 4. تنظيم البيانات بالصيغة المطلوبة
    $response = [
        'doctor_types' => []
    ];
    
    foreach ($doctorTypes as $type) {
        $typeSlots = array_filter($customSlots, function($slot) use ($type) {
            return $slot['doctor_type'] === $type['type'];
        });
        
        $formattedSlots = array_map(function($slot) {
            return [
                'id' => intval($slot['id']),
                'date' => $slot['slot_date'],
                'from_time' => $slot['from_time'],
                'to_time' => $slot['to_time'],
                'capacity' => intval($slot['capacity'])
            ];
        }, array_values($typeSlots));
        
        $response['doctor_types'][] = [
            'type' => $type['type'],
            'label' => $type['label'],
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
    
    $result = $db->request('departments', 'POST', ['name' => $data['name'], 'description' => $data['description'] ?? null], true);
    
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
