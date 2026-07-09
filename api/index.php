 <?php
/**
 * Clinic API - PHP version with Supabase
 * Version: 3.0.2
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/http.php';
require_once __DIR__ . '/../helpers/jwt.php';
require_once __DIR__ . '/../helpers/slots.php';

// ===== CORS =====
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$uri = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
if ($uri === '') {
    $uri = '/';
}

// ===== Simple router =====
$routes = [];

function route(array &$routes, string $method, string $pattern, callable $handler): void
{
    $routes[] = [$method, $pattern, $handler];
}

function dispatch(array $routes, string $method, string $uri): void
{
    foreach ($routes as [$routeMethod, $pattern, $handler]) {
        if ($routeMethod !== $method) {
            continue;
        }
        if (preg_match($pattern, $uri, $matches)) {
            $handler($matches);
            return;
        }
    }
    jsonError('المسار غير موجود', 404);
}

// ============================
// 1. Health Check
// ============================

route($routes, 'GET', '#^/$#', function () {
    jsonResponse([
        'message' => '🚀 Clinic API is running! (Supabase)',
        'supabase_connected' => isDbConfigured(),
        'version' => '3.0.2',
    ]);
});

route($routes, 'GET', '#^/api/health$#', function () {
    jsonResponse([
        'status' => 'OK',
        'timestamp' => nowIso(),
        'supabase' => isDbConfigured() ? 'Connected ✅' : 'Missing ❌',
    ]);
});

// ============================
// 2. Admin Login
// ============================

route($routes, 'POST', '#^/api/admin/login$#', function () {
    $body = getJsonBody();
    $email = $body['email'] ?? null;
    $password = $body['password'] ?? null;

    if (!$email || !$password) {
        jsonError('البريد الإلكتروني وكلمة المرور مطلوبان', 400);
    }

    $result = supabaseRequest("admins?email=eq.$email&select=*");
    if ($result['status'] !== 200 || empty($result['data'])) {
        jsonError('بيانات الدخول غير صحيحة', 401);
    }

    $admin = $result['data'][0];

    // Simple password check (in production, use password_hash)
    if ($admin['password'] !== $password) {
        jsonError('بيانات الدخول غير صحيحة', 401);
    }

    $role = $admin['role'] ?? 'admin';
    $token = jwtSign([
        'id' => $admin['id'],
        'email' => $admin['email'],
        'role' => $role,
    ], JWT_SECRET);

    $accountKey = $role === 'admin' ? 'admin' : 'user';

    jsonResponse([
        'success' => true,
        'message' => 'تم تسجيل الدخول بنجاح',
        'token' => $token,
        $accountKey => [
            'id' => $admin['id'],
            'email' => $admin['email'],
            'role' => $role,
        ],
    ]);
});

// ============================
// 3. Departments - GET all
// ============================

route($routes, 'GET', '#^/api/departments$#', function () {
    $deptResult = supabaseRequest('departments?select=*&order=order.asc');
    if ($deptResult['status'] !== 200 || empty($deptResult['data'])) {
        jsonResponse([]);
        return;
    }
    $departments = $deptResult['data'];

    $typesResult = supabaseRequest('doctor_types?select=*&enabled=eq.true');
    $doctorTypes = ($typesResult['status'] === 200 && is_array($typesResult['data'])) ? $typesResult['data'] : [];

    $result = [];
    foreach ($departments as $dept) {
        $types = array_values(array_filter($doctorTypes, fn($dt) => $dt['department_id'] === $dept['id']));
        $formattedTypes = [];
        foreach ($types as $dt) {
            $dt['enabled'] = (bool) $dt['enabled'];
            $slots = fetchCustomSlots($dt['id']);
            $dt['custom_slots'] = array_map(fn($slot) => formatSlot($slot), $slots);
            $formattedTypes[] = $dt;
        }
        $dept['doctor_types'] = $formattedTypes;
        $result[] = $dept;
    }

    jsonResponse($result);
});

// ============================
// 4. Departments - GET by ID
// ============================

route($routes, 'GET', '#^/api/departments/([^/]+)$#', function (array $p) {
    $id = $p[1];

    $deptResult = supabaseRequest("departments?id=eq.$id&select=*");
    if ($deptResult['status'] !== 200 || empty($deptResult['data'])) {
        jsonError('القسم غير موجود', 404);
    }
    $department = $deptResult['data'][0];

    $typesResult = supabaseRequest("doctor_types?department_id=eq.$id&select=*&order=type.asc");
    $doctorTypes = ($typesResult['status'] === 200 && is_array($typesResult['data'])) ? $typesResult['data'] : [];

    $formattedTypes = [];
    foreach ($doctorTypes as $dt) {
        $dt['enabled'] = (bool) $dt['enabled'];
        $slots = fetchCustomSlots($dt['id']);
        $dt['custom_slots'] = array_map(fn($slot) => formatSlot($slot), $slots);
        $formattedTypes[] = $dt;
    }

    $department['doctor_types'] = $formattedTypes;
    jsonResponse($department);
});

// ============================
// 5. Departments - POST (Create)
// ============================

route($routes, 'POST', '#^/api/departments$#', function () {
    $body = getJsonBody();
    $name = $body['name'] ?? null;
    $iconUrl = $body['icon_url'] ?? null;
    $doctorTypes = $body['doctor_types'] ?? null;

    if (!$name) {
        jsonError('اسم القسم مطلوب', 400);
    }

    // Get max order
    $orderResult = supabaseRequest('departments?select=order&order=order.desc&limit=1');
    $nextOrder = 1;
    if ($orderResult['status'] === 200 && !empty($orderResult['data'])) {
        $nextOrder = intval($orderResult['data'][0]['order']) + 1;
    }

    $insertData = [
        'name' => $name,
        'icon_url' => $iconUrl,
        'order' => $nextOrder
    ];

    $result = supabaseRequest('departments', 'POST', $insertData);
    if ($result['status'] !== 201) {
        jsonError('فشل في إضافة القسم: ' . json_encode($result), 500);
    }

    $department = $result['data'][0];
    $addedTypes = [];

    if (is_array($doctorTypes) && count($doctorTypes) > 0) {
        foreach ($doctorTypes as $type) {
            $label = $type['label'] ?? (($type['type'] ?? '') === 'male' ? 'دكتور' : 'دكتورة');
            $enabled = $type['enabled'] ?? true;

            $typeData = [
                'department_id' => $department['id'],
                'type' => $type['type'] ?? 'male',
                'label' => $label,
                'enabled' => $enabled
            ];

            $typeResult = supabaseRequest('doctor_types', 'POST', $typeData);
            if ($typeResult['status'] === 201) {
                $newType = $typeResult['data'][0];
                $newType['enabled'] = (bool) $newType['enabled'];
                $newType['custom_slots'] = [];
                $addedTypes[] = $newType;
            }
        }
    }

    $department['doctor_types'] = $addedTypes;
    jsonResponse($department, 201);
});

// ============================
// 6. Departments - PUT Update
// ============================

route($routes, 'PUT', '#^/api/departments/([^/]+)$#', function (array $p) {
    $id = $p[1];
    $body = getJsonBody();

    $updateData = [];
    if (!empty($body['name'])) {
        $updateData['name'] = $body['name'];
    }
    if (array_key_exists('icon_url', $body)) {
        $updateData['icon_url'] = $body['icon_url'];
    }

    if (empty($updateData)) {
        jsonError('لا توجد بيانات للتحديث', 400);
    }

    $result = supabaseRequest("departments?id=eq.$id", 'PATCH', $updateData);
    if ($result['status'] !== 200) {
        jsonError('فشل في تحديث القسم', 500);
    }

    $department = fetchRowById('departments', $id);
    if (!$department) {
        jsonError('القسم غير موجود', 404);
    }

    jsonResponse($department);
});

// ============================
// 7. Departments - DELETE
// ============================

route($routes, 'DELETE', '#^/api/departments/([^/]+)$#', function (array $p) {
    $id = $p[1];

    $result = supabaseRequest("departments?id=eq.$id", 'DELETE');
    if ($result['status'] === 204 || $result['status'] === 200) {
        jsonResponse(['message' => 'تم حذف القسم بنجاح']);
    } else {
        jsonError('فشل في حذف القسم', 500);
    }
});

// ============================
// 8. Bookings - POST
// ============================

route($routes, 'POST', '#^/api/bookings$#', function () {
    $body = getJsonBody();

    $departmentId = $body['department_id'] ?? null;
    $doctorType = $body['doctor_type'] ?? null;
    $slotId = $body['slot_id'] ?? null;
    $bookingDate = $body['booking_date'] ?? null;
    $bookingTime = $body['booking_time'] ?? null;
    $patientName = $body['patient_name'] ?? null;
    $patientAge = $body['patient_age'] ?? null;
    $patientPhone = $body['patient_phone'] ?? null;
    $patientGender = $body['patient_gender'] ?? null;

    if (!$departmentId || !$doctorType || !$slotId || !$bookingDate || !$patientName || !$patientAge || !$patientPhone || !$patientGender) {
        jsonError('جميع الحقول مطلوبة', 400);
    }

    // Check phone number
    $phoneResult = supabaseRequest("bookings?patient_phone=eq.$patientPhone&select=id,patient_name");
    if ($phoneResult['status'] === 200 && !empty($phoneResult['data'])) {
        jsonResponse([
            'error' => 'رقم التليفون مستخدم بالفعل في حجز آخر',
            'existing_booking' => $phoneResult['data'][0]
        ], 400);
    }

    // Get doctor type
    $typeResult = supabaseRequest("doctor_types?department_id=eq.$departmentId&type=eq.$doctorType&select=id");
    if ($typeResult['status'] !== 200 || empty($typeResult['data'])) {
        jsonError('نوع الطبيب غير موجود', 404);
    }
    $doctorTypeId = $typeResult['data'][0]['id'];

    // Get slot
    $slotResult = supabaseRequest("custom_slots?id=eq.$slotId&doctor_type_id=eq.$doctorTypeId&date=eq.$bookingDate&select=id,capacity,from_time,to_time");
    if ($slotResult['status'] !== 200 || empty($slotResult['data'])) {
        jsonError('الموعد غير موجود', 404);
    }
    $customSlot = $slotResult['data'][0];

    // Check capacity
    $currentCount = countBookingsForSlot($slotId);
    if ($currentCount >= $customSlot['capacity']) {
        jsonResponse([
            'error' => 'الموعد مكتمل، لا توجد أماكن متاحة',
            'capacity' => $customSlot['capacity'],
            'current_bookings' => $currentCount,
            'remaining' => 0,
        ], 400);
    }

    $finalBookingTime = $bookingTime ?: (timeShort($customSlot['from_time']) . ' - ' . timeShort($customSlot['to_time']));

    $bookingData = [
        'department_id' => $departmentId,
        'doctor_type_id' => $doctorTypeId,
        'custom_slot_id' => $slotId,
        'booking_date' => $bookingDate,
        'booking_time' => $finalBookingTime,
        'patient_name' => $patientName,
        'patient_age' => $patientAge,
        'patient_phone' => $patientPhone,
        'patient_gender' => $patientGender
    ];

    $result = supabaseRequest('bookings', 'POST', $bookingData);
    if ($result['status'] !== 201) {
        jsonError('فشل في إنشاء الحجز', 500);
    }

    $booking = $result['data'][0];
    $newCount = countBookingsForSlot($slotId);

    jsonResponse([
        'success' => true,
        'booking' => $booking,
        'capacity' => $customSlot['capacity'],
        'current_bookings' => $newCount,
        'remaining' => $customSlot['capacity'] - $newCount,
    ], 201);
});

// ============================
// 9. Bookings - GET All (Admin)
// ============================

route($routes, 'GET', '#^/api/admin/bookings$#', function () {
    $bookings = fetchBookingsWithRelations();

    $formatted = array_map(function ($row) {
        $slotFrom = timeShort($row['custom_slot']['from_time'] ?? null);
        $slotTo = timeShort($row['custom_slot']['to_time'] ?? null);
        $capacity = (int) ($row['custom_slot']['capacity'] ?? 0);
        $currentBookings = countBookingsForSlot($row['custom_slot_id']);

        return [
            'patient' => [
                'id' => $row['id'],
                'name' => $row['patient_name'],
                'age' => $row['patient_age'],
                'phone' => $row['patient_phone'],
                'gender' => $row['patient_gender'] === 'male' ? 'ذكر' : 'أنثى',
            ],
            'booking' => [
                'id' => $row['id'],
                'date' => $row['booking_date'],
                'booking_time' => $row['booking_time'],
                'slot_range' => ($slotFrom && $slotTo) ? "$slotFrom - $slotTo" : null,
                'slot_from' => $slotFrom,
                'slot_to' => $slotTo,
                'capacity' => $capacity,
                'current_bookings' => $currentBookings,
                'remaining' => $capacity - $currentBookings,
                'is_full' => $currentBookings >= $capacity,
            ],
            'department' => [
                'id' => $row['department']['id'] ?? null,
                'name' => $row['department']['name'] ?? 'غير معروف',
                'icon' => $row['department']['icon_url'] ?? null,
            ],
            'doctor' => [
                'id' => $row['doctor_type']['id'] ?? null,
                'type' => $row['doctor_type']['type'] ?? null,
                'label' => $row['doctor_type']['label'] ?? 'غير معروف',
            ],
            'created_at' => $row['created_at'],
        ];
    }, $bookings);

    jsonResponse($formatted);
});

// ============================
// 10. Bookings - DELETE
// ============================

route($routes, 'DELETE', '#^/api/bookings/([^/]+)$#', function (array $p) {
    $id = $p[1];

    // Get the booking first to know the slot
    $bookingResult = supabaseRequest("bookings?id=eq.$id&select=custom_slot_id");
    if ($bookingResult['status'] !== 200 || empty($bookingResult['data'])) {
        jsonError('الحجز غير موجود', 404);
    }
    $booking = $bookingResult['data'][0];

    $result = supabaseRequest("bookings?id=eq.$id", 'DELETE');
    if ($result['status'] !== 204 && $result['status'] !== 200) {
        jsonError('فشل في إلغاء الحجز', 500);
    }

    $newCount = countBookingsForSlot($booking['custom_slot_id']);
    $slotResult = supabaseRequest("custom_slots?id=eq.{$booking['custom_slot_id']}&select=capacity");
    $capacity = ($slotResult['status'] === 200 && !empty($slotResult['data'])) ? $slotResult['data'][0]['capacity'] : 0;

    jsonResponse([
        'message' => 'تم إلغاء الحجز بنجاح',
        'slot_id' => $booking['custom_slot_id'],
        'capacity' => $capacity,
        'current_bookings' => $newCount,
        'remaining' => $capacity - $newCount,
    ]);
});

// ============================
// Run the router
// ============================

try {
    dispatch($routes, $method, $uri);
} catch (Throwable $e) {
    error_log('❌ Unhandled error: ' . $e->getMessage());
    jsonError('Server error: ' . $e->getMessage(), 500);
}