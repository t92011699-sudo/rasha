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

// Mock departments data with the requested structure
$departments = [
    "4266f3c3-27de-4353-b505-4035258c848b" => [
        "name" => "العلاج الطبيعي",
        "icon_url" => "https://example.com/icons/physio.svg",
        "doctor_types" => [
            [
                "type" => "male",
                "label" => "دكتور",
                "enabled" => true,
                "custom_slots" => [
                    ["id" => 20, "date" => "2026-07-10", "from_time" => "09:00:00", "to_time" => "10:00:00", "capacity" => 3],
                    ["id" => 21, "date" => "2026-07-10", "from_time" => "10:00:00", "to_time" => "11:00:00", "capacity" => 3],
                    ["id" => 22, "date" => "2026-07-10", "from_time" => "11:00:00", "to_time" => "12:00:00", "capacity" => 2],
                    ["id" => 23, "date" => "2026-07-10", "from_time" => "14:00:00", "to_time" => "15:00:00", "capacity" => 2],
                    ["id" => 24, "date" => "2026-07-10", "from_time" => "15:00:00", "to_time" => "16:00:00", "capacity" => 3]
                ]
            ],
            [
                "type" => "female",
                "label" => "دكتورة",
                "enabled" => true,
                "custom_slots" => [
                    ["id" => 25, "date" => "2026-07-10", "from_time" => "09:00:00", "to_time" => "10:00:00", "capacity" => 2],
                    ["id" => 26, "date" => "2026-07-10", "from_time" => "10:00:00", "to_time" => "11:00:00", "capacity" => 2],
                    ["id" => 27, "date" => "2026-07-10", "from_time" => "15:00:00", "to_time" => "16:00:00", "capacity" => 3]
                ]
            ]
        ]
    ],
    "5377g4d4-38ef-5464-c616-5146369d959c" => [
        "name" => "طب الأسنان",
        "icon_url" => "https://example.com/icons/dental.svg",
        "doctor_types" => [
            [
                "type" => "male",
                "label" => "دكتور",
                "enabled" => true,
                "custom_slots" => [
                    ["id" => 30, "date" => "2026-07-10", "from_time" => "08:00:00", "to_time" => "09:00:00", "capacity" => 2],
                    ["id" => 31, "date" => "2026-07-10", "from_time" => "09:00:00", "to_time" => "10:00:00", "capacity" => 2]
                ]
            ]
        ]
    ]
];

// Handle GET request - get department by ID or all departments
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $department_id = isset($_GET['id']) ? $_GET['id'] : null;
    
    if ($department_id) {
        // Return specific department
        if (isset($departments[$department_id])) {
            echo json_encode($departments[$department_id]);
        } else {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Department not found']);
        }
    } else {
        // Return all departments
        echo json_encode($departments);
    }
    exit();
}

// Handle POST request - create new department
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['name']) || !isset($data['doctor_types'])) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        exit();
    }
    
    // Generate new ID
    $new_id = bin2hex(random_bytes(8)) . '-' . bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(4)) . '-' . bin2hex(random_bytes(12));
    
    $departments[$new_id] = [
        'name' => $data['name'],
        'icon_url' => $data['icon_url'] ?? 'https://example.com/icons/default.svg',
        'doctor_types' => $data['doctor_types']
    ];
    
    echo json_encode(['status' => 'success', 'id' => $new_id, 'data' => $departments[$new_id]]);
    exit();
}

// Handle PUT request - update department
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = isset($params['id']) ? $params['id'] : '';
    
    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'Department ID is required']);
        exit();
    }
    
    if (!isset($departments[$id])) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Department not found']);
        exit();
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (isset($data['name'])) {
        $departments[$id]['name'] = $data['name'];
    }
    if (isset($data['icon_url'])) {
        $departments[$id]['icon_url'] = $data['icon_url'];
    }
    if (isset($data['doctor_types'])) {
        $departments[$id]['doctor_types'] = $data['doctor_types'];
    }
    
    echo json_encode(['status' => 'success', 'message' => 'Department updated successfully', 'data' => $departments[$id]]);
    exit();
}

// Handle DELETE request - delete department
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = isset($params['id']) ? $params['id'] : '';
    
    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'Department ID is required']);
        exit();
    }
    
    if (!isset($departments[$id])) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Department not found']);
        exit();
    }
    
    unset($departments[$id]);
    
    echo json_encode(['status' => 'success', 'message' => 'Department deleted successfully']);
    exit();
}
?>