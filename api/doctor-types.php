<?php
// api/doctor-types.php
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

// جلب أنواع الأطباء لقسم معين
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['department_id'])) {
    $deptId = intval($_GET['department_id']);
    $result = $db->request("doctor_types?department_id=eq.{$deptId}&select=*", 'GET', null, true);
    echo json_encode($result['status'] === 200 ? $result['data'] : []);
    exit();
}

// إضافة نوع طبيب جديد
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['department_id']) || !isset($data['type']) || !isset($data['label'])) {
        echo json_encode(['status' => 'error', 'message' => 'بيانات غير مكتملة']);
        exit();
    }
    
    $result = $db->request('doctor_types', 'POST', $data, true);
    
    if ($result['status'] === 201) {
        echo json_encode(['status' => 'success', 'message' => 'تم إضافة نوع الطبيب', 'data' => $result['data']]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'فشل في الإضافة']);
    }
    exit();
}

// تحديث نوع طبيب
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = intval($params['id'] ?? 0);
    
    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'المعرف مطلوب']);
        exit();
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $result = $db->request("doctor_types?id=eq.{$id}", 'PATCH', $data, true);
    
    echo json_encode([
        'status' => $result['status'] === 200 ? 'success' : 'error',
        'message' => $result['status'] === 200 ? 'تم التحديث' : 'فشل في التحديث'
    ]);
    exit();
}

// حذف نوع طبيب
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = intval($params['id'] ?? 0);
    
    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'المعرف مطلوب']);
        exit();
    }
    
    $result = $db->request("doctor_types?id=eq.{$id}", 'DELETE', null, true);
    
    echo json_encode([
        'status' => $result['status'] === 204 ? 'success' : 'error',
        'message' => $result['status'] === 204 ? 'تم الحذف' : 'فشل في الحذف'
    ]);
    exit();
}
