<?php
// api/prices.php
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

// GET - جلب جميع الأسعار (للعرض العام)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $id = isset($_GET['id']) ? intval($_GET['id']) : null;
    $category = isset($_GET['category']) ? $_GET['category'] : null;
    $activeOnly = isset($_GET['active_only']) ? filter_var($_GET['active_only'], FILTER_VALIDATE_BOOLEAN) : true;
    
    $query = 'prices?select=*';
    
    if ($id) {
        $query .= "&id=eq.{$id}";
    }
    if ($category) {
        $query .= "&category=eq.{$category}";
    }
    if ($activeOnly) {
        $query .= "&is_active=eq.true";
    }
    
    $query .= '&order=display_order.asc,label.asc';
    
    $result = $db->request($query, 'GET', null, true);
    
    if ($result['status'] === 200 && $result['data'] !== null) {
        echo json_encode($result['data']);
    } else {
        echo json_encode([]);
    }
    exit();
}

// POST - إضافة سعر جديد (للأدمن فقط)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // التحقق من الصلاحية (يمكن إضافة توكن)
    // $auth = validateAdminToken(); // اختياري
    
    if (!isset($data['label']) || empty(trim($data['label']))) {
        echo json_encode(['status' => 'error', 'message' => 'الاسم مطلوب']);
        exit();
    }
    if (!isset($data['price']) || $data['price'] === '' || floatval($data['price']) < 0) {
        echo json_encode(['status' => 'error', 'message' => 'السعر مطلوب وقيمة موجبة']);
        exit();
    }
    
    // جلب أعلى ترتيب
    $orderResult = $db->request('prices?select=display_order&order=display_order.desc&limit=1', 'GET', null, true);
    $nextOrder = 0;
    if ($orderResult['status'] === 200 && !empty($orderResult['data'])) {
        $nextOrder = intval($orderResult['data'][0]['display_order']) + 1;
    }
    
    $result = $db->request('prices', 'POST', [
        'label' => trim($data['label']),
        'price' => floatval($data['price']),
        'description' => isset($data['description']) ? trim($data['description']) : null,
        'category' => isset($data['category']) ? trim($data['category']) : 'general',
        'icon' => isset($data['icon']) ? trim($data['icon']) : null,
        'is_active' => isset($data['is_active']) ? (bool)$data['is_active'] : true,
        'display_order' => $nextOrder,
        'created_at' => date('Y-m-d\TH:i:s.000\Z'),
        'updated_at' => date('Y-m-d\TH:i:s.000\Z')
    ], true);
    
    if ($result['status'] === 201) {
        echo json_encode([
            'status' => 'success',
            'message' => 'تم إضافة السعر بنجاح',
            'data' => $result['data']
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'فشل في إضافة السعر']);
    }
    exit();
}

// PUT - تحديث سعر (للأدمن فقط)
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = isset($params['id']) ? intval($params['id']) : 0;
    
    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'المعرف مطلوب']);
        exit();
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $updateData = ['updated_at' => date('Y-m-d\TH:i:s.000\Z')];
    
    if (isset($data['label'])) {
        $updateData['label'] = trim($data['label']);
    }
    if (isset($data['price']) && $data['price'] !== '') {
        $updateData['price'] = floatval($data['price']);
    }
    if (array_key_exists('description', $data)) {
        $updateData['description'] = isset($data['description']) ? trim($data['description']) : null;
    }
    if (array_key_exists('category', $data)) {
        $updateData['category'] = isset($data['category']) ? trim($data['category']) : 'general';
    }
    if (array_key_exists('icon', $data)) {
        $updateData['icon'] = isset($data['icon']) ? trim($data['icon']) : null;
    }
    if (array_key_exists('is_active', $data)) {
        $updateData['is_active'] = (bool)$data['is_active'];
    }
    if (array_key_exists('display_order', $data)) {
        $updateData['display_order'] = intval($data['display_order']);
    }
    
    $result = $db->request("prices?id=eq.{$id}", 'PATCH', $updateData, true);
    
    if ($result['status'] === 200) {
        echo json_encode([
            'status' => 'success',
            'message' => 'تم تحديث السعر بنجاح'
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'فشل في تحديث السعر']);
    }
    exit();
}

// DELETE - حذف سعر (للأدمن فقط)
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = isset($params['id']) ? intval($params['id']) : 0;
    
    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'المعرف مطلوب']);
        exit();
    }
    
    $result = $db->request("prices?id=eq.{$id}", 'DELETE', null, true);
    
    if ($result['status'] === 204) {
        echo json_encode([
            'status' => 'success',
            'message' => 'تم حذف السعر بنجاح'
        ]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'فشل في حذف السعر']);
    }
    exit();
}

// GET - جلب التصنيفات
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['categories'])) {
    $result = $db->request('prices?select=category&is_active=eq.true', 'GET', null, true);
    
    $categories = [];
    if ($result['status'] === 200 && !empty($result['data'])) {
        foreach ($result['data'] as $item) {
            if (!empty($item['category'])) {
                $categories[] = $item['category'];
            }
        }
        $categories = array_unique($categories);
        sort($categories);
    }
    
    echo json_encode($categories);
    exit();
}