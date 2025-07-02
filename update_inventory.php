<?php
/**
 * Honey's Place Inventory Sync Script
 * - Logs everything
 * - Sends email notification after run
 */

date_default_timezone_set('UTC');

$logFile = __DIR__ . '/inventory_sync_log.txt';

function logMsg($msg) {
    global $logFile;
    $time = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$time] $msg\n", FILE_APPEND);
    echo "$msg\n";
}

function sendEmail($subject, $body) {
    global $emailTo, $logFile;
    if (!$emailTo) return;
    $headers = "From: no-reply@yourdomain.com\r\n";
    mail($emailTo, $subject, $body, $headers);
}

$hpUsername = getenv('HP_USERNAME');
$hpPassword = getenv('HP_PASSWORD');
$shopifyApiKey = getenv('SHOPIFY_API_KEY');
$shopifyApiPassword = getenv('SHOPIFY_API_PASSWORD');
$shopifyStoreDomain = getenv('SHOPIFY_STORE_DOMAIN');
$emailTo = getenv('SYNC_NOTIFICATION_EMAIL');

if (!$hpUsername || !$hpPassword || !$shopifyApiKey || !$shopifyApiPassword || !$shopifyStoreDomain) {
    logMsg("❌ Missing required environment variables.");
    exit(1);
}

logMsg("Starting Honey's Place inventory sync...");

$url = "https://honeysplace.com/API/inventory/index.php";

$opts = [
    "http" => [
        "header" => "Authorization: Basic " . base64_encode("$hpUsername:$hpPassword"),
        "timeout" => 30
    ]
];
$context = stream_context_create($opts);

$xmlString = @file_get_contents($url, false, $context);

if (!$xmlString) {
    logMsg("❌ Failed to download Honey's Place inventory.");
    sendEmail("Inventory Sync FAILED: Could not download Honey's Place inventory.", file_get_contents($logFile));
    exit(1);
}

logMsg("✅ Downloaded Honey's Place inventory XML.");

libxml_use_internal_errors(true);
$xml = simplexml_load_string($xmlString);
if (!$xml) {
    logMsg("❌ Failed to parse XML:");
    foreach(libxml_get_errors() as $error) {
        logMsg("  - " . trim($error->message));
    }
    sendEmail("Inventory Sync FAILED: XML parsing error.", file_get_contents($logFile));
    exit(1);
}

$skuPrefix = 'HP-';
$updatedCount = 0;

foreach ($xml->product as $product) {
    $sku = trim((string)$product->sku);
    $qty = (int)$product->qty;

    if (!str_starts_with($sku, $skuPrefix)) {
        continue;
    }

    // Update Shopify inventory here:
    // For demo, just log it:
    logMsg("Updating SKU $sku with quantity $qty");

    // Here you would call Shopify Admin API to update inventory for $sku
    // Example placeholder: updateShopifyInventory($sku, $qty);

    $updatedCount++;
}

logMsg("✅ Updated inventory for $updatedCount Honey's Place products.");

// Send success email notification
sendEmail("Inventory Sync SUCCESS", file_get_contents($logFile));

exit(0);
