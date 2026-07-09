 <?php
// api/appointments.php
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

// Handle GET request - get appointments for a date
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $date = isset($_GET['date']) ? $_GET['date'] : '';
    
    if (empty($date)) {
        echo json_encode(['status' => 'error', 'message' => 'Date parameter is required']);
        exit();
    }
    
    $result = $db->request("appointments?date=eq.{$date}&select=*", 'GET');
    
    if ($result['status'] === 200) {
        echo json_encode($result['data']);
    } else {
        echo json_encode([]);
    }
}

// Handle POST request - create new appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['name', 'phone', 'age', 'date', 'time'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            echo json_encode(['status' => 'error', 'message' => "Field {$field} is required"]);
            exit();
        }
    }
    
    // Check if slot is available
    $checkResult = $db->request("appointments?date=eq.{$data['date']}&time=eq.{$data['time']}&select=id", 'GET');
    if ($checkResult['status'] === 200 && count($checkResult['data']) > 0) {
        echo json_encode(['status' => 'error', 'message' => 'This time slot is already booked']);
        exit();
    }
    
    // Create appointment
    $result = $db->request('appointments', 'POST', $data);
    
    if ($result['status'] === 201) {
        echo json_encode(['status' => 'success', 'message' => 'Appointment created successfully', 'data' => $result['data']]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to create appointment']);
    }
}

// Handle DELETE request - cancel appointment (admin)
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = isset($params['id']) ? $params['id'] : '';
    
    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'Appointment ID is required']);
        exit();
    }
    
    $result = $db->request("appointments?id=eq.{$id}", 'DELETE', null, true);
    
    if ($result['status'] === 204) {
        echo json_encode(['status' => 'success', 'message' => 'Appointment deleted successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete appointment']);
    }
}
?>
