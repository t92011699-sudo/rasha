 <?php
// index.php

header('Content-Type: application/json');

echo json_encode([
    'message' => '🚀 Rasha Clinic API is running!',
    'version' => '1.0.0',
    'timestamp' => date('Y-m-d H:i:s')
]);
?>