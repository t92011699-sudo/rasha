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
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/supabase.php';
require_once __DIR__ . '/../helpers/http.php';
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
                <a href="#" data-tab="prices"><i class="fas fa-dollar-sign"></i> الأسعار</a>
                <a href="#" data-tab="settings"><i class="fas fa-cog"></i> الإعدادات</a>
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

                <!-- Prices Tab -->
                <div id="prices" class="tab-content">
                    <h3 class="mb-4"><i class="fas fa-dollar-sign"></i> إدارة الأسعار</h3>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <button class="btn btn-success" onclick="showAddPriceModal()">
                                <i class="fas fa-plus"></i> إضافة سعر جديد
                            </button>
                        </div>
                        <div class="col-md-4">
                            <select id="priceCategoryFilter" class="form-select" onchange="loadPrices()">
                                <option value="">جميع التصنيفات</option>
                            </select>
                        </div>
                        <div class="col-md-4 text-end">
                            <button class="btn btn-outline-secondary" onclick="loadPrices()">
                                <i class="fas fa-sync"></i> تحديث
                            </button>
                        </div>
                    </div>
                    
                    <div id="pricesList"></div>
                </div>

                <!-- Settings Tab -->
                <div id="settings" class="tab-content">
                    <h3 class="mb-4"><i class="fas fa-cog"></i> الإعدادات</h3>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="stats-card">
                                <h5>تغيير كلمة المرور</h5>
                                <hr>
                                <form id="changePasswordForm">
                                    <div class="mb-3">
                                        <label class="form-label">كلمة المرور الحالية</label>
                                        <input type="password" id="currentPassword" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">كلمة المرور الجديدة</label>
                                        <input type="password" id="newPassword" class="form-control" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">تأكيد كلمة المرور الجديدة</label>
                                        <input type="password" id="confirmPassword" class="form-control" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary">تحديث كلمة المرور</button>
                                </form>
                                <div id="passwordMessage" class="mt-3"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Price Modal -->
    <div class="modal fade" id="priceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="priceModalTitle">إضافة سعر جديد</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="priceForm">
                        <input type="hidden" id="priceId">
                        <div class="mb-3">
                            <label class="form-label">الاسم <span class="text-danger">*</span></label>
                            <input type="text" id="priceLabel" class="form-control" required placeholder="مثال: كشف، سونار، أشعة مقطعية">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">السعر (جنيه) <span class="text-danger">*</span></label>
                            <input type="number" id="priceAmount" class="form-control" step="0.01" min="0" required placeholder="0.00">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">التصنيف</label>
                            <input type="text" id="priceCategory" class="form-control" placeholder="مثال: consultation, radiology, laboratory">
                            <small class="text-muted">يمكنك إدخال أي تصنيف جديد وسيظهر تلقائياً في الفلتر</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">أيقونة (Font Awesome)</label>
                            <input type="text" id="priceIcon" class="form-control" placeholder="مثال: fa-stethoscope, fa-microscope">
                            <small class="text-muted">اختياري - لعرض أيقونة بجانب السعر</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">وصف</label>
                            <textarea id="priceDescription" class="form-control" rows="2" placeholder="وصف اختياري للخدمة"></textarea>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" id="priceActive" class="form-check-input" checked>
                            <label class="form-check-label">مفعل (ظهر في القائمة)</label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="button" class="btn btn-primary" onclick="savePrice()">حفظ</button>
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
                if (tab === 'prices') loadPrices();
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

        // ===== Price Management Functions =====
        
        function loadPrices() {
            const category = document.getElementById('priceCategoryFilter').value;
            let url = 'api/prices.php';
            const params = new URLSearchParams();
            if (category) params.append('category', category);
            params.append('active_only', 'false');
            if (params.toString()) url += '?' + params.toString();
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    const container = document.getElementById('pricesList');
                    if (!data || !data.length) {
                        container.innerHTML = `
                            <div class="alert alert-info text-center">
                                <i class="fas fa-info-circle"></i> لا توجد أسعار مسجلة
                                <br><button class="btn btn-sm btn-success mt-2" onclick="showAddPriceModal()">
                                    <i class="fas fa-plus"></i> أضف أول سعر
                                </button>
                            </div>
                        `;
                        return;
                    }
                    
                    let html = `
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>الاسم</th>
                                        <th>السعر</th>
                                        <th>التصنيف</th>
                                        <th>الأيقونة</th>
                                        <th>الحالة</th>
                                        <th>الترتيب</th>
                                        <th>إجراءات</th>
                                    </tr>
                                </thead>
                                <tbody>
                    `;
                    
                    data.forEach((price, index) => {
                        const isActive = price.is_active !== false;
                        html += `
                            <tr>
                                <td>${index + 1}</td>
                                <td>
                                    ${price.icon ? `<i class="fas ${price.icon}"></i> ` : ''}
                                    <strong>${price.label}</strong>
                                    ${price.description ? `<br><small class="text-muted">${price.description}</small>` : ''}
                                </td>
                                <td><strong class="text-success">${Number(price.price).toFixed(2)} ج.م</strong></td>
                                <td><span class="badge bg-info">${price.category || 'عام'}</span></td>
                                <td>${price.icon ? `<i class="fas ${price.icon} fa-lg"></i>` : '-'}</td>
                                <td>
                                    <span class="badge bg-${isActive ? 'success' : 'danger'}">
                                        ${isActive ? 'مفعل' : 'غير مفعل'}
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-secondary" onclick="movePrice(${price.id}, 'up')">
                                            <i class="fas fa-arrow-up"></i>
                                        </button>
                                        <button class="btn btn-outline-secondary" onclick="movePrice(${price.id}, 'down')">
                                            <i class="fas fa-arrow-down"></i>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick="editPrice(${price.id})">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deletePrice(${price.id}, '${price.label}')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    
                    html += '</tbody></table></div>';
                    container.innerHTML = html;
                    
                    updateCategoryFilter(data);
                })
                .catch(error => {
                    console.error('Error loading prices:', error);
                    document.getElementById('pricesList').innerHTML = 
                        '<div class="alert alert-danger">حدث خطأ في تحميل الأسعار</div>';
                });
        }

        function updateCategoryFilter(data) {
            const filter = document.getElementById('priceCategoryFilter');
            const categories = [...new Set(data.map(p => p.category).filter(c => c && c.trim()))];
            const currentValue = filter.value;
            
            const existingOptions = {};
            filter.querySelectorAll('option').forEach(opt => {
                if (opt.value) existingOptions[opt.value] = true;
            });
            
            categories.forEach(cat => {
                if (!existingOptions[cat]) {
                    const option = document.createElement('option');
                    option.value = cat;
                    option.textContent = cat;
                    filter.appendChild(option);
                }
            });
            
            filter.value = currentValue;
        }

        function showAddPriceModal() {
            document.getElementById('priceModalTitle').textContent = 'إضافة سعر جديد';
            document.getElementById('priceForm').reset();
            document.getElementById('priceId').value = '';
            document.getElementById('priceActive').checked = true;
            new bootstrap.Modal(document.getElementById('priceModal')).show();
        }

        function editPrice(id) {
            fetch(`api/prices.php?id=${id}`)
                .then(response => response.json())
                .then(data => {
                    if (!data || !data.length) {
                        alert('السعر غير موجود');
                        return;
                    }
                    const price = data[0];
                    document.getElementById('priceModalTitle').textContent = 'تعديل السعر';
                    document.getElementById('priceId').value = price.id;
                    document.getElementById('priceLabel').value = price.label;
                    document.getElementById('priceAmount').value = price.price;
                    document.getElementById('priceCategory').value = price.category || '';
                    document.getElementById('priceIcon').value = price.icon || '';
                    document.getElementById('priceDescription').value = price.description || '';
                    document.getElementById('priceActive').checked = price.is_active !== false;
                    new bootstrap.Modal(document.getElementById('priceModal')).show();
                })
                .catch(error => {
                    console.error('Error fetching price:', error);
                    alert('حدث خطأ في جلب بيانات السعر');
                });
        }

        function savePrice() {
            const id = document.getElementById('priceId').value;
            const label = document.getElementById('priceLabel').value.trim();
            const price = document.getElementById('priceAmount').value;
            const category = document.getElementById('priceCategory').value.trim();
            const icon = document.getElementById('priceIcon').value.trim();
            const description = document.getElementById('priceDescription').value.trim();
            const isActive = document.getElementById('priceActive').checked;
            
            if (!label) {
                alert('الاسم مطلوب');
                document.getElementById('priceLabel').focus();
                return;
            }
            if (!price || parseFloat(price) < 0) {
                alert('السعر مطلوب وقيمة موجبة');
                document.getElementById('priceAmount').focus();
                return;
            }
            
            const data = {
                label: label,
                price: parseFloat(price),
                category: category || 'general',
                icon: icon || null,
                description: description || null,
                is_active: isActive
            };
            
            let url = 'api/prices.php';
            let method = 'POST';
            
            if (id) {
                url += `?id=${id}`;
                method = 'PUT';
            }
            
            fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.status === 'success' || result.success) {
                    bootstrap.Modal.getInstance(document.getElementById('priceModal')).hide();
                    loadPrices();
                    alert(result.message || 'تم الحفظ بنجاح');
                } else {
                    alert(result.message || 'حدث خطأ');
                }
            })
            .catch(error => {
                console.error('Error saving price:', error);
                alert('حدث خطأ في حفظ السعر');
            });
        }

        function deletePrice(id, label) {
            if (!confirm(`هل أنت متأكد من حذف السعر "${label}"؟`)) {
                return;
            }
            
            fetch(`api/prices.php?id=${id}`, {
                method: 'DELETE'
            })
            .then(response => response.json())
            .then(result => {
                if (result.status === 'success' || result.success) {
                    loadPrices();
                    alert(result.message || 'تم الحذف بنجاح');
                } else {
                    alert(result.message || 'حدث خطأ في حذف السعر');
                }
            })
            .catch(error => {
                console.error('Error deleting price:', error);
                alert('حدث خطأ في حذف السعر');
            });
        }

        function movePrice(id, direction) {
            const category = document.getElementById('priceCategoryFilter').value;
            let url = 'api/prices.php';
            const params = new URLSearchParams();
            if (category) params.append('category', category);
            params.append('active_only', 'false');
            if (params.toString()) url += '?' + params.toString();
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    const index = data.findIndex(p => p.id === id);
                    if (index === -1) return;
                    
                    const newIndex = direction === 'up' ? index - 1 : index + 1;
                    if (newIndex < 0 || newIndex >= data.length) return;
                    
                    const current = data[index];
                    const target = data[newIndex];
                    
                    const currentOrder = current.display_order || index;
                    const targetOrder = target.display_order || newIndex;
                    
                    const updates = [
                        { id: current.id, order: targetOrder },
                        { id: target.id, order: currentOrder }
                    ];
                    
                    let completed = 0;
                    updates.forEach(update => {
                        fetch(`api/prices.php?id=${update.id}`, {
                            method: 'PUT',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ display_order: update.order })
                        })
                        .then(response => response.json())
                        .then(() => {
                            completed++;
                            if (completed === updates.length) {
                                loadPrices();
                            }
                        });
                    });
                })
                .catch(error => {
                    console.error('Error moving price:', error);
                });
        }

        // Change Password Form Handling
        document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const currentPassword = document.getElementById('currentPassword').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const messageDiv = document.getElementById('passwordMessage');
            
            if (newPassword !== confirmPassword) {
                messageDiv.innerHTML = '<div class="alert alert-danger">كلمات المرور الجديدة غير متطابقة</div>';
                return;
            }
            
            // Get token from session (assuming it's stored or we can login again to get it)
            // For now, we'll try to use the API directly if possible, or simulate the token
            // Since this is a server-side session, we might need a different approach if the API requires JWT
            // But the current admin.php uses session. Let's add a specialized endpoint for session-based change.
            
            fetch('api/admin-change-password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    current_password: currentPassword,
                    new_password: newPassword
                })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    messageDiv.innerHTML = '<div class="alert alert-success">' + result.message + '</div>';
                    document.getElementById('changePasswordForm').reset();
                } else {
                    messageDiv.innerHTML = '<div class="alert alert-danger">' + result.message + '</div>';
                }
            })
            .catch(error => {
                console.error('Error changing password:', error);
                messageDiv.innerHTML = '<div class="alert alert-danger">حدث خطأ في الاتصال بالخادم</div>';
            });
        });

        // تحميل الأسعار عند فتح الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('prices')) {
                loadPrices();
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>