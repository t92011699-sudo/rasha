<?php
// api/departments.php
require_once '../config/database.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$db = new Database();

// Get all departments
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $result = $db->request('departments?select=*', 'GET', null, true);
    
    if ($result['status'] === 200) {
        echo json_encode($result['data']);
    } else {
        echo json_encode([]);
    }
}

// Create department
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($data['name']) || empty($data['name'])) {
        echo json_encode(['status' => 'error', 'message' => 'Department name is required']);
        exit();
    }
    
    $result = $db->request('departments', 'POST', $data, true);
    
    if ($result['status'] === 201) {
        echo json_encode(['status' => 'success', 'message' => 'Department created successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to create department']);
    }
}

// Update department
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = isset($params['id']) ? $params['id'] : '';
    
    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'Department ID is required']);
        exit();
    }
    
    $data = json_decode(file_get_contents('php://input'), true);
    $result = $db->request("departments?id=eq.{$id}", 'PATCH', $data, true);
    
    if ($result['status'] === 200) {
        echo json_encode(['status' => 'success', 'message' => 'Department updated successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update department']);
    }
}

// Delete department
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    parse_str($_SERVER['QUERY_STRING'], $params);
    $id = isset($params['id']) ? $params['id'] : '';
    
    if (empty($id)) {
        echo json_encode(['status' => 'error', 'message' => 'Department ID is required']);
        exit();
    }
    
    $result = $db->request("departments?id=eq.{$id}", 'DELETE', null, true);
    
    if ($result['status'] === 204) {
        echo json_encode(['status' => 'success', 'message' => 'Department deleted successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to delete department']);
    }
}
?>