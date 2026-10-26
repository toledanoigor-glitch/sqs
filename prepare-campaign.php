<?php
require_once __DIR__ . '/config.php';

$campaignId = $argv[1] ?? 'CAMP-001';
$promoCode  = $argv[2] ?? 'PROMO15';
$subject    = "Exclusive Promo Code: " . $promoCode;
$body       = "Hello! Use your promo code " . $promoCode . " on your next purchase.";

try {
    $db = (new Lab\Database())->connect();
    $preparer = new Lab\CampaignPreparer($db);
    $preparer->prepare($campaignId, $promoCode, $subject, $body);
} catch (Exception $e) {
    echo "Preparation Error: " . $e->getMessage() . "\n";
}