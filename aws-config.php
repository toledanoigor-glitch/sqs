<?php
require 'vendor/autoload.php';

use Aws\Sqs\SqsClient;

$sqs = new SqsClient([
    'version' => '2012-11-05',
    'region'  => 'ap-southeast-2',
    'credentials' => [
        'key'    => 'YOUR_AWS_ACCESS_KEY_ID',
        'secret' => 'YOUR_AWS_SECRET_ACCESS_KEY',
    ],
]);

define('QUEUE_URL', 'https://sqs.ap-southeast-2.amazonaws.com/408852558600/MarketingEmailQueue-toledano');
define('DLQ_URL',   'https://sqs.ap-southeast-2.amazonaws.com/408852558600/MarketingEmailDLQ-toledano');