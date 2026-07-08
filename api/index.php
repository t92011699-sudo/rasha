 <?php
// index.php

header('Content-Type: application/json');

echo json_encode([
    'message' => '🚀 Rasha Clinic API is running!',
    'version' => '1.0.0',
    'supabase_connected' => true,
    'endpoints' => [
        'auth' => ['POST /api/admin/login'],
        'patient' => [
            'GET /api/departments',
            'GET /api/departments/{id}',
            'GET /api/slots?doctor_type_id=&date=',
            'POST /api/bookings'
        ],
        'admin' => [
            'GET /api/admin/slots',
            'POST /api/admin/slots',
            'PUT /api/admin/slots/{id}',
            'DELETE /api/admin/slots/{id}',
            'GET /api/admin/bookings',
            'DELETE /api/admin/bookings/{id}',
            'GET /api/admin/departments',
            'POST /api/admin/departments',
            'PUT /api/admin/departments/{id}',
            'DELETE /api/admin/departments/{id}',
            'POST /api/admin/doctor-types'
        ]
    ]
]);
?>