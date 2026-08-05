<?php
declare(strict_types=1);

function pet_dashboard_remote_request(string $endpoint, string $method = 'GET', ?string $token = null, ?array $payload = null): array
{
    $url = 'https://lemeinformatica.com.br/pet/api/' . ltrim($endpoint, '/');
    $headers = ['Accept: application/json'];
    if ($token !== null && $token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    $body = null;
    if ($payload !== null) {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers[] = 'Content-Type: application/json; charset=utf-8';
    }

    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    if ($body !== null) {
        curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
    }

    $responseBody = curl_exec($handle);
    $error = curl_error($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);
    if (!is_string($responseBody)) {
        return ['status' => 502, 'body' => json_encode(['ok' => false, 'erro' => 'Falha na conexao com a API Pet.', 'detalhe' => $error])];
    }
    return ['status' => $status ?: 502, 'body' => $responseBody];
}
