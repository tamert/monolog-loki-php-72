<?php

declare(strict_types=1);

namespace Tamert\Monolog\Loki;

use CurlHandle;
use RuntimeException;

class LokiClient
{
    /** @var array<int> */
    private static array $retriableErrorCodes = [
        CURLE_COULDNT_RESOLVE_HOST,
        CURLE_COULDNT_CONNECT,
        CURLE_HTTP_NOT_FOUND,
        CURLE_READ_ERROR,
        CURLE_OPERATION_TIMEOUTED,
        CURLE_HTTP_POST_ERROR,
        CURLE_SSL_CONNECT_ERROR,
    ];
    public const DEFAULT_CONNECTION_TIMEOUT_MILLISECONDS = 5000;
    public const DEFAULT_TIMEOUT_MILLISECONDS = 5000;
    private null|false|CurlHandle $handle = null;

    public function __construct(
        private readonly string $username,
        #[\SensitiveParameter]
        private readonly string $password,
        private readonly string $endpoint,
        private readonly int $connectionTimeoutMs = self::DEFAULT_CONNECTION_TIMEOUT_MILLISECONDS,
        private readonly int $timeoutMs = self::DEFAULT_TIMEOUT_MILLISECONDS,
    ) {
        if (!\extension_loaded('curl')) {
            throw new \LogicException('The curl extension is needed to use the ' . LokiHandler::class);
        }
    }

    /**
     * @param array<key-of, mixed>|string $data
     */
    public function send(array|string $data): void
    {
        if ($this->handle === null) {
            $this->initCurlHandle();
        }
        if (!$this->handle instanceof CurlHandle) {
            throw new RuntimeException('Failed to initialize cURL handle');
        }
        \curl_setopt($this->handle, CURLOPT_POSTFIELDS, $data);
        \curl_setopt($this->handle, CURLOPT_RETURNTRANSFER, true);

        $this->execute($this->handle, 5, false);
    }

    public function execute(CurlHandle $ch, int $retries = 5, bool $closeAfterDone = true): bool|string
    {
        while ($retries > 0) {
            $retries--;
            $curlResponse = curl_exec($ch);
            if ($curlResponse === false) {
                $curlErrno = curl_errno($ch);

                if (false === \in_array($curlErrno, self::$retriableErrorCodes, true) || $retries === 0) {
                    $curlError = curl_error($ch);

                    if ($closeAfterDone) {
                        curl_close($ch);
                    }

                    throw new RuntimeException(sprintf('Curl error (code %d): %s', $curlErrno, $curlError));
                }
                continue;
            }

            if ($closeAfterDone) {
                curl_close($ch);
            }

            return $curlResponse;
        }
        return false;
    }

    private function initCurlHandle(): void
    {
        $this->handle = \curl_init();
        if (!$this->handle instanceof CurlHandle) {
            throw new RuntimeException('Failed to initialize cURL handle');
        }

        \curl_setopt($this->handle, CURLOPT_URL, $this->endpoint . '/loki/api/v1/push');
        \curl_setopt($this->handle, CURLOPT_USERPWD, "{$this->username}:{$this->password}");
        \curl_setopt($this->handle, CURLOPT_POST, true);
        \curl_setopt($this->handle, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        \curl_setopt($this->handle, CURLOPT_CONNECTTIMEOUT_MS, $this->connectionTimeoutMs);
        \curl_setopt($this->handle, CURLOPT_TIMEOUT_MS, $this->timeoutMs);
    }
}
