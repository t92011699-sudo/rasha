 <?php
// api/bookings.php
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

// Mock bookings storage (in production, this would be a database)
$bookings = [];

// Handle GET request - get bookings
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $department_id = isset($_GET['department_id']) ? $_GET['department_id'] : null;
    $date = isset($_GET['date']) ? $_GET['date'] : null;
    
    if ($department_id && $date) {
        // Filter bookings by department and date
        $filtered = array_filter($bookings, function($booking) use ($department_id, $date) {
            return $booking['department_id'] === $department_id && $booking['booking_date'] === $date;
        });
        echo json_encode(array_values($filtered));
    } else {
        // Return all bookings
        echo json_encode($bookings);
    }
    exit();
}

// Handle POST request - create new booking
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['department_id', 'doctor_type', 'slot_id', 'booking_date', 'booking_time', 'patient_name', 'patient_age', 'patient_phone', 'patient_gender'];
    foreach ($required as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => "Field {$field} is required"]);
            exit();
        }
    }
    
    // Generate booking ID
    $booking_id = bin2hex(random_bytes(8));
    
    // Create booking object
    $booking = [
        'id' => $booking_id,
        'department_id' => $data['department_id'],
        'doctor_type' => $data['doctor_type'],
        'slot_id' => $data['slot_id'],
        'booking_date' => $data['booking_date'],
        'booking_time' => $data['booking_time'],
        'patient_name' => $data['patient_name'],
        'patient_age' => (int)$data['patient_age'],
        'patient_phone' => $data['patient_phone'],
        'patient_gender' => $data['patient_gender'],
        'status' => 'pending',
        'created_at' => date('Y-m-d\TH:i:s.000\Z'),
        'updated_at' => date('Y-m-d\TH:i:s.000\Z')
    ];
    
    // Add to bookings (in production, save to database)
    $bookings[] = $booking;
    
    // Also save to database via Supabase
    $db_result = $db->request('appointments', 'POST', [
        'name' => $data['patient_name'],
        'phone' => $data['patient_phone'],
        'age' => $data['patient_age'],
        'date' => $data['booking_date'],
        'time' => $data['booking_time'],
        'status' => 'pending',
        'department_id' => $data['department_id'],
        'doctor_type' => $data['doctor_type'],
        'slot_id' => $data['slot_id'],
        'patient_gender' => $data['patient_gender']
    ]);
    
    http_response_code(201);
    echo json_encode([
        'status' => 'success',
        'message' => 'Booking created successfully',
        'data' => $booking
    ]);
    exit();
}

// Handle PUT request - update booking status
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = isset($params['id']) ? $params['id'] : '';
    
    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'Booking ID is required']);
        exit();
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Find and update booking
    $found = false;
    foreach ($bookings as &$booking) {
        if ($booking['id'] === $id) {
            if (isset($data['status'])) {
                $booking['status'] = $data['status'];
            }
            $booking['updated_at'] = date('Y-m-d\TH:i:s.000\Z');
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Booking not found']);
        exit();
    }
    
    echo json_encode(['status' => 'success', 'message' => 'Booking updated successfully']);
    exit();
}

// Handle DELETE request - cancel booking
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = isset($params['id']) ? $params['id'] : '';
    
    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'Booking ID is required']);
        exit();
    }
    
    // Find and delete booking
    $found = false;
    foreach ($bookings as $key => $booking) {
        if ($booking['id'] === $id) {
            unset($bookings[$key]);
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Booking not found']);
        exit();
    }
    
    echo json_encode(['status' => 'success', 'message' => 'Booking cancelled successfully']);
    exit();
}
?>