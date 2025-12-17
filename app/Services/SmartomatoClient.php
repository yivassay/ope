<?php
declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class SmartomatoClient
{
    public function __construct(
        private string $baseUrl,
        private string $login,
        private string $password,
        private int $timeoutSeconds = 30,
    ) {
        $this->baseUrl = rtrim($this->baseUrl, '/');
    }

    public function createSessionToken(): string
    {
        // Doc: POST /api/session with login/password (form-encoded), response meta.token
        $url = $this->baseUrl . '/api/session';
        $resp = $this->request('POST', $url, [
            'headers' => [],
            'form' => [
                'login' => $this->login,
                'password' => $this->password,
            ],
        ]);

        $token = $resp['meta']['token'] ?? null;
        if (!is_string($token) || $token === '') {
            throw new RuntimeException('Smartomato: token not found in session response');
        }
        return $token;
    }

    /**
     * Returns list page response: ['orders' => [...], 'meta' => [...]]
     */
    public function listOrders(string $token, array $query): array
    {
        $url = $this->baseUrl . '/api/orders';
        if ($query) {
            $url .= '?' . http_build_query($query);
        }
        return $this->request('GET', $url, [
            'headers' => [
                'Authorization: Token token="' . $token . '"',
            ],
        ]);
    }

    private function request(string $method, string $url, array $opts): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('curl_init failed');
        }

        $headers = $opts['headers'] ?? [];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeoutSeconds);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if (isset($opts['form'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['form']); // application/x-www-form-urlencoded
        }
        if (isset($opts['json'])) {
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($opts['json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $raw = curl_exec($ch);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('Smartomato request failed: ' . $err);
        }

        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new RuntimeException('Smartomato returned non-JSON response (HTTP ' . $code . '): ' . substr($raw, 0, 400));
        }
        if ($code >= 400) {
            throw new RuntimeException('Smartomato HTTP ' . $code . ': ' . json_encode($data, JSON_UNESCAPED_UNICODE));
        }
        return $data;
    }
}

