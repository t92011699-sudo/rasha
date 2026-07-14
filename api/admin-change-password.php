<?php
// api/admin-change-password.php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/http.php';
require_once __DIR__ . '/../helpers/supabase.php';

header('Content-Type: application/json');

// Check if admin is logged in via session
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    jsonError('غير مصرح', 401);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = getJsonBody();
    $currentPassword = $body['current_password'] ?? null;
    $newPassword = $body['new_password'] ?? null;

    if (!$currentPassword || !$newPassword) {
        jsonError('كلمة المرور الحالية والجديدة مطلوبتان', 400);
    }

    try {
        // Since admin.php uses a hardcoded email for the dashboard, 
        // we'll target the 'superadmin@gmail.com' or the one in session if we had it.
        // Based on admin.php line 6: $admin_email = 'superadmin@gmail.com';
        $adminEmail = 'superadmin@gmail.com';

        $admins = supabaseGet('admins', [
            'email' => 'eq.' . $adminEmail,
            'select' => '*'
        ], true);
        
        if (empty($admins)) {
            jsonError('المسؤول غير موجود', 404);
        }
        
        $admin = $admins[0];
        
        if ($currentPassword !== $admin['password']) {
            jsonError('كلمة المرور الحالية غير صحيحة', 401);
        }

        // Update Password
        supabasePatch('admins', [
            'password' => $newPassword
        ], [
            'id' => 'eq.' . $admin['id']
        ], true);

        jsonResponse([
            'success' => true,
            'message' => 'تم تغيير كلمة المرور بنجاح'
        ]);
    } catch (Exception $e) {
        jsonError('حدث خطأ أثناء تغيير كلمة المرور: ' . $e->getMessage(), 500);
    }
}
