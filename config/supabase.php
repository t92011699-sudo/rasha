<?php
// config/supabase.php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

function getSupabaseClient() {
    $url = $_ENV['SUPABASE_URL'];
    $key = $_ENV['SUPABASE_KEY'];
    
    return [
        'url' => $url,
        'key' => $key
    ];
}

function supabaseRequest($method, $path, $data = null, $params = []) {
    $client = getSupabaseClient();
    
    $url = $client['url'] . '/rest/v1/' . $path;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . $client['key'],
        'Authorization: Bearer ' . $client['key'],
        'Content-Type: application/json',
        'Prefer: return=representation'
    ]);
    
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['data' => null, 'status' => 500, 'error' => $error];
    }
    
    return [
        'data' => json_decode($response, true),
        'status' => $httpCode
    ];
}

function verifyToken($token) {
    try {
        $decoded = \Firebase\JWT\JWT::decode($token, $_ENV['JWT_SECRET'], ['HS256']);
        return (array) $decoded;
    } catch (Exception $e) {
        return null;
    }
}

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
?>