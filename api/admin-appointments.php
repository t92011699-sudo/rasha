 <?php
// api/admin-appointments.php (تحديث طفيف)
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$db = new Database();

// جلب جميع الحجوزات مع إمكانية الفلترة حسب القسم
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $department_id = isset($_GET['department_id']) ? intval($_GET['department_id']) : null;
    $query = 'appointments?select=*&order=date.asc,time.asc';
    
    if ($department_id) {
        $query .= "&department_id=eq.{$department_id}";
    }
    
    $result = $db->request($query, 'GET', null, true);
    
    if ($result['status'] === 200) {
        echo json_encode($result['data']);
    } else {
        echo json_encode([]);
    }
    exit();
}

// تحديث حالة الحجز
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = intval($params['id'] ?? 0);
    
    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'معرف الحجز مطلوب']);
        exit();
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $result = $db->request("appointments?id=eq.{$id}", 'PATCH', $data, true);
    
    if ($result['status'] === 200) {
        echo json_encode(['status' => 'success', 'message' => 'تم تحديث الحجز بنجاح']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'فشل في تحديث الحجز']);
    }
    exit();
}

// حذف حجز
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = intval($params['id'] ?? 0);
    
    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'معرف الحجز مطلوب']);
        exit();
    }
    
    $result = $db->request("appointments?id=eq.{$id}", 'DELETE', null, true);
    
    if ($result['status'] === 204) {
        echo json_encode(['status' => 'success', 'message' => 'تم حذف الحجز بنجاح']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'فشل في حذف الحجز']);
    }
    exit();
}
?>