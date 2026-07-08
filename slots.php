<?php
// api/slots.php

require_once __DIR__ . '/../config/supabase.php';

header('Content-Type: application/json');

$doctor_type_id = $_GET['doctor_type_id'] ?? null;
$date = $_GET['date'] ?? null;

if (!$doctor_type_id || !$date) {
    jsonResponse(['error' => 'doctor_type_id و date مطلوبان'], 400);
}

$result = supabaseRequest('GET', 'slots', null, [
    'doctor_type_id' => 'eq.' . $doctor_type_id,
    'date' => 'eq.' . $date,
    'order' => 'from_time.asc'
]);

$slots = $result['data'] ?? [];

$slotsWithBookings = array_map(function($slot) {
    $bookingsResult = supabaseRequest('GET', 'bookings', null, [
        'slot_id' => 'eq.' . $slot['id'],
        'select' => 'id'
    ]);
    
    $currentBookings = 0;
    if ($bookingsResult['status'] === 200 && is_array($bookingsResult['data'])) {
        $currentBookings = count($bookingsResult['data']);
    }
    
    return [
        'id' => $slot['id'],
        'date' => $slot['date'],
        'from_time' => $slot['from_time'],
        'to_time' => $slot['to_time'],
        'capacity' => $slot['capacity'],
        'current_bookings' => $currentBookings,
        'remaining' => $slot['capacity'] - $currentBookings,
        'available' => $currentBookings < $slot['capacity'],
        'time_range' => substr($slot['from_time'], 0, 5) . ' - ' . substr($slot['to_time'], 0, 5)
    ];
}, $slots);

jsonResponse($slotsWithBookings);
?>