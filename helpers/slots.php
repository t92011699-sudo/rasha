<?php
/**
 * ===== Shared helpers for computing current_bookings and formatting slots =====
 */

function countBookingsForSlot(int|string $slotId): int
{
    $result = supabaseRequest("bookings?custom_slot_id=eq.$slotId&select=id");
    if ($result['status'] === 200 && is_array($result['data'])) {
        return count($result['data']);
    }
    return 0;
}

/**
 * Formats a slot in the same shape the frontend expects
 */
function formatSlot(array $slot): array
{
    $currentBookings = countBookingsForSlot($slot['id']);
    $from = timeShort($slot['from_time']);
    $to = timeShort($slot['to_time']);

    return array_merge($slot, [
        'current_bookings' => $currentBookings,
        'remaining' => $slot['capacity'] - $currentBookings,
        'available' => $currentBookings < $slot['capacity'],
        'time_range' => "$from - $to",
        'time_display' => "من $from إلى $to",
        'from_time_formatted' => $from,
        'to_time_formatted' => $to,
        'slot_display' => "$from - $to",
    ]);
}

function fetchCustomSlots(int|string $doctorTypeId): array
{
    $result = supabaseRequest("custom_slots?doctor_type_id=eq.$doctorTypeId&select=*");
    if ($result['status'] === 200 && is_array($result['data'])) {
        return $result['data'];
    }
    return [];
}

/**
 * Fetches bookings with relations (department, doctor_type, custom_slots)
 */
function fetchBookingsWithRelations(?string $departmentId = null): array
{
    $query = 'bookings?select=*,department:departments(*),doctor_type:doctor_types(*),custom_slot:custom_slots(*)';
    if ($departmentId !== null) {
        $query .= "&department_id=eq.$departmentId";
    }
    $query .= '&order=created_at.desc';
    
    $result = supabaseRequest($query);
    if ($result['status'] === 200 && is_array($result['data'])) {
        return $result['data'];
    }
    return [];
}