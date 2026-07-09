<?php
// admin.php - لوحة تحكم المشرف
session_start();

// Admin credentials
$admin_email = 'superadmin@gmail.com';
$admin_password = '121314';

// Check if logged in
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    if ($email === $admin_email && $password === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: admin.php');
        exit();
    } else {
        $error = 'بيانات الدخول غير صحيحة';
    }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit();
}

// If not logged in, show login form
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    ?>
    <!DOCTYPE html>
    <html dir="rtl" lang="ar">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>تسجيل الدخول - لوحة التحكم</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <style>
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
            }
            .login-box {
                background: white;
                border-radius: 20px;
                padding: 40px;
                box-shadow: 0 15px 35px rgba(0,0,0,0.2);
                max-width: 400px;
                margin: auto;
            }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h3 class="text-center mb-4"><i class="fas fa-lock"></i> تسجيل الدخول</h3>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">البريد الإلكتروني</label>
                    <input type="email" name="email" class="form-control" value="superadmin@gmail.com" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">كلمة المرور</label>
                    <input type="password" name="password" class="form-control" value="121314" required>
                </div>
                <button type="submit" name="login" class="btn btn-primary w-100">دخول</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Admin dashboard
require_once 'config/database.php';
$db = new Database();
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - عيادة دكتورة راشا</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        .admin-sidebar {
            min-height: 100vh;
            background: #2c3e50;
            padding: 20px;
            color: white;
        }
        .admin-sidebar a {
            color: #ecf0f1;
            text-decoration: none;
            display: block;
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 5px;
            transition: all 0.3s;
        }
        .admin-sidebar a:hover, .admin-sidebar a.active {
            background: #34495e;
        }
        .admin-content {
            padding: 30px;
        }
        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: #667eea;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 admin-sidebar">
                <h4 class="mb-4"><i class="fas fa-stethoscope"></i> لوحة التحكم</h4>
                <a href="#" class="active" data-tab="stats"><i class="fas fa-chart-bar"></i> الإحصائيات</a>
                <a href="#" data-tab="calendar"><i class="fas fa-calendar"></i> التقويم</a>
                <a href="#" data-tab="bookings"><i class="fas fa-list"></i> الحجوزات</a>
                <a href="#" data-tab="departments"><i class="fas fa-building"></i> الأقسام</a>
                <a href="?logout=1" class="text-danger"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 admin-content">
                <!-- Stats Tab -->
                <div id="stats" class="tab-content active">
                    <h3 class="mb-4"><i class="fas fa-chart-bar"></i> الإحصائيات</h3>
                    <div class="row">
                        <?php
                        // Get statistics
                        $totalResult = $db->request('appointments?select=count', 'GET');
                        $totalBookings = isset($totalResult['data'][0]['count']) ? $totalResult['data'][0]['count'] : 0;
                        
                        $today = date('Y-m-d');
                        $todayResult = $db->request("appointments?date=eq.{$today}&select=count", 'GET');
                        $todayBookings = isset($todayResult['data'][0]['count']) ? $todayResult['data'][0]['count'] : 0;
                        
                        $pendingResult = $db->request("appointments?status=eq.pending&select=count", 'GET');
                        $pendingBookings = isset($pendingResult['data'][0]['count']) ? $pendingResult['data'][0]['count'] : 0;
                        ?>
                        <div class="col-md-3">
                            <div class="stats-card text-center">
                                <div class="stats-number"><?= $totalBookings ?></div>
                                <p>إجمالي الحجوزات</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card text-center">
                                <div class="stats-number" style="color: #28a745;"><?= $todayBookings ?></div>
                                <p>حجوزات اليوم</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card text-center">
                                <div class="stats-number" style="color: #ffc107;"><?= $pendingBookings ?></div>
                                <p>قيد الانتظار</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stats-card text-center">
                                <div class="stats-number" style="color: #17a2b8;"><?= date('F Y') ?></div>
                                <p>الشهر الحالي</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Calendar Tab -->
                <div id="calendar" class="tab-content">
                    <h3 class="mb-4"><i class="fas fa-calendar"></i> إدارة التقويم</h3>
                    <div id="adminCalendar" class="calendar-grid"></div>
                    <div id="adminTimeSlots" class="mt-3"></div>
                </div>
                
                <!-- Bookings Tab -->
                <div id="bookings" class="tab-content">
                    <h3 class="mb-4"><i class="fas fa-list"></i> الحجوزات</h3>
                    <div id="bookingsList"></div>
                </div>
                
                <!-- Departments Tab -->
                <div id="departments" class="tab-content">
                    <h3 class="mb-4"><i class="fas fa-building"></i> إدارة الأقسام</h3>
                    <button class="btn btn-primary mb-3" onclick="addDepartment()"><i class="fas fa-plus"></i> إضافة قسم جديد</button>
                    <div id="departmentsList"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab switching
        document.querySelectorAll('[data-tab]').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelectorAll('[data-tab]').forEach(l => l.classList.remove('active'));
                this.classList.add('active');
                
                const tab = this.dataset.tab;
                document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
                document.getElementById(tab).classList.add('active');
                
                if (tab === 'calendar') loadAdminCalendar();
                if (tab === 'bookings') loadAllBookings();
                if (tab === 'departments') loadDepartments();
            });
        });

        // Admin Calendar
        function loadAdminCalendar() {
            // Similar to patient calendar but with admin controls
            const calendar = document.getElementById('adminCalendar');
            // Implementation similar to patient calendar
        }

        // Load all bookings
        function loadAllBookings() {
            fetch('api/admin-appointments.php')
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.text().then(text => text ? JSON.parse(text) : []);
                })
                .then(data => {
                    const container = document.getElementById('bookingsList');
                    container.innerHTML = '<div class="table-responsive"><table class="table table-striped"><thead><tr><th>#</th><th>المريض</th><th>الهاتف</th><th>العمر</th><th>التاريخ</th><th>الوقت</th><th>الحالة</th><th>إجراءات</th></tr></thead><tbody>';
                    
                    data.forEach((booking, index) => {
                        container.innerHTML += `
                            <tr>
                                <td>${index + 1}</td>
                                <td>${booking.name}</td>
                                <td>${booking.phone}</td>
                                <td>${booking.age}</td>
                                <td>${booking.date}</td>
                                <td>${booking.time}</td>
                                <td><span class="badge bg-${booking.status === 'confirmed' ? 'success' : 'warning'}">${booking.status}</span></td>
                                <td>
                                    <button class="btn btn-sm btn-success" onclick="confirmBooking('${booking.id}')"><i class="fas fa-check"></i></button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteBooking('${booking.id}')"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        `;
                    });
                    
                    container.innerHTML += '</tbody></table></div>';
                });
        }

        function confirmBooking(id) {
            if (confirm('تأكيد حجز هذا الموعد؟')) {
                fetch(`api/admin-appointments.php?id=${id}`, {
                    method: 'PUT',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({status: 'confirmed'})
                })
                .then(() => loadAllBookings());
            }
        }

        function deleteBooking(id) {
            if (confirm('هل أنت متأكد من حذف هذا الحجز؟')) {
                fetch(`api/admin-appointments.php?id=${id}`, {
                    method: 'DELETE'
                })
                .then(() => loadAllBookings());
            }
        }

        // Load departments
        function loadDepartments() {
            fetch('api/departments.php')
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.text().then(text => text ? JSON.parse(text) : {});
                })
                .then(data => {
                    const container = document.getElementById('departmentsList');
                    container.innerHTML = '<div class="list-group">';
                    
                    data.forEach(dept => {
                        container.innerHTML += `
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span>${dept.name}</span>
                                <div>
                                    <button class="btn btn-sm btn-warning" onclick="editDepartment('${dept.id}')"><i class="fas fa-edit"></i></button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteDepartment('${dept.id}')"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                        `;
                    });
                    
                    container.innerHTML += '</div>';
                });
        }

        function addDepartment() {
            const name = prompt('أدخل اسم القسم الجديد:');
            if (name) {
                fetch('api/departments.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({name: name})
                })
                .then(() => loadDepartments());
            }
        }

        function editDepartment(id) {
            const name = prompt('أدخل الاسم الجديد للقسم:');
            if (name) {
                fetch(`api/departments.php?id=${id}`, {
                    method: 'PUT',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({name: name})
                })
                .then(() => loadDepartments());
            }
        }

        function deleteDepartment(id) {
            if (confirm('هل أنت متأكد من حذف هذا القسم؟')) {
                fetch(`api/departments.php?id=${id}`, {
                    method: 'DELETE'
                })
                .then(() => loadDepartments());
            }
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
