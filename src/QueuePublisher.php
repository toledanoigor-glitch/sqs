<?php

namespace Lab;

use PDO;
use Aws\Sqs\SqsClient;
use Exception;

final class QueuePublisher
{
    public function __construct(
        private PDO $db,
        private SqsClient $sqs,
        private string $queueUrl,
        private ActivityLogger $logger
    ) {}

    public function publishPending(): void
    {
        // Select all PENDING rows from local outbox
        $stmt = $this->db->prepare("
            SELECT request_id, campaign_id, customer_id, promo_code, subject, body 
            FROM marketing_outbox 
            WHERE status = 'PENDING'
        ");
        $stmt->execute();
        $pendingJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($pendingJobs)) {
            echo "No PENDING jobs found to publish.\n";
            return;
        }

        $updateStmt = $this->db->prepare("
            UPDATE marketing_outbox 
            SET status = 'QUEUED', queued_at = NOW(), sqs_message_id = :sqs_id 
            WHERE request_id = :request_id
        ");

        $publishedCount = 0;

        foreach ($pendingJobs as $job) {
            $payload = json_encode([
                'requestId'  => $job['request_id'],
                'campaignId' => $job['campaign_id'],
                'customerId' => $job['customer_id'],
                'promoCode'  => $job['promo_code'],
                'subject'   => $job['subject'],
                'body'      => $job['body']
            ], JSON_THROW_ON_ERROR);

            try {
                // TODO Q1: Send message to SQS
                $result = $this->sqs->sendMessage([
                    'QueueUrl'    => $this->queueUrl,
                    'MessageBody' => $payload,
                ]);

                $sqsMessageId = $result->get('MessageId');

                // TODO Q2: Update status to QUEUED after acknowledgement
                $updateStmt->execute([
                    ':sqs_id'     => $sqsMessageId,
                    ':request_id' => $job['request_id']
                ]);

                // TODO M1: Log queued event
                $this->logger->record('queued', [
                    'requestId'   => $job['request_id'],
                    'campaignId'  => $job['campaign_id'],
                    'customerId'  => $job['customer_id'],
                    'sqsMessageId'=> $sqsMessageId
                ]);

                $publishedCount++;
            } catch (Exception $e) {
                echo "Failed to publish request {$job['request_id']}: " . $e->getMessage() . "\n";
            }
        }

        echo "Successfully published {$publishedCount} message(s) to SQS!\n";
    }
}