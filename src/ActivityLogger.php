<?php

namespace Lab;

final class ActivityLogger
{
    private string $logFile;

    public function __construct(?string $logFile = null)
    {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        $this->logFile = $logFile ?? $logDir . '/events.jsonl';
    }

    public function record(string $event, array $context = []): void
    {
        $entry = array_merge([
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'event'     => $event,
        ], $context);

        file_put_contents(
            $this->logFile,
            json_encode($entry, JSON_UNESCAPED_SLASHES) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }
}