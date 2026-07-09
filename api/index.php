<?php
/**
 * Clinic API - PHP with Supabase REST API
 * Version: 3.1.0
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/http.php';
require_once __DIR__ . '/../helpers/jwt.php';
require_once __DIR__ . '/../helpers/slots.php';
require_once __DIR__ . '/../helpers/supabase.php';

// ===== CORS =====
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, apikey, Prefer');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$uri = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
if ($uri === '') {
    $uri = '/';
}

// ===== Router =====
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

// ===== Authentication Middleware =====
function requireAuth(): array
{
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    
    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
        jsonError('غير مصرح - التوكن مطلوب', 401);
    }
    
    $token = substr($authHeader, 7);
    $payload = jwtVerify($token, JWT_SECRET);
    
    if (!$payload) {
        jsonError('التوكن غير صالح أو منتهي الصلاحية', 401);
    }
    
    return $payload;
}

// ============================
// Health routes
// ============================

route($routes, 'GET', '#^/$#', function () {
    jsonResponse([
        'message' => '🚀 Clinic API is running with Supabase!',
        'supabase_connected' => isDbConfigured(),
        'version' => '3.1.0',
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
// 1. Admin Login
// ============================

route($routes, 'POST', '#^/api/admin/login$#', function () {
    $body = getJsonBody();
    $email = $body['email'] ?? null;
    $password = $body['password'] ?? null;

    if (!$email || !$password) {
        jsonError('البريد الإلكتروني وكلمة المرور مطلوبان', 400);
    }

    try {
        // استخدام service_key للوصول إلى جدول admins
        $admins = supabaseGet('admins', [
            'email' => 'eq.' . $email,
            'select' => '*'
        ], true);
        
        if (empty($admins)) {
            jsonError('بيانات الدخول غير صحيحة', 401);
        }
        
        $admin = $admins[0];
        
        // في الإنتاج استخدم password_hash و password_verify
        if ($password !== $admin['password']) {
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
    } catch (Exception $e) {
        error_log('❌ Login error: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء تسجيل الدخول', 500);
    }
});

// ============================
// 2. Departments
// ============================

route($routes, 'GET', '#^/api/departments$#', function () {
    try {
        $departments = supabaseGet('departments', [
            'select' => '*',
            'order' => 'order.asc',
        ]);
        
        foreach ($departments as &$dept) {
            $doctorTypes = supabaseGet('doctor_types', [
                'department_id' => 'eq.' . $dept['id'],
                'select' => '*',
                'order' => 'type.asc',
            ]);
            
            foreach ($doctorTypes as &$dt) {
                $dt['enabled'] = (bool) $dt['enabled'];
                $slots = supabaseGet('custom_slots', [
                    'doctor_type_id' => 'eq.' . $dt['id'],
                    'select' => '*',
                    'order' => 'date.asc,from_time.asc',
                ]);
                $dt['custom_slots'] = array_map('formatSlotSupabase', $slots);
            }
            unset($dt);
            $dept['doctor_types'] = $doctorTypes;
        }
        unset($dept);

        jsonResponse($departments);
    } catch (Exception $e) {
        error_log('❌ Error fetching departments: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء جلب الأقسام', 500);
    }
});

route($routes, 'GET', '#^/api/departments/([^/]+)$#', function (array $p) {
    $id = $p[1];
    
    try {
        $departments = supabaseGet('departments', [
            'id' => 'eq.' . $id,
            'select' => '*',
            'limit' => 1,
        ]);
        
        if (empty($departments)) {
            jsonError('القسم غير موجود', 404);
        }
        
        $department = $departments[0];
        $doctorTypes = supabaseGet('doctor_types', [
            'department_id' => 'eq.' . $id,
            'select' => '*',
            'order' => 'type.asc',
        ]);
        
        foreach ($doctorTypes as &$dt) {
            $dt['enabled'] = (bool) $dt['enabled'];
            $slots = supabaseGet('custom_slots', [
                'doctor_type_id' => 'eq.' . $dt['id'],
                'select' => '*',
                'order' => 'date.asc,from_time.asc',
            ]);
            $dt['custom_slots'] = array_map('formatSlotSupabase', $slots);
        }
        unset($dt);
        $department['doctor_types'] = $doctorTypes;
        
        jsonResponse($department);
    } catch (Exception $e) {
        error_log('❌ Error fetching department: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء جلب القسم', 500);
    }
});

route($routes, 'POST', '#^/api/departments$#', function () {
    requireAuth();
    
    $body = getJsonBody();
    $name = $body['name'] ?? null;
    $iconUrl = $body['icon_url'] ?? null;
    $doctorTypes = $body['doctor_types'] ?? null;

    if (!$name) {
        jsonError('اسم القسم مطلوب', 400);
    }

    try {
        $depts = supabaseGet('departments', [
            'select' => 'order',
            'order' => 'order.desc',
            'limit' => 1,
        ]);
        $nextOrder = empty($depts) ? 1 : ($depts[0]['order'] + 1);

        $newDept = supabasePost('departments', [
            'name' => $name,
            'icon_url' => $iconUrl,
            'order' => $nextOrder,
            'created_at' => nowIso(),
            'updated_at' => nowIso(),
        ], true);

        $department = $newDept[0] ?? $newDept;
        $addedTypes = [];
        
        if (is_array($doctorTypes) && count($doctorTypes) > 0) {
            foreach ($doctorTypes as $type) {
                $label = $type['label'] ?? (($type['type'] ?? '') === 'male' ? 'دكتور' : 'دكتورة');
                $enabled = $type['enabled'] ?? true;
                
                $newType = supabasePost('doctor_types', [
                    'department_id' => $department['id'],
                    'type' => $type['type'] ?? null,
                    'label' => $label,
                    'enabled' => $enabled ? 1 : 0,
                    'created_at' => nowIso(),
                    'updated_at' => nowIso(),
                ], true);
                $addedTypes[] = $newType[0] ?? $newType;
            }
        }

        $department['doctor_types'] = $addedTypes;
        jsonResponse($department, 201);
    } catch (Exception $e) {
        error_log('❌ Error creating department: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء إنشاء القسم', 500);
    }
});

route($routes, 'PUT', '#^/api/departments/reorder$#', function () {
    requireAuth();
    
    $body = getJsonBody();
    $orderedIds = $body['ordered_ids'] ?? null;

    if (!is_array($orderedIds)) {
        jsonError('ordered_ids مطلوب كمصفوفة', 400);
    }

    try {
        foreach ($orderedIds as $index => $id) {
            supabasePatch('departments', [
                'order' => $index + 1,
                'updated_at' => nowIso(),
            ], ['id' => 'eq.' . $id], true);
        }
        jsonResponse(['message' => 'تم إعادة ترتيب الأقسام بنجاح']);
    } catch (Exception $e) {
        error_log('❌ Error reordering: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء إعادة الترتيب', 500);
    }
});

route($routes, 'PUT', '#^/api/departments/([^/]+)$#', function (array $p) {
    requireAuth();
    
    $id = $p[1];
    $body = getJsonBody();

    $data = ['updated_at' => nowIso()];
    if (!empty($body['name'])) {
        $data['name'] = $body['name'];
    }
    if (array_key_exists('icon_url', $body)) {
        $data['icon_url'] = $body['icon_url'];
    }

    try {
        supabasePatch('departments', $data, ['id' => 'eq.' . $id], true);
        
        $departments = supabaseGet('departments', [
            'id' => 'eq.' . $id,
            'limit' => 1,
        ]);
        
        if (empty($departments)) {
            jsonError('القسم غير موجود', 404);
        }
        
        jsonResponse($departments[0]);
    } catch (Exception $e) {
        error_log('❌ Error updating department: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء تحديث القسم', 500);
    }
});

route($routes, 'DELETE', '#^/api/departments/([^/]+)$#', function (array $p) {
    requireAuth();
    
    $id = $p[1];

    try {
        supabaseDelete('departments', ['id' => 'eq.' . $id], true);
        jsonResponse(['message' => 'تم حذف القسم بنجاح']);
    } catch (Exception $e) {
        error_log('❌ Error deleting department: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء حذف القسم', 500);
    }
});

// ============================
// 3. Custom Slots
// ============================

route($routes, 'GET', '#^/api/departments/([^/]+)/doctor-types/([^/]+)/custom-slots$#', function (array $p) {
    $departmentId = $p[1];
    $type = $p[2];
    $date = $_GET['date'] ?? null;

    if (!$date) {
        jsonError('التاريخ مطلوب', 400);
    }

    try {
        $doctorTypes = supabaseGet('doctor_types', [
            'department_id' => 'eq.' . $departmentId,
            'type' => 'eq.' . $type,
            'limit' => 1,
        ]);
        
        if (empty($doctorTypes)) {
            jsonError('نوع الطبيب غير موجود', 404);
        }
        
        $doctorType = $doctorTypes[0];
        $slots = supabaseGet('custom_slots', [
            'doctor_type_id' => 'eq.' . $doctorType['id'],
            'date' => 'eq.' . $date,
            'order' => 'from_time.asc',
        ]);

        jsonResponse([
            'doctor_type' => $type,
            'date' => $date,
            'custom_slots' => array_map('formatSlotSupabase', $slots),
        ]);
    } catch (Exception $e) {
        error_log('❌ Error fetching slots: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء جلب المواعيد', 500);
    }
});

route($routes, 'GET', '#^/api/departments/([^/]+)/doctor-types/([^/]+)/slots$#', function (array $p) {
    $departmentId = $p[1];
    $type = $p[2];
    $date = $_GET['date'] ?? null;

    if (!$date) {
        jsonError('التاريخ مطلوب', 400);
    }

    try {
        $doctorTypes = supabaseGet('doctor_types', [
            'department_id' => 'eq.' . $departmentId,
            'type' => 'eq.' . $type,
            'limit' => 1,
        ]);
        
        if (empty($doctorTypes)) {
            jsonError('نوع الطبيب غير موجود', 404);
        }
        
        $doctorType = $doctorTypes[0];
        $slots = supabaseGet('custom_slots', [
            'doctor_type_id' => 'eq.' . $doctorType['id'],
            'date' => 'eq.' . $date,
            'order' => 'from_time.asc',
        ]);

        jsonResponse([
            'doctor_type' => $type,
            'doctor_label' => $doctorType['label'],
            'date' => $date,
            'slots' => array_map('formatSlotSupabase', $slots),
        ]);
    } catch (Exception $e) {
        error_log('❌ Error fetching slots: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء جلب المواعيد', 500);
    }
});

route($routes, 'POST', '#^/api/departments/([^/]+)/doctor-types/([^/]+)/custom-slots$#', function (array $p) {
    requireAuth();
    
    $departmentId = $p[1];
    $type = $p[2];
    $body = getJsonBody();

    $date = $body['date'] ?? null;
    $capacity = $body['capacity'] ?? null;
    $fromTime = $body['from_time'] ?? null;
    $toTime = $body['to_time'] ?? null;

    if (!$date || !$capacity || !$fromTime || !$toTime) {
        jsonError('جميع الحقول مطلوبة', 400);
    }

    try {
        $doctorTypes = supabaseGet('doctor_types', [
            'department_id' => 'eq.' . $departmentId,
            'type' => 'eq.' . $type,
            'limit' => 1,
        ]);
        
        if (empty($doctorTypes)) {
            jsonError('نوع الطبيب غير موجود', 404);
        }
        
        $doctorType = $doctorTypes[0];
        $newSlot = supabasePost('custom_slots', [
            'doctor_type_id' => $doctorType['id'],
            'date' => $date,
            'capacity' => $capacity,
            'from_time' => $fromTime,
            'to_time' => $toTime,
            'created_at' => nowIso(),
            'updated_at' => nowIso(),
        ], true);

        jsonResponse($newSlot[0] ?? $newSlot, 201);
    } catch (Exception $e) {
        error_log('❌ Error creating slot: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء إنشاء الموعد', 500);
    }
});

route($routes, 'PUT', '#^/api/departments/([^/]+)/doctor-types/([^/]+)/custom-slots/([^/]+)$#', function (array $p) {
    requireAuth();
    
    $slotId = $p[3];
    $body = getJsonBody();

    $data = ['updated_at' => nowIso()];
    if (array_key_exists('capacity', $body)) {
        $data['capacity'] = $body['capacity'];
    }
    if (!empty($body['from_time'])) {
        $data['from_time'] = $body['from_time'];
    }
    if (!empty($body['to_time'])) {
        $data['to_time'] = $body['to_time'];
    }

    try {
        supabasePatch('custom_slots', $data, ['id' => 'eq.' . $slotId], true);
        
        $slots = supabaseGet('custom_slots', [
            'id' => 'eq.' . $slotId,
            'limit' => 1,
        ]);
        
        if (empty($slots)) {
            jsonError('الفترة غير موجودة', 404);
        }
        
        $slot = $slots[0];
        $currentBookings = countBookingsForSlotSupabase($slot['id']);

        jsonResponse([
            'message' => 'تم تحديث الفترة بنجاح',
            'slot' => [
                'id' => $slot['id'],
                'date' => $slot['date'],
                'from_time' => $slot['from_time'],
                'to_time' => $slot['to_time'],
                'capacity' => $slot['capacity'],
                'current_bookings' => $currentBookings,
                'remaining' => $slot['capacity'] - $currentBookings,
                'available' => $currentBookings < $slot['capacity'],
            ],
        ]);
    } catch (Exception $e) {
        error_log('❌ Error updating slot: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء تحديث الموعد', 500);
    }
});

route($routes, 'PATCH', '#^/api/departments/([^/]+)/doctor-types/([^/]+)/custom-slots/([^/]+)/capacity$#', function (array $p) {
    requireAuth();
    
    $slotId = $p[3];
    $body = getJsonBody();
    $capacity = $body['capacity'] ?? null;

    if ($capacity === null || $capacity < 0) {
        jsonError('السعة مطلوبة ويجب أن تكون أكبر من أو تساوي 0', 400);
    }

    try {
        $slots = supabaseGet('custom_slots', [
            'id' => 'eq.' . $slotId,
            'limit' => 1,
        ]);
        
        if (empty($slots)) {
            jsonError('الفترة غير موجودة', 404);
        }
        
        $currentSlot = $slots[0];
        $currentBookings = countBookingsForSlotSupabase($slotId);

        if ($capacity < $currentBookings) {
            jsonResponse([
                'error' => "لا يمكن تقليل السعة إلى أقل من عدد الحجوزات الحالية ($currentBookings)",
                'current_bookings' => $currentBookings,
                'requested_capacity' => $capacity,
            ], 400);
        }

        supabasePatch('custom_slots', [
            'capacity' => $capacity,
            'updated_at' => nowIso(),
        ], ['id' => 'eq.' . $slotId], true);
        
        $updatedSlots = supabaseGet('custom_slots', [
            'id' => 'eq.' . $slotId,
            'limit' => 1,
        ]);
        $slot = $updatedSlots[0];

        jsonResponse([
            'message' => 'تم تحديث السعة بنجاح',
            'slot' => [
                'id' => $slot['id'],
                'date' => $slot['date'],
                'from_time' => $slot['from_time'],
                'to_time' => $slot['to_time'],
                'capacity' => $slot['capacity'],
                'current_bookings' => $currentBookings,
                'remaining' => $slot['capacity'] - $currentBookings,
                'available' => $currentBookings < $slot['capacity'],
            ],
        ]);
    } catch (Exception $e) {
        error_log('❌ Error updating capacity: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء تحديث السعة', 500);
    }
});

route($routes, 'DELETE', '#^/api/departments/([^/]+)/doctor-types/([^/]+)/custom-slots/([^/]+)$#', function (array $p) {
    requireAuth();
    
    $slotId = $p[3];

    try {
        supabaseDelete('custom_slots', ['id' => 'eq.' . $slotId], true);
        jsonResponse(['message' => 'تم حذف الفترة المخصصة بنجاح']);
    } catch (Exception $e) {
        error_log('❌ Error deleting slot: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء حذف الموعد', 500);
    }
});

// ============================
// 4. Save changes (Update Department with all related data)
// ============================

route($routes, 'PUT', '#^/api/departments/([^/]+)/save$#', function (array $p) {
    requireAuth();
    
    $id = $p[1];
    $body = getJsonBody();

    $name = $body['name'] ?? null;
    $iconUrl = array_key_exists('icon_url', $body) ? $body['icon_url'] : null;
    $doctorTypes = $body['doctor_types'] ?? null;

    try {
        // تحديث القسم
        $deptData = ['updated_at' => nowIso()];
        if ($name) {
            $deptData['name'] = $name;
        }
        if (array_key_exists('icon_url', $body)) {
            $deptData['icon_url'] = $iconUrl;
        }
        supabasePatch('departments', $deptData, ['id' => 'eq.' . $id], true);

        // تحديث أنواع الأطباء
        if (is_array($doctorTypes)) {
            foreach ($doctorTypes as $typeData) {
                $existingTypes = supabaseGet('doctor_types', [
                    'department_id' => 'eq.' . $id,
                    'type' => 'eq.' . ($typeData['type'] ?? ''),
                    'limit' => 1,
                ]);
                
                if (empty($existingTypes)) {
                    // إنشاء نوع جديد
                    $label = $typeData['label'] ?? (($typeData['type'] ?? '') === 'male' ? 'دكتور' : 'دكتورة');
                    $enabled = $typeData['enabled'] ?? true;
                    
                    $newType = supabasePost('doctor_types', [
                        'department_id' => $id,
                        'type' => $typeData['type'] ?? null,
                        'label' => $label,
                        'enabled' => $enabled ? 1 : 0,
                        'created_at' => nowIso(),
                        'updated_at' => nowIso(),
                    ], true);
                    $doctorType = $newType[0] ?? $newType;
                } else {
                    $doctorType = $existingTypes[0];
                    // تحديث النوع
                    $typeUpdate = ['updated_at' => nowIso()];
                    if (array_key_exists('enabled', $typeData)) {
                        $typeUpdate['enabled'] = $typeData['enabled'] ? 1 : 0;
                    }
                    if (!empty($typeData['label'])) {
                        $typeUpdate['label'] = $typeData['label'];
                    }
                    supabasePatch('doctor_types', $typeUpdate, ['id' => 'eq.' . $doctorType['id']], true);
                }

                // حذف المواعيد القديمة وإنشاء الجديدة
                supabaseDelete('custom_slots', ['doctor_type_id' => 'eq.' . $doctorType['id']], true);
                
                if (!empty($typeData['custom_slots']) && is_array($typeData['custom_slots'])) {
                    foreach ($typeData['custom_slots'] as $slot) {
                        supabasePost('custom_slots', [
                            'doctor_type_id' => $doctorType['id'],
                            'date' => $slot['date'] ?? null,
                            'capacity' => $slot['capacity'] ?? null,
                            'from_time' => $slot['from_time'] ?? null,
                            'to_time' => $slot['to_time'] ?? null,
                            'created_at' => nowIso(),
                            'updated_at' => nowIso(),
                        ], true);
                    }
                }
            }
        }

        // جلب القسم المحدث بالكامل
        $departments = supabaseGet('departments', [
            'id' => 'eq.' . $id,
            'limit' => 1,
        ]);
        $updatedDepartment = $departments[0];
        
        $doctorTypesList = supabaseGet('doctor_types', [
            'department_id' => 'eq.' . $id,
        ]);
        
        $doctorTypesWithBookings = [];
        foreach ($doctorTypesList as $dt) {
            $dt['enabled'] = (bool) $dt['enabled'];
            $slots = supabaseGet('custom_slots', [
                'doctor_type_id' => 'eq.' . $dt['id'],
            ]);
            $dt['custom_slots'] = array_map(function ($slot) {
                $currentBookings = countBookingsForSlotSupabase($slot['id']);
                return array_merge($slot, [
                    'current_bookings' => $currentBookings,
                    'remaining' => $slot['capacity'] - $currentBookings,
                    'available' => $currentBookings < $slot['capacity'],
                ]);
            }, $slots);
            $doctorTypesWithBookings[] = $dt;
        }

        $updatedDepartment['doctor_types'] = $doctorTypesWithBookings;

        jsonResponse([
            'success' => true,
            'department' => $updatedDepartment,
        ]);
    } catch (Exception $e) {
        error_log('❌ Error saving department: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء حفظ القسم', 500);
    }
});

// ============================
// 5. Bookings
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

    if (!in_array($patientGender, ['male', 'female'], true)) {
        jsonError('الجنس يجب أن يكون male أو female', 400);
    }

    try {
        // التحقق من رقم الهاتف
        $existingPhone = supabaseGet('bookings', [
            'patient_phone' => 'eq.' . $patientPhone,
            'limit' => 1,
        ]);
        
        if (!empty($existingPhone)) {
            jsonResponse([
                'error' => 'رقم التليفون مستخدم بالفعل في حجز آخر',
                'existing_booking' => [
                    'id' => $existingPhone[0]['id'],
                    'patient_name' => $existingPhone[0]['patient_name'],
                ],
            ], 400);
        }

        // التحقق من نوع الطبيب
        $doctorTypes = supabaseGet('doctor_types', [
            'department_id' => 'eq.' . $departmentId,
            'type' => 'eq.' . $doctorType,
            'limit' => 1,
        ]);
        
        if (empty($doctorTypes)) {
            jsonError('نوع الطبيب غير موجود', 404);
        }
        
        $doctorTypeRow = $doctorTypes[0];

        // التحقق من الموعد
        $slots = supabaseGet('custom_slots', [
            'id' => 'eq.' . $slotId,
            'doctor_type_id' => 'eq.' . $doctorTypeRow['id'],
            'date' => 'eq.' . $bookingDate,
            'limit' => 1,
        ]);
        
        if (empty($slots)) {
            jsonError('الموعد غير موجود', 404);
        }
        
        $customSlot = $slots[0];
        $currentCount = countBookingsForSlotSupabase($slotId);

        if ($currentCount >= $customSlot['capacity']) {
            jsonResponse([
                'error' => 'الموعد مكتمل، لا توجد أماكن متاحة',
                'capacity' => $customSlot['capacity'],
                'current_bookings' => $currentCount,
                'remaining' => 0,
            ], 400);
        }

        $finalBookingTime = $bookingTime ?: (timeShort($customSlot['from_time']) . ' - ' . timeShort($customSlot['to_time']));

        $newBooking = supabasePost('bookings', [
            'department_id' => $departmentId,
            'doctor_type_id' => $doctorTypeRow['id'],
            'custom_slot_id' => $slotId,
            'booking_date' => $bookingDate,
            'booking_time' => $finalBookingTime,
            'patient_name' => $patientName,
            'patient_age' => $patientAge,
            'patient_phone' => $patientPhone,
            'patient_gender' => $patientGender,
            'created_at' => nowIso(),
            'updated_at' => nowIso(),
        ], true);

        $booking = $newBooking[0] ?? $newBooking;
        $newCount = countBookingsForSlotSupabase($slotId);

        jsonResponse([
            'success' => true,
            'booking' => $booking,
            'capacity' => $customSlot['capacity'],
            'current_bookings' => $newCount,
            'remaining' => $customSlot['capacity'] - $newCount,
        ], 201);
    } catch (Exception $e) {
        error_log('❌ Error creating booking: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء إنشاء الحجز', 500);
    }
});

route($routes, 'GET', '#^/api/bookings/all$#', function () {
    try {
        $bookings = supabaseGet('bookings', [
            'select' => '*',
            'order' => 'created_at.desc',
        ]);
        
        $formatted = array_map(function ($booking) {
            // جلب معلومات القسم ونوع الطبيب والموعد
            $department = supabaseGet('departments', [
                'id' => 'eq.' . $booking['department_id'],
                'limit' => 1,
            ]);
            $doctorType = supabaseGet('doctor_types', [
                'id' => 'eq.' . $booking['doctor_type_id'],
                'limit' => 1,
            ]);
            $slot = supabaseGet('custom_slots', [
                'id' => 'eq.' . $booking['custom_slot_id'],
                'limit' => 1,
            ]);
            
            $currentBookings = $slot ? countBookingsForSlotSupabase($slot[0]['id']) : 0;
            $capacity = $slot ? (int) $slot[0]['capacity'] : 0;
            $from = $slot ? timeShort($slot[0]['from_time']) : null;
            $to = $slot ? timeShort($slot[0]['to_time']) : null;

            return [
                'id' => $booking['id'],
                'patient_name' => $booking['patient_name'],
                'patient_age' => $booking['patient_age'],
                'patient_phone' => $booking['patient_phone'],
                'patient_gender' => $booking['patient_gender'],
                'booking_date' => $booking['booking_date'],
                'booking_time' => $booking['booking_time'],
                'department' => [
                    'id' => $department[0]['id'] ?? null,
                    'name' => $department[0]['name'] ?? 'غير معروف',
                ],
                'doctor' => [
                    'id' => $doctorType[0]['id'] ?? null,
                    'type' => $doctorType[0]['type'] ?? null,
                    'label' => $doctorType[0]['label'] ?? 'غير معروف',
                ],
                'slot' => [
                    'id' => $slot[0]['id'] ?? null,
                    'date' => $slot[0]['date'] ?? null,
                    'from_time' => $from,
                    'to_time' => $to,
                    'capacity' => $capacity,
                    'current_bookings' => $currentBookings,
                    'remaining' => $capacity - $currentBookings,
                ],
                'created_at' => $booking['created_at'],
                'display' => "{$booking['patient_name']} | {$booking['booking_date']} | " .
                    ($department[0]['name'] ?? 'غير معروف') . ' | ' . ($doctorType[0]['label'] ?? 'غير معروف'),
            ];
        }, $bookings);

        jsonResponse($formatted);
    } catch (Exception $e) {
        error_log('❌ Error fetching bookings: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء جلب الحجوزات', 500);
    }
});

route($routes, 'GET', '#^/api/admin/bookings$#', function () {
    requireAuth();
    
    try {
        $bookings = supabaseGet('bookings', [
            'select' => '*',
            'order' => 'created_at.desc',
        ], true);
        
        $formatted = array_map(function ($booking) {
            $department = supabaseGet('departments', [
                'id' => 'eq.' . $booking['department_id'],
                'limit' => 1,
            ]);
            $doctorType = supabaseGet('doctor_types', [
                'id' => 'eq.' . $booking['doctor_type_id'],
                'limit' => 1,
            ]);
            $slot = supabaseGet('custom_slots', [
                'id' => 'eq.' . $booking['custom_slot_id'],
                'limit' => 1,
            ]);
            
            $currentBookings = $slot ? countBookingsForSlotSupabase($slot[0]['id']) : 0;
            $capacity = $slot ? (int) $slot[0]['capacity'] : 0;
            $from = $slot ? timeShort($slot[0]['from_time']) : null;
            $to = $slot ? timeShort($slot[0]['to_time']) : null;

            return [
                'patient' => [
                    'id' => $booking['id'],
                    'name' => $booking['patient_name'],
                    'age' => $booking['patient_age'],
                    'phone' => $booking['patient_phone'],
                    'gender' => $booking['patient_gender'] === 'male' ? 'ذكر' : 'أنثى',
                ],
                'booking' => [
                    'id' => $booking['id'],
                    'date' => $booking['booking_date'],
                    'booking_time' => $booking['booking_time'],
                    'slot_range' => ($from && $to) ? "$from - $to" : null,
                    'slot_from' => $from,
                    'slot_to' => $to,
                    'capacity' => $capacity,
                    'current_bookings' => $currentBookings,
                    'remaining' => $capacity - $currentBookings,
                    'is_full' => $currentBookings >= $capacity,
                ],
                'department' => [
                    'id' => $department[0]['id'] ?? null,
                    'name' => $department[0]['name'] ?? 'غير معروف',
                    'icon' => $department[0]['icon_url'] ?? null,
                ],
                'doctor' => [
                    'id' => $doctorType[0]['id'] ?? null,
                    'type' => $doctorType[0]['type'] ?? null,
                    'label' => $doctorType[0]['label'] ?? 'غير معروف',
                ],
                'created_at' => $booking['created_at'],
                'display' => "{$booking['patient_name']} | {$booking['booking_date']} | " .
                    ($department[0]['name'] ?? 'غير معروف') . ' | ' . ($doctorType[0]['label'] ?? 'غير معروف') .
                    " | $from - $to | $currentBookings/$capacity",
            ];
        }, $bookings);

        jsonResponse($formatted);
    } catch (Exception $e) {
        error_log('❌ Error fetching admin bookings: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء جلب الحجوزات', 500);
    }
});

route($routes, 'GET', '#^/api/bookings/department/([^/]+)$#', function (array $p) {
    $departmentId = $p[1];
    
    try {
        $bookings = supabaseGet('bookings', [
            'department_id' => 'eq.' . $departmentId,
            'order' => 'created_at.desc',
        ]);
        
        $formatted = array_map(function ($booking) {
            $doctorType = supabaseGet('doctor_types', [
                'id' => 'eq.' . $booking['doctor_type_id'],
                'limit' => 1,
            ]);
            $slot = supabaseGet('custom_slots', [
                'id' => 'eq.' . $booking['custom_slot_id'],
                'limit' => 1,
            ]);
            
            $currentBookings = $slot ? countBookingsForSlotSupabase($slot[0]['id']) : 0;
            $capacity = $slot ? (int) $slot[0]['capacity'] : 0;
            $from = $slot ? timeShort($slot[0]['from_time']) : null;
            $to = $slot ? timeShort($slot[0]['to_time']) : null;
            $timeLabel = $booking['booking_time'] ?: "$from - $to";

            return [
                'id' => $booking['id'],
                'patient_name' => $booking['patient_name'],
                'patient_age' => $booking['patient_age'],
                'patient_phone' => $booking['patient_phone'],
                'patient_gender' => $booking['patient_gender'],
                'booking_date' => $booking['booking_date'],
                'booking_time' => $booking['booking_time'],
                'department' => [
                    'id' => $booking['department_id'],
                    'name' => null, // سيتم ملؤه من البيانات
                ],
                'doctor' => [
                    'id' => $doctorType[0]['id'] ?? null,
                    'type' => $doctorType[0]['type'] ?? null,
                    'label' => $doctorType[0]['label'] ?? 'غير معروف',
                ],
                'slot' => [
                    'id' => $slot[0]['id'] ?? null,
                    'from_time' => $from,
                    'to_time' => $to,
                    'capacity' => $capacity,
                    'current_bookings' => $currentBookings,
                    'remaining' => $capacity - $currentBookings,
                ],
                'created_at' => $booking['created_at'],
                'summary' => "حجز {$booking['patient_name']} في {$booking['booking_date']} الفترة $timeLabel ($currentBookings/$capacity)",
            ];
        }, $bookings);

        jsonResponse($formatted);
    } catch (Exception $e) {
        error_log('❌ Error fetching department bookings: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء جلب حجوزات القسم', 500);
    }
});

route($routes, 'DELETE', '#^/api/bookings/([^/]+)$#', function (array $p) {
    $id = $p[1];

    try {
        $bookings = supabaseGet('bookings', [
            'id' => 'eq.' . $id,
            'limit' => 1,
        ]);
        
        if (empty($bookings)) {
            jsonError('الحجز غير موجود', 404);
        }
        
        $booking = $bookings[0];
        $slotId = $booking['custom_slot_id'];

        // جلب معلومات الموعد قبل الحذف
        $slots = supabaseGet('custom_slots', [
            'id' => 'eq.' . $slotId,
            'limit' => 1,
        ]);
        $customSlot = $slots[0] ?? null;

        supabaseDelete('bookings', ['id' => 'eq.' . $id], true);

        $capacityInfo = [];
        if ($customSlot) {
            $newCurrentBookings = countBookingsForSlotSupabase($slotId);
            $capacityInfo = [
                'slot_id' => $customSlot['id'],
                'capacity' => $customSlot['capacity'],
                'current_bookings' => $newCurrentBookings,
                'remaining' => $customSlot['capacity'] - $newCurrentBookings,
            ];
        }

        jsonResponse(array_merge(['message' => 'تم إلغاء الحجز بنجاح'], $capacityInfo));
    } catch (Exception $e) {
        error_log('❌ Error deleting booking: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء إلغاء الحجز', 500);
    }
});

// ============================
// Helper Functions
// ============================

function formatSlotSupabase(array $slot): array
{
    $currentBookings = countBookingsForSlotSupabase($slot['id']);
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

function countBookingsForSlotSupabase(int|string $slotId): int
{
    try {
        $bookings = supabaseGet('bookings', [
            'custom_slot_id' => 'eq.' . $slotId,
            'select' => 'id',
        ]);
        return count($bookings);
    } catch (Exception $e) {
        error_log('❌ Error counting bookings: ' . $e->getMessage());
        return 0;
    }
}

// ============================
// Run the router
// ============================

try {
    dispatch($routes, $method, $uri);
} catch (Throwable $e) {
    error_log('❌ Unhandled error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    jsonError('حدث خطأ في الخادم', 500);
}