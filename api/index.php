<?php
/**
 * Clinic API - PHP version with MySQL
 * Version: 3.2.0
 */

$root = dirname(__DIR__);

require_once $root . '/config/database.php';
require_once $root . '/helpers/http.php';
require_once $root . '/helpers/jwt.php';
require_once $root . '/helpers/slots.php';

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

// ===== Router =====
$routes = [];

function route(array &$routes, string $method, string $pattern, callable $handler): void {
    $routes[] = [$method, $pattern, $handler];
}

function dispatch(array $routes, string $method, string $uri): void {
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
function requireAuth(): array {
    $headers = function_exists('getallheaders') ? getallheaders() : [];
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
        'message' => '🚀 Clinic API is running!',
        'database' => isDbConfigured() ? 'Connected ✅' : 'Missing ❌',
        'version' => '3.2.0',
    ]);
});

route($routes, 'GET', '#^/api/health$#', function () {
    jsonResponse([
        'status' => 'OK',
        'timestamp' => nowIso(),
        'database' => isDbConfigured() ? 'Connected ✅' : 'Missing ❌',
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
        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT * FROM admins WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result->fetch_assoc();
        $stmt->close();
        $conn->close();

        if (!$admin) {
            jsonError('بيانات الدخول غير صحيحة', 401);
        }

        if ($password !== $admin['password']) {
            jsonError('بيانات الدخول غير صحيحة', 401);
        }

        $token = jwtSign([
            'id' => $admin['id'],
            'email' => $admin['email'],
            'role' => $admin['role'] ?? 'admin',
        ], JWT_SECRET);

        jsonResponse([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $admin['id'],
                'email' => $admin['email'],
                'role' => $admin['role'] ?? 'admin',
            ],
        ]);
    } catch (Exception $e) {
        error_log('❌ Login error: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء تسجيل الدخول', 500);
    }
});

route($routes, 'POST', '#^/api/admin/change-password$#', function () {
    $payload = requireAuth();
    $adminId = $payload['id'];
    
    $body = getJsonBody();
    $currentPassword = $body['current_password'] ?? null;
    $newPassword = $body['new_password'] ?? null;

    if (!$currentPassword || !$newPassword) {
        jsonError('كلمة المرور الحالية والجديدة مطلوبتان', 400);
    }

    try {
        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT password FROM admins WHERE id = ?");
        $stmt->bind_param("i", $adminId);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result->fetch_assoc();
        $stmt->close();

        if (!$admin || $currentPassword !== $admin['password']) {
            $conn->close();
            jsonError('كلمة المرور الحالية غير صحيحة', 401);
        }

        $stmt = $conn->prepare("UPDATE admins SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $newPassword, $adminId);
        $stmt->execute();
        $stmt->close();
        $conn->close();

        jsonResponse([
            'success' => true,
            'message' => 'تم تغيير كلمة المرور بنجاح'
        ]);
    } catch (Exception $e) {
        error_log('❌ Change password error: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء تغيير كلمة المرور', 500);
    }
});

route($routes, 'GET', '#^/api/admin/profile$#', function () {
    $payload = requireAuth();
    $adminId = $payload['id'];

    try {
        $conn = getDbConnection();
        $stmt = $conn->prepare("SELECT id, email, role, created_at FROM admins WHERE id = ?");
        $stmt->bind_param("i", $adminId);
        $stmt->execute();
        $result = $stmt->get_result();
        $admin = $result->fetch_assoc();
        $stmt->close();
        $conn->close();

        if (!$admin) {
            jsonError('المسؤول غير موجود', 404);
        }

        jsonResponse([
            'success' => true,
            'user' => $admin
        ]);
    } catch (Exception $e) {
        error_log('❌ Get profile error: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء جلب بيانات الحساب', 500);
    }
});

// ============================
// 2. Departments
// ============================

route($routes, 'GET', '#^/api/departments$#', function () {
    try {
        $departments = dbGet('departments', [], '*');
        
        foreach ($departments as &$dept) {
            $doctorTypes = dbGet('doctor_types', ['department_id' => $dept['id']]);
            foreach ($doctorTypes as &$dt) {
                $dt['enabled'] = (bool) $dt['enabled'];
                $slots = dbGet('custom_slots', ['doctor_type_id' => $dt['id']]);
                $dt['custom_slots'] = array_map('formatSlot', $slots);
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
        $departments = dbGet('departments', ['id' => $id]);
        
        if (empty($departments)) {
            jsonError('القسم غير موجود', 404);
        }
        
        $department = $departments[0];
        $doctorTypes = dbGet('doctor_types', ['department_id' => $id]);
        
        foreach ($doctorTypes as &$dt) {
            $dt['enabled'] = (bool) $dt['enabled'];
            $slots = dbGet('custom_slots', ['doctor_type_id' => $dt['id']]);
            $dt['custom_slots'] = array_map('formatSlot', $slots);
        }
        unset($dt);
        $department['doctor_types'] = $doctorTypes;
        
        jsonResponse($department);
    } catch (Exception $e) {
        error_log('❌ Error fetching department: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء جلب القسم', 500);
    }
});

// ============================
// 3. Departments - POST, PUT, DELETE (تتطلب مصادقة)
// ============================

route($routes, 'POST', '#^/api/departments$#', function () {
    requireAuth();
    
    $body = getJsonBody();
    $name = $body['name'] ?? null;
    $iconUrl = $body['icon_url'] ?? null;

    if (!$name) {
        jsonError('اسم القسم مطلوب', 400);
    }

    try {
        $result = dbInsert('departments', [
            'name' => $name,
            'icon_url' => $iconUrl,
            'order' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        jsonResponse(['success' => true, 'message' => 'تم إضافة القسم', 'id' => $result['id']], 201);
    } catch (Exception $e) {
        error_log('❌ Error creating department: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء إنشاء القسم', 500);
    }
});

route($routes, 'PUT', '#^/api/departments/([^/]+)$#', function (array $p) {
    requireAuth();
    
    $id = $p[1];
    $body = getJsonBody();

    $data = ['updated_at' => date('Y-m-d H:i:s')];
    if (!empty($body['name'])) {
        $data['name'] = $body['name'];
    }
    if (array_key_exists('icon_url', $body)) {
        $data['icon_url'] = $body['icon_url'];
    }

    try {
        dbUpdate('departments', $data, ['id' => $id]);
        jsonResponse(['success' => true, 'message' => 'تم تحديث القسم']);
    } catch (Exception $e) {
        error_log('❌ Error updating department: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء تحديث القسم', 500);
    }
});

route($routes, 'DELETE', '#^/api/departments/([^/]+)$#', function (array $p) {
    requireAuth();
    
    $id = $p[1];

    try {
        dbDelete('departments', ['id' => $id]);
        jsonResponse(['message' => 'تم حذف القسم بنجاح']);
    } catch (Exception $e) {
        error_log('❌ Error deleting department: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء حذف القسم', 500);
    }
});

// ============================
// 4. Price Management
// ============================

route($routes, 'GET', '#^/api/prices$#', function () {
    try {
        $category = $_GET['category'] ?? null;
        $activeOnly = isset($_GET['active_only']) ? filter_var($_GET['active_only'], FILTER_VALIDATE_BOOLEAN) : true;
        
        $filters = [];
        if ($category) {
            $filters['category'] = $category;
        }
        if ($activeOnly) {
            $filters['is_active'] = '1';
        }
        
        $prices = dbGet('prices', $filters, '*');
        jsonResponse($prices);
    } catch (Exception $e) {
        error_log('❌ Error fetching prices: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء جلب الأسعار', 500);
    }
});

route($routes, 'GET', '#^/api/prices/([^/]+)$#', function (array $p) {
    $id = $p[1];
    
    try {
        $prices = dbGet('prices', ['id' => $id]);
        
        if (empty($prices)) {
            jsonError('السعر غير موجود', 404);
        }
        
        jsonResponse($prices[0]);
    } catch (Exception $e) {
        error_log('❌ Error fetching price: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء جلب السعر', 500);
    }
});

route($routes, 'GET', '#^/api/prices/categories$#', function () {
    try {
        $prices = dbGet('prices', ['is_active' => '1'], 'DISTINCT category');
        $categories = array_column($prices, 'category');
        $categories = array_filter($categories);
        sort($categories);
        jsonResponse($categories);
    } catch (Exception $e) {
        error_log('❌ Error fetching categories: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء جلب التصنيفات', 500);
    }
});

route($routes, 'POST', '#^/api/prices$#', function () {
    requireAuth();
    
    $body = getJsonBody();
    
    if (empty(trim($body['label'] ?? ''))) {
        jsonError('الاسم مطلوب', 400);
    }
    if (!isset($body['price']) || $body['price'] === '' || floatval($body['price']) < 0) {
        jsonError('السعر مطلوب وقيمة موجبة', 400);
    }
    
    try {
        $result = dbInsert('prices', [
            'label' => trim($body['label']),
            'price' => floatval($body['price']),
            'description' => $body['description'] ?? null,
            'category' => $body['category'] ?? 'general',
            'icon' => $body['icon'] ?? null,
            'is_active' => isset($body['is_active']) ? (int)$body['is_active'] : 1,
            'display_order' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        jsonResponse([
            'success' => true,
            'message' => 'تم إضافة السعر بنجاح',
            'data' => ['id' => $result['id']]
        ], 201);
    } catch (Exception $e) {
        error_log('❌ Error creating price: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء إضافة السعر', 500);
    }
});

route($routes, 'PUT', '#^/api/prices/([^/]+)$#', function (array $p) {
    requireAuth();
    
    $id = $p[1];
    $body = getJsonBody();
    
    $updateData = ['updated_at' => date('Y-m-d H:i:s')];
    
    if (isset($body['label'])) {
        $updateData['label'] = trim($body['label']);
    }
    if (isset($body['price']) && $body['price'] !== '') {
        $updateData['price'] = floatval($body['price']);
    }
    if (array_key_exists('description', $body)) {
        $updateData['description'] = $body['description'];
    }
    if (array_key_exists('category', $body)) {
        $updateData['category'] = $body['category'];
    }
    if (array_key_exists('icon', $body)) {
        $updateData['icon'] = $body['icon'];
    }
    if (array_key_exists('is_active', $body)) {
        $updateData['is_active'] = (int)$body['is_active'];
    }
    
    try {
        dbUpdate('prices', $updateData, ['id' => $id]);
        jsonResponse([
            'success' => true,
            'message' => 'تم تحديث السعر بنجاح'
        ]);
    } catch (Exception $e) {
        error_log('❌ Error updating price: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء تحديث السعر', 500);
    }
});

route($routes, 'DELETE', '#^/api/prices/([^/]+)$#', function (array $p) {
    requireAuth();
    
    $id = $p[1];
    
    try {
        dbDelete('prices', ['id' => $id]);
        jsonResponse([
            'success' => true,
            'message' => 'تم حذف السعر بنجاح'
        ]);
    } catch (Exception $e) {
        error_log('❌ Error deleting price: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء حذف السعر', 500);
    }
});

// ============================
// 5. Bookings
// ============================

// GET /api/bookings/all - جلب جميع الحجوزات
route($routes, 'GET', '#^/api/bookings/all$#', function () {
    try {
        $bookings = dbGet('bookings', [], '*');
        
        foreach ($bookings as &$booking) {
            $department = dbGet('departments', ['id' => $booking['department_id']]);
            $booking['department_name'] = $department[0]['name'] ?? 'غير معروف';
            
            $doctorType = dbGet('doctor_types', ['id' => $booking['doctor_type_id']]);
            $booking['doctor_label'] = $doctorType[0]['label'] ?? 'غير معروف';
            
            $slot = dbGet('custom_slots', ['id' => $booking['custom_slot_id']]);
            if (!empty($slot)) {
                $booking['slot_from'] = $slot[0]['from_time'] ?? null;
                $booking['slot_to'] = $slot[0]['to_time'] ?? null;
                $booking['slot_capacity'] = $slot[0]['capacity'] ?? 0;
            }
        }
        
        jsonResponse($bookings);
    } catch (Exception $e) {
        error_log('❌ Error fetching bookings: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء جلب الحجوزات', 500);
    }
});

// GET /api/bookings/department/{id} - جلب حجوزات قسم محدد
route($routes, 'GET', '#^/api/bookings/department/([^/]+)$#', function (array $p) {
    $departmentId = $p[1];
    
    try {
        $bookings = dbGet('bookings', ['department_id' => $departmentId], '*');
        jsonResponse($bookings);
    } catch (Exception $e) {
        error_log('❌ Error fetching department bookings: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء جلب حجوزات القسم', 500);
    }
});

// GET /api/admin/bookings - جلب جميع الحجوزات (للأدمن)
route($routes, 'GET', '#^/api/admin/bookings$#', function () {
    requireAuth();
    
    try {
        $bookings = dbGet('bookings', [], '*');
        
        foreach ($bookings as &$booking) {
            $department = dbGet('departments', ['id' => $booking['department_id']]);
            $booking['department_name'] = $department[0]['name'] ?? 'غير معروف';
            
            $doctorType = dbGet('doctor_types', ['id' => $booking['doctor_type_id']]);
            $booking['doctor_label'] = $doctorType[0]['label'] ?? 'غير معروف';
            
            $slot = dbGet('custom_slots', ['id' => $booking['custom_slot_id']]);
            if (!empty($slot)) {
                $booking['slot_from'] = $slot[0]['from_time'] ?? null;
                $booking['slot_to'] = $slot[0]['to_time'] ?? null;
                $booking['slot_capacity'] = $slot[0]['capacity'] ?? 0;
            }
        }
        
        jsonResponse($bookings);
    } catch (Exception $e) {
        error_log('❌ Error fetching admin bookings: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء جلب الحجوزات', 500);
    }
});

// POST /api/bookings - إنشاء حجز جديد
route($routes, 'POST', '#^/api/bookings$#', function () {
    $body = getJsonBody();
    
    $required = ['department_id', 'doctor_type', 'slot_id', 'booking_date', 'patient_name', 'patient_age', 'patient_phone', 'patient_gender'];
    foreach ($required as $field) {
        if (!isset($body[$field]) || empty($body[$field])) {
            jsonError("الحقل {$field} مطلوب", 400);
        }
    }
    
    try {
        $slot = dbGet('custom_slots', ['id' => $body['slot_id']]);
        if (empty($slot)) {
            jsonError('الموعد غير موجود', 404);
        }
        
        $currentBookings = dbGet('bookings', ['custom_slot_id' => $body['slot_id']]);
        $currentCount = count($currentBookings);
        
        if ($currentCount >= $slot[0]['capacity']) {
            jsonError('الموعد مكتمل، لا توجد أماكن متاحة', 400);
        }
        
        $result = dbInsert('bookings', [
            'department_id' => $body['department_id'],
            'doctor_type_id' => $body['doctor_type'],
            'custom_slot_id' => $body['slot_id'],
            'booking_date' => $body['booking_date'],
            'booking_time' => $body['booking_time'] ?? null,
            'patient_name' => $body['patient_name'],
            'patient_age' => $body['patient_age'],
            'patient_phone' => $body['patient_phone'],
            'patient_gender' => $body['patient_gender'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        jsonResponse([
            'success' => true,
            'message' => 'تم إنشاء الحجز بنجاح',
            'booking_id' => $result['id']
        ], 201);
    } catch (Exception $e) {
        error_log('❌ Error creating booking: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء إنشاء الحجز', 500);
    }
});

// DELETE /api/bookings/{id} - إلغاء حجز
route($routes, 'DELETE', '#^/api/bookings/([^/]+)$#', function (array $p) {
    $id = $p[1];
    
    try {
        $booking = dbGet('bookings', ['id' => $id]);
        if (empty($booking)) {
            jsonError('الحجز غير موجود', 404);
        }
        
        dbDelete('bookings', ['id' => $id]);
        jsonResponse(['message' => 'تم إلغاء الحجز بنجاح']);
    } catch (Exception $e) {
        error_log('❌ Error deleting booking: ' . $e->getMessage());
        jsonError('حدث خطأ أثناء إلغاء الحجز', 500);
    }
});

// ============================
// 6. Test Route
// ============================

route($routes, 'GET', '#^/api/test$#', function () {
    jsonResponse([
        'message' => 'API is working!',
        'status' => 'OK',
        'timestamp' => nowIso()
    ]);
});

// ============================
// Helper Functions
// ============================

function formatSlot(array $slot): array {
    $from = date('H:i', strtotime($slot['from_time'] ?? '00:00:00'));
    $to = date('H:i', strtotime($slot['to_time'] ?? '00:00:00'));
    
    return array_merge($slot, [
        'time_range' => "$from - $to",
        'time_display' => "من $from إلى $to",
        'from_time_formatted' => $from,
        'to_time_formatted' => $to,
        'slot_display' => "$from - $to",
    ]);
}

// ============================
// Run the router
// ============================

try {
    dispatch($routes, $method, $uri);
} catch (Throwable $e) {
    error_log('❌ Unhandled error: ' . $e->getMessage());
    jsonError('حدث خطأ في الخادم', 500);
}