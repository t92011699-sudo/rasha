<?php
// api/departments.php

require_once __DIR__ . '/../config/supabase.php';

header('Content-Type: application/json');

$id = $_GET['id'] ?? null;

if ($id) {
    $result = supabaseRequest('GET', 'departments?id=eq.' . $id . '&select=*,doctor_types(*)');
    
    if ($result['status'] !== 200 || empty($result['data'])) {
        jsonResponse(['error' => 'القسم غير موجود'], 404);
    }
    
    $department = $result['data'][0];
    $department['doctor_types'] = $result['data'][0]['doctor_types'] ?? [];
    jsonResponse($department);
} else {
    $result = supabaseRequest('GET', 'departments?select=*&order=order.asc');
    jsonResponse($result['data'] ?? []);
}
?>