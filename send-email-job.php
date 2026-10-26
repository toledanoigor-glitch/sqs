<?php

require __DIR__ . '/config.php';
require __DIR__ . '/aws-config.php';

use Lab\Database;
use Lab\QueuePublisher;
use Lab\ActivityLogger;

// 1. Connect to MySQL
$db = (new Database())->connect();

// 2. Read Queue URL from environment or configuration
$queueUrl = getenv('SQS_QUEUE_URL') ?: QUEUE_URL;

// 3. Initialize Logger
$logger = new ActivityLogger();

// 4. Instantiate and execute QueuePublisher
$publisher = new QueuePublisher($db, $sqs, $queueUrl, $logger);
$publisher->publishPending();