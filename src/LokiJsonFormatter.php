<?php

namespace Tamert\MonologLoki\Loki;

use Monolog\Formatter\NormalizerFormatter;

/**
 * Simple Loki formatter for Monolog 2.x
 */
class LokiFormatter extends NormalizerFormatter
{
    public function format(array $record)
    {
        $normalized = parent::format($record);

        $message = isset($normalized['message']) ? $normalized['message'] : '';
        $context = isset($normalized['context']) && !empty($normalized['context'])
            ? json_encode($normalized['context'])
            : '';

        return trim($message . ' ' . $context);
    }
}
