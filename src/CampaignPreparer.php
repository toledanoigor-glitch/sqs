<?php
namespace Lab;

use PDO;
use Exception;
use RuntimeException;

final class CampaignPreparer
{
    public function __construct(
        private PDO $db
    ) {}

    public function prepare(string $campaignId, string $promoCode, string $subject, string $body): void
    {
        // TODO C3: Check existing outbox rows for this campaign
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM marketing_outbox WHERE campaign_id = :campaign_id");
        $stmt->execute([':campaign_id' => $campaignId]);
        $existingCount = (int) $stmt->fetchColumn();

        if ($existingCount === 50) {
            echo "Campaign '{$campaignId}' is already fully prepared with 50 requests. Preserving selection.\n";
            return;
        }

        if ($existingCount > 0 && $existingCount !== 50) {
            throw new RuntimeException("Inconsistent state: Campaign '{$campaignId}' has {$existingCount} existing requests instead of 50.");
        }

        // TODO C2: Select exactly 50 distinct customers at random with marketing_opt_in = 1
        $stmt = $this->db->prepare("
            SELECT customer_id 
            FROM customers 
            WHERE marketing_opt_in = 1 
            ORDER BY RAND() 
            LIMIT 50
        ");
        $stmt->execute();
        $selectedCustomers = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (count($selectedCustomers) < 50) {
            throw new RuntimeException("Not enough eligible opted-in customers to prepare campaign.");
        }

        // Prepare insertion statement
        $insertStmt = $this->db->prepare("
            INSERT INTO marketing_outbox (request_id, campaign_id, customer_id, promo_code, subject, body, status, created_at)
            VALUES (:request_id, :campaign_id, :customer_id, :promo_code, :subject, :body, 'PENDING', :created_at)
        ");

        $this->db->beginTransaction();

        try {
            $createdAt = gmdate('Y-m-d H:i:s');

            foreach ($selectedCustomers as $customerId) {
                // Generate a 32-character hexadecimal request ID
                $requestId = bin2hex(random_bytes(16));

                // TODO C1: Execute prepared insert with required parameters
                $insertStmt->execute([
                    ':request_id'  => $requestId,
                    ':campaign_id' => $campaignId,
                    ':customer_id' => $customerId,
                    ':promo_code'  => $promoCode,
                    ':subject'     => $subject,
                    ':body'        => $body,
                    ':created_at'  => $createdAt,
                ]);
            }

            $this->db->commit();
            echo "Prepared 50 campaign requests for campaign '{$campaignId}' successfully!\n";
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}