<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('UTC');

$logFile = __DIR__ . '/inventory_sync_log.txt';

// Start logging
file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Script started\n", FILE_APPEND);
file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Starting Honey's Place inventory sync...\n", FILE_APPEND);

// Shopify credentials from GitHub secrets (set in Actions)
$shopifyApiKey = getenv('SHOPIFY_API_KEY');
$shopifyApiPassword = getenv('SHOPIFY_API_PASSWORD');
$shopifyStoreDomain = getenv('SHOPIFY_STORE_DOMAIN');

// Honey's Place inventory XML URL
$inventoryUrl = 'https://www.honeysplace.com/df/IGnJiYVwz6xVfAaR/xml';
file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Downloading from URL: $inventoryUrl\n", FILE_APPEND);

// Fetch inventory data
$inventoryXml = @file_get_contents($inventoryUrl);
if ($inventoryXml === false) {
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ❌ Failed to download Honey's Place inventory.\n", FILE_APPEND);
    exit(1);
}

$xml = simplexml_load_string($inventoryXml);
if (!$xml) {
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ❌ Failed to parse XML.\n", FILE_APPEND);
    exit(1);
}

$count = 0;
foreach ($xml->product as $product) {
    $sku = (string) $product->sku;
    $stock = (int) $product->stock;

    // Only sync products for Honey's Place
    if (strpos($sku, 'HP-') !== 0) {
        continue;
    }

    // Log dry-run action
    $logLine = "[" . date('Y-m-d H:i:s') . "] [DRY-RUN] Would update SKU $sku to stock $stock\n";
    file_put_contents($logFile, $logLine, FILE_APPEND);
    $count++;
}

file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ✅ DRY-RUN complete. $count items processed.\n", FILE_APPEND);
exit(0);
