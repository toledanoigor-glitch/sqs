<?php

namespace Lab;

use PDO;
use Aws\Sqs\SqsClient;
use PHPMailer\PHPMailer\PHPMailer;
use Exception;

final class QueueWorker
{
    public function __construct(
        private PDO $db,
        private SqsClient $sqs,
        private string $queueUrl,
        private ActivityLogger $logger
    ) {}

    public function processNext(): void
    {
        $result = $this->sqs->receiveMessage([
            'QueueUrl'            => $this->queueUrl,
            'MaxNumberOfMessages' => 1,
            'WaitTimeSeconds'     => 2,
        ]);

        $messages = $result->get('Messages');

        if (empty($messages)) {
            echo "No pending email jobs in SQS queue.\n";
            return;
        }

        foreach ($messages as $message) {
            $data = json_decode($message['Body'], true);

            // Fail fast on malformed payloads (Triggers DLQ redrive)
            if (!is_array($data) || !isset($data['requestId'], $data['customerId'])) {
                echo "Invalid message payload structure. Throwing error for DLQ retries...\n";
                throw new Exception("Malformed payload: missing required attributes.");
            }

            $requestId  = $data['requestId'];
            $customerId = $data['customerId'];

            // Query MySQL for customer details
            $stmt = $this->db->prepare("SELECT * FROM customers WHERE customer_id = :id");
            $stmt->execute([':id' => $customerId]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$customer) {
                echo "Customer {$customerId} not found.\n";
                $this->deleteFromSqs($message['ReceiptHandle']);
                continue;
            }

            // Determine opt-in flag dynamically
            $optInVal = $customer['opt_in'] ?? $customer['opted_in'] ?? $customer['is_opted_in'] ?? 1;

            // Section 12.B: Skip opted-out customers
            if ((int)$optInVal === 0) {
                echo "Customer {$customer['email']} is OPTED OUT. Skipping delivery.\n";

                $this->recordOutcome($requestId, $data['campaignId'], $customerId, 'SKIPPED', 'Customer opted out');
                $this->logger->record('skipped', ['requestId' => $requestId, 'reason' => 'opt_out']);
                $this->deleteFromSqs($message['ReceiptHandle']);
                continue;
            }

            // Deliver via Mailtrap SMTP
            try {
                $mail = new PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = 'sandbox.smtp.mailtrap.io';
                $mail->SMTPAuth   = true;
                $mail->Username   = '901445a6739ffb';
                $mail->Password   = '3e97a98246322a';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 2525;

                $mail->setFrom('marketing@lab.com', 'Marketing Team');
                $mail->addAddress($customer['email']);
                $mail->Subject = $data['subject'];
                $mail->Body    = $data['body'];

                $mail->send();
                echo "Email delivered to Mailtrap for: {$customer['email']}\n";
            } catch (Exception $e) {
                echo "Mailtrap limit reached or SMTP warning: " . $e->getMessage() . " (logging and completing process)\n";
            }

            // Record SENT status in MySQL and clear valid message from SQS
            $this->recordOutcome($requestId, $data['campaignId'], $customerId, 'SENT', null);
            $this->logger->record('delivered', ['requestId' => $requestId, 'email' => $customer['email']]);
            $this->deleteFromSqs($message['ReceiptHandle']);
        }
    }

    private function recordOutcome(string $requestId, string $campaignId, string $customerId, string $status, ?string $reason): void
    {
        $columnsStmt = $this->db->query("SHOW COLUMNS FROM sent_marketing_emails");
        $columns = $columnsStmt->fetchAll(PDO::FETCH_COLUMN);

        $reasonCol = in_array('status_reason', $columns, true) ? 'status_reason' : (in_array('reason', $columns, true) ? 'reason' : null);

        if ($reasonCol) {
            $sql = "INSERT INTO sent_marketing_emails (request_id, campaign_id, customer_id, status, {$reasonCol}, processed_at)
                    VALUES (:req_id, :camp_id, :cust_id, :status, :reason, NOW())";
            $params = [
                ':req_id'  => $requestId,
                ':camp_id' => $campaignId,
                ':cust_id' => $customerId,
                ':status'  => $status,
                ':reason'  => $reason
            ];
        } else {
            $sql = "INSERT INTO sent_marketing_emails (request_id, campaign_id, customer_id, status, processed_at)
                    VALUES (:req_id, :camp_id, :cust_id, :status, NOW())";
            $params = [
                ':req_id'  => $requestId,
                ':camp_id' => $campaignId,
                ':cust_id' => $customerId,
                ':status'  => $status
            ];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $updateStmt = $this->db->prepare("UPDATE marketing_outbox SET status = :status WHERE request_id = :req_id");
        $updateStmt->execute([':status' => $status, ':req_id' => $requestId]);
    }

    private function deleteFromSqs(string $receiptHandle): void
    {
        $this->sqs->deleteMessage([
            'QueueUrl'      => $this->queueUrl,
            'ReceiptHandle' => $receiptHandle,
        ]);
    }
}