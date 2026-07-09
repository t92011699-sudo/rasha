<?php
// api/admin-appointments.php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$db = new Database();

// Get all appointments
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = $db->request('appointments?select=*&order=date.asc,time.asc', 'GET', null, true);
    
    if ($result['status'] === 200 && $result['data'] !== null) {
        echo json_encode($result['data']);
    } else {
        echo json_encode([]);
    }
}

// Update appointment status
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = isset($params['id']) ? $params['id'] : '';
    
    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'Appointment ID is required']);
        exit();
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $result = $db->request("appointments?id=eq.{$id}", 'PATCH', $data, true);
    
    if ($result['status'] === 200) {
        echo json_encode(['status' => 'success', 'message' => 'Appointment updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update appointment']);
    }
}

// Delete appointment
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
