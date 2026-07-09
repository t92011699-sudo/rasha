 <?php
/**
 * ===== Shared helpers for slots =====
 */

function timeShort(?string $time): ?string
{
    if ($time === null) {
        return null;
    }
    return substr($time, 0, 5);
}

// دوال أخرى مستخدمة من index.php
function fetchCustomSlots($doctorTypeId): array
{
    try {
        return supabaseGet('custom_slots', [
            'doctor_type_id' => 'eq.' . $doctorTypeId,
            'order' => 'date.asc,from_time.asc',
        ]);
    } catch (Exception $e) {
        error_log('❌ Error fetching custom slots: ' . $e->getMessage());
        return [];
    }
}