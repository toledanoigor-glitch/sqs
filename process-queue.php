<?php

require __DIR__ . '/config.php';
require __DIR__ . '/aws-config.php';

use Lab\Database;
use Lab\QueueWorker;
use Lab\ActivityLogger;

$db = (new Database())->connect();
$queueUrl = getenv('SQS_QUEUE_URL') ?: QUEUE_URL;
$logger = new ActivityLogger();

$worker = new QueueWorker($db, $sqs, $queueUrl, $logger);

echo "Starting Queue Worker...\n";
try {
    $worker->processNext();
} catch (Exception $e) {
    echo "Worker caught exception: " . $e->getMessage() . "\n";
}