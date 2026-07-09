<?php
// api/custom-slots.php
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

// جلب الفترات لقسم معين
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['department_id'])) {
    $deptId = intval($_GET['department_id']);
    $date = isset($_GET['date']) ? $_GET['date'] : '';
    
    $query = "custom_slots?department_id=eq.{$deptId}&select=*";
    if (!empty($date)) {
        $query .= "&slot_date=eq.{$date}";
    }
    $query .= "&order=slot_date.asc,from_time.asc";
    
    $result = $db->request($query, 'GET', null, true);
    echo json_encode($result['status'] === 200 ? $result['data'] : []);
    exit();
}

// إضافة فترة جديدة
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['department_id', 'doctor_type', 'slot_date', 'from_time', 'to_time'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            echo json_encode(['status' => 'error', 'message' => "الحقل {$field} مطلوب"]);
            exit();
        }
    }
    
    if (!isset($data['capacity'])) {
        $data['capacity'] = 1;
    }
    
    $result = $db->request('custom_slots', 'POST', $data, true);
    
    if ($result['status'] === 201) {
        echo json_encode(['status' => 'success', 'message' => 'تم إضافة الفترة', 'data' => $result['data']]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'فشل في إضافة الفترة']);
    }
    exit();
}

// تحديث فترة
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = intval($params['id'] ?? 0);
    
    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'المعرف مطلوب']);
        exit();
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $result = $db->request("custom_slots?id=eq.{$id}", 'PATCH', $data, true);
    
    echo json_encode([
        'status' => $result['status'] === 200 ? 'success' : 'error',
        'message' => $result['status'] === 200 ? 'تم التحديث' : 'فشل في التحديث'
    ]);
    exit();
}

// حذف فترة
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = intval($params['id'] ?? 0);
    
    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'المعرف مطلوب']);
        exit();
    }
    
    $result = $db->request("custom_slots?id=eq.{$id}", 'DELETE', null, true);
    
    echo json_encode([
        'status' => $result['status'] === 204 ? 'success' : 'error',
        'message' => $result['status'] === 204 ? 'تم الحذف' : 'فشل في الحذف'
    ]);
    exit();
}