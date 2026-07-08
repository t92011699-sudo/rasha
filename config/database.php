<?php
// config/database.php
class Database {
    private $supabase_url = 'https://qlnnotrkotkmqdesjfmm.supabase.co/rest/v1/';
    private $api_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InFsbm5vdHJrb3RrbXFkZXNqZm1tIiwicm9sZSI6ImFub24iLCJpYXQiOjE3ODMzODI2NjEsImV4cCI6MjA5ODk1ODY2MX0.gbHxqDfz5Gj2rci_s-ht0KxUZ6qS1jbpkM3v1_M2fDM';
    private $service_role_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InFsbm5vdHJrb3RrbXFkZXNqZm1tIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc4MzM4MjY2MSwiZXhwIjoyMDk4OTU4NjYxfQ.55CLw9AcUjqYzW2QwVjFRSszbRd_-nZnkl-O-Z_APtg';

    public function getConnection($use_service_role = false) {
        $headers = [
            'apikey: ' . $this->api_key,
            'Authorization: Bearer ' . ($use_service_role ? $this->service_role_key : $this->api_key),
            'Content-Type: application/json',
            'Prefer: return=representation'
        ];

        return [
            'url' => $this->supabase_url,
            'headers' => $headers
        ];
    }

    public function request($endpoint, $method = 'GET', $data = null, $use_service_role = false) {
        $conn = $this->getConnection($use_service_role);
        $url = $conn['url'] . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $conn['headers']);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        } elseif ($method === 'PATCH') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'status' => $http_code,
            'data' => json_decode($response, true)
        ];
    }
}
?>