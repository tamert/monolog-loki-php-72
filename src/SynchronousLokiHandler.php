<?php

declare(strict_types=1);

namespace Tamert\MonologLoki\Loki;

use Monolog\Formatter\FormatterInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Monolog\Processor\HostnameProcessor;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\ProcessIdProcessor;
use Monolog\Processor\WebProcessor;
use SensitiveParameter;
use Throwable;

class SynchronousLokiHandler extends AbstractProcessingHandler
{
    public const DEFAULT_THROW_EXCEPTION = false;
    private readonly LokiClient $client;

    /**
     * @param array<string,string> $labels
     */
    public function __construct(
        string $username,
        #[SensitiveParameter]
        string $password,
        string $endpoint,
        int|string|Level $level,
        private readonly array $labels = [],
        bool $bubble = LokiHandler::DEFAULT_BUBBLE,
        int $connectionTimeoutMs = LokiClient::DEFAULT_CONNECTION_TIMEOUT_MILLISECONDS,
        int $timeoutMs = LokiClient::DEFAULT_TIMEOUT_MILLISECONDS,
        private readonly bool $throwExceptions = self::DEFAULT_THROW_EXCEPTION,
        ?LokiClient $client = null
    ) {
        parent::__construct($level, $bubble);
        $this->client = $client ?? new LokiClient($username, $password, $endpoint, $connectionTimeoutMs, $timeoutMs);

        $this->pushProcessor(new IntrospectionProcessor($level, ['Loki\\']));
        $this->pushProcessor(new WebProcessor());
        $this->pushProcessor(new ProcessIdProcessor());
        $this->pushProcessor(new HostnameProcessor());
    }

    /**
     * @inheritDoc
     * @throws Throwable
     */
    protected function write(LogRecord $record): void
    {
        try {
            if (!is_string($record->formatted)) {
                throw new \RuntimeException('Formatted records must be a string, got ' . gettype($record->formatted));
            }
            $this->client->send($record->formatted);
        } catch (Throwable $throwable) {
            if ($this->throwExceptions) {
                throw $throwable;
            }
            trigger_error("Failed to send a single log record to Loki because of " . $throwable, E_USER_WARNING);
        }
    }

    /**
     * @inheritDoc
     * @throws Throwable
     */
    public function handleBatch(array $records): void
    {
        $formattedRecords = $this->getFormatter()->formatBatch($records);
        if (!is_string($formattedRecords)) {
            throw new \RuntimeException('Formatted records must be a string, got ' . gettype($formattedRecords));
        }
        try {
            $this->client->send($formattedRecords);
        } catch (Throwable $throwable) {
            if ($this->throwExceptions) {
                throw $throwable;
            }
            trigger_error(
                "Failed to send " . count($records) . " log records to Loki because of " . $throwable,
                E_USER_WARNING
            );
        }
    }

    protected function getDefaultFormatter(): LokiJsonFormatter
    {
        return new LokiJsonFormatter($this->labels);
    }

    public function getFormatter(): FormatterInterface
    {
        return $this->getDefaultFormatter();
    }
}
