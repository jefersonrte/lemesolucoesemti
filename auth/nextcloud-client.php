<?php

if (is_file(__DIR__ . '/nextcloud-runtime.php')) {
    require_once __DIR__ . '/nextcloud-runtime.php';
}

final class NextcloudProvisioningException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus = 0,
        public readonly int $ocsStatus = 0
    ) {
        parent::__construct($message);
    }
}

function nextcloud_is_configured(): bool
{
    return defined('NEXTCLOUD_BASE_URL')
        && defined('NEXTCLOUD_PROVISION_USER')
        && defined('NEXTCLOUD_PROVISION_TOKEN')
        && trim((string) NEXTCLOUD_BASE_URL) !== ''
        && trim((string) NEXTCLOUD_PROVISION_USER) !== ''
        && trim((string) NEXTCLOUD_PROVISION_TOKEN) !== '';
}

function nextcloud_ocs_request(string $method, string $path, array $data = []): array
{
    if (!nextcloud_is_configured()) {
        throw new NextcloudProvisioningException('O provisionamento do Nextcloud ainda nao foi configurado.');
    }

    $separator = str_contains($path, '?') ? '&' : '?';
    $url = rtrim((string) NEXTCLOUD_BASE_URL, '/') . '/' . ltrim($path, '/') . $separator . 'format=json';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_USERPWD => (string) NEXTCLOUD_PROVISION_USER . ':' . (string) NEXTCLOUD_PROVISION_TOKEN,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded; charset=utf-8',
            'OCS-APIRequest: true',
            'User-Agent: Leme-User-Provisioning/1.0',
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    if ($data !== []) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }

    $body = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpStatus = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false) {
        throw new NextcloudProvisioningException('Falha de conexao com o Nextcloud: ' . $curlError);
    }

    $response = json_decode($body, true);
    $meta = is_array($response) ? ($response['ocs']['meta'] ?? []) : [];
    $ocsStatus = (int) ($meta['statuscode'] ?? 0);
    $isSuccess = $httpStatus >= 200
        && $httpStatus < 300
        && is_array($response)
        && (($meta['status'] ?? '') === 'ok' || in_array($ocsStatus, [100, 200], true));

    if (!$isSuccess) {
        $message = is_array($meta) ? trim((string) ($meta['message'] ?? '')) : '';
        throw new NextcloudProvisioningException(
            $message !== '' ? $message : 'O Nextcloud recusou a operacao de usuario.',
            $httpStatus,
            $ocsStatus
        );
    }

    return $response['ocs']['data'] ?? [];
}

function nextcloud_user_exists(string $userId): bool
{
    try {
        nextcloud_ocs_request('GET', '/ocs/v1.php/cloud/users/' . rawurlencode($userId));
        return true;
    } catch (NextcloudProvisioningException $e) {
        if ($e->httpStatus === 404 || in_array($e->ocsStatus, [101, 998], true)) {
            return false;
        }
        throw $e;
    }
}

function nextcloud_set_user_value(string $userId, string $key, string $value): void
{
    nextcloud_ocs_request('PUT', '/ocs/v1.php/cloud/users/' . rawurlencode($userId), [
        'key' => $key,
        'value' => $value,
    ]);
}

function nextcloud_set_user_enabled(string $userId, bool $enabled): void
{
    nextcloud_ocs_request(
        'PUT',
        '/ocs/v1.php/cloud/users/' . rawurlencode($userId) . '/' . ($enabled ? 'enable' : 'disable')
    );
}

function nextcloud_upsert_user(
    string $userId,
    string $password,
    string $displayName,
    string $email,
    bool $active
): bool {
    $exists = nextcloud_user_exists($userId);
    $created = false;

    if (!$exists) {
        if ($password === '') {
            throw new NextcloudProvisioningException(
                'Informe uma nova senha para concluir o primeiro provisionamento deste usuario.'
            );
        }

        nextcloud_ocs_request('POST', '/ocs/v1.php/cloud/users', [
            'userid' => $userId,
            'password' => $password,
            'displayName' => $displayName,
            'email' => $email,
        ]);
        $created = true;
    }

    nextcloud_set_user_value($userId, 'displayname', $displayName);
    nextcloud_set_user_value($userId, 'email', $email);

    if ($password !== '') {
        nextcloud_set_user_value($userId, 'password', $password);
    }

    nextcloud_set_user_enabled($userId, $active);
    return $created;
}

function nextcloud_disable_user_if_exists(string $userId): void
{
    if (nextcloud_user_exists($userId)) {
        nextcloud_set_user_enabled($userId, false);
    }
}

function nextcloud_delete_user_if_exists(string $userId): void
{
    if (nextcloud_user_exists($userId)) {
        nextcloud_ocs_request('DELETE', '/ocs/v1.php/cloud/users/' . rawurlencode($userId));
    }
}
