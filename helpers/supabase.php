<?php
/**
 * ===== Supabase REST API Client for Vercel =====
 */

function supabaseRequest(string $method, string $endpoint, array $data = null, array $filters = [], bool $useServiceKey = false): array
{
    $config = getSupabaseConfig();
    $url = $config['url'] . '/rest/v1/' . ltrim($endpoint, '/');
    
    if (!empty($filters)) {
        $queryParams = [];
        foreach ($filters as $key => $value) {
            $queryParams[] = $key . '=' . urlencode($value);
        }
        $url .= '?' . implode('&', $queryParams);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'apikey: ' . ($useServiceKey ? $config['service_key'] : $config['anon_key']),
        'Authorization: Bearer ' . ($useServiceKey ? $config['service_key'] : $config['anon_key']),
        'Content-Type: application/json',
        'Prefer: return=representation',
    ]);
    
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        throw new Exception('Supabase API Error: ' . $error);
    }
    
    if ($httpCode >= 400) {
        $errorData = json_decode($response, true);
        $message = $errorData['message'] ?? $errorData['error'] ?? $response;
        throw new Exception('Supabase API Error (' . $httpCode . '): ' . $message);
    }
    
    return json_decode($response, true) ?? [];
}

function supabaseGet(string $endpoint, array $filters = [], bool $useServiceKey = false): array
{
    return supabaseRequest('GET', $endpoint, null, $filters, $useServiceKey);
}

function supabasePost(string $endpoint, array $data, bool $useServiceKey = false): array
{
    return supabaseRequest('POST', $endpoint, $data, [], $useServiceKey);
}

function supabasePut(string $endpoint, array $data, array $filters = [], bool $useServiceKey = false): array
{
    return supabaseRequest('PUT', $endpoint, $data, $filters, $useServiceKey);
}

function supabasePatch(string $endpoint, array $data, array $filters = [], bool $useServiceKey = false): array
{
    return supabaseRequest('PATCH', $endpoint, $data, $filters, $useServiceKey);
}

function supabaseDelete(string $endpoint, array $filters = [], bool $useServiceKey = false): array
{
    return supabaseRequest('DELETE', $endpoint, null, $filters, $useServiceKey);
}