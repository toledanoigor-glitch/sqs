<?php
namespace Lab;

use PDO;
use Faker\Generator;
use LogicException;

final class CustomerSeeder
{
    public function __construct(
        private PDO $db,
        private Generator $faker
    ) {}

    public function seed(): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO customers (customer_id, full_name, email, marketing_opt_in, created_at)
            VALUES (:customer_id, :full_name, :email, :marketing_opt_in, :created_at)
            ON DUPLICATE KEY UPDATE customer_id = customer_id
        ");

        $createdAt = gmdate('Y-m-d H:i:s');

        for ($i = 1; $i <= 10000; $i++) {
            $customerId = sprintf('CUST-%05d', $i);
            $email = sprintf('customer%05d@example.com', $i);

            // TODO S1: Generate a full name using Faker[cite: 8]
            $fullName = $this->faker->name();

            // TODO S2: Assign consent 1 for customers 1-8,000 and 0 for 8,001-10,000[cite: 8]
            $marketingOptIn = ($i <= 8000) ? 1 : 0;

            $stmt->execute([
                ':customer_id'      => $customerId,
                ':full_name'        => $fullName,
                ':email'            => $email,
                ':marketing_opt_in' => $marketingOptIn,
                ':created_at'       => $createdAt,
            ]);
        }
    }
}