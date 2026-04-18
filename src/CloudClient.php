<?php

namespace VoltTest;

use VoltTest\Exceptions\AuthenticationException;
use VoltTest\Exceptions\CloudConnectionException;
use VoltTest\Exceptions\CloudException;
use VoltTest\Exceptions\PlanLimitException;

class CloudClient
{
    private const BASE_URL = 'https://cloud.volt-test.com';

    private const USER_AGENT = 'volt-test-php-sdk';

    private string $apiKey;

    private string $baseUrl;

    public function __construct(string $apiKey, ?string $baseUrl = null)
    {
        if (empty($apiKey)) {
            throw new AuthenticationException('API key is required');
        }

        if (! str_starts_with($apiKey, 'vt_')) {
            throw new AuthenticationException('API key must start with "vt_"');
        }

        $this->apiKey = $apiKey;
        $this->baseUrl = $baseUrl ?? self::BASE_URL;
    }

    public function createTest(array $data): array
    {
        return $this->request('POST', '/api/v1/tests', $data);
    }

    public function startRun(string $testId): array
    {
        return $this->request('POST', '/api/v1/runs', ['test_id' => $testId]);
    }

    public function getRunStatus(string $runId): array
    {
        return $this->request('GET', '/api/v1/runs/' . $runId);
    }

    public function stopRun(string $runId): array
    {
        return $this->request('DELETE', '/api/v1/runs/' . $runId);
    }

    private function request(string $method, string $endpoint, ?array $body = null): array
    {
        $url = rtrim($this->baseUrl, '/') . $endpoint;

        $ch = curl_init();

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: ' . self::USER_AGENT,
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new CloudConnectionException('Failed to connect to VoltTest Cloud: ' . $curlError);
        }

        $decoded = json_decode($response, true) ?? [];

        if ($httpCode === 401) {
            $message = $decoded['error']['message'] ?? 'Invalid or expired API key';

            throw new AuthenticationException($message);
        }

        if ($httpCode === 403) {
            $message = $decoded['error']['message'] ?? 'Plan limit exceeded';

            throw new PlanLimitException($message);
        }

        if ($httpCode >= 400) {
            $message = $decoded['error']['message'] ?? "API request failed with status {$httpCode}";

            throw new CloudException($message);
        }

        return $decoded;
    }
}
