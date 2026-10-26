<?php
require_once __DIR__ . '/config.php';

$db = (new Lab\Database())->connect();

echo "--- CUSTOMER OVERVIEW ---\n";
$total = $db->query("SELECT COUNT(*) FROM customers")->fetchColumn();
echo "Total Customers: {$total}\n";

$stmt = $db->query("SELECT marketing_opt_in, COUNT(*) AS count FROM customers GROUP BY marketing_opt_in");
while ($row = $stmt->fetch()) {
    $status = $row['marketing_opt_in'] ? 'Opted In (1)' : 'Opted Out (0)';
    echo "  - {$status}: {$row['count']}\n";
}

echo "\n--- OUTBOX OVERVIEW ---\n";
$outboxTotal = $db->query("SELECT COUNT(*) FROM marketing_outbox")->fetchColumn();
echo "Total Outbox Requests: {$outboxTotal}\n";

$stmt = $db->query("SELECT status, COUNT(*) AS count FROM marketing_outbox GROUP BY status");
while ($row = $stmt->fetch()) {
    echo "  - Status {$row['status']}: {$row['count']}\n";
}