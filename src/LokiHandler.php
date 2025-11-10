<?php

namespace Tamert\MonologLoki;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

/**
 * Simplified Loki handler compatible with PHP 7.2 + Monolog 2.x
 */
class LokiHandler extends AbstractProcessingHandler
{
    /** @var string */
    private $lokiUrl;

    /** @var array */
    private $labels;

    public function __construct(
        string $lokiUrl,
        array $labels = ['app' => 'php-app'],
        $level = Logger::DEBUG,
        bool $bubble = true
    ) {
        $this->lokiUrl = $lokiUrl;
        $this->labels = $labels;
        parent::__construct($level, $bubble);
    }

    /**
     * @param array $record
     */
    protected function write(array $record): void
    {
        $timestamp = (string) (microtime(true) * 1e9);

        $entry = [
            'streams' => [[
                'stream' => $this->labels,
                'values' => [[$timestamp, $record['formatted']]],
            ]],
        ];

        $this->sendToLoki($entry);
    }

    /**
     * Sends data to Loki via HTTP POST
     */
    private function sendToLoki(array $entry): void
    {
        $payload = json_encode($entry);
        if ($payload === false) {
            return;
        }

        $ch = curl_init($this->lokiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);

        curl_exec($ch);
        curl_close($ch);
    }
}
