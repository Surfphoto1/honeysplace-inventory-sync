<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('UTC');

// === Settings ===
$inventoryUrl = 'https://www.honeysplace.com/df/IGnJiYVwz6xVfAaR/xml';
$logFile = __DIR__ . '/inventory_sync_log.txt';

// === Logging Function ===
function logMsg($msg) {
    global $logFile;
    $timestamp = date('[Y-m-d H:i:s] ');
    file_put_contents($logFile, $timestamp . $msg . PHP_EOL, FILE_APPEND);
}

// === Start Logging ===
logMsg("Script started");
logMsg("Starting Honey's Place inventory sync...");

// === Download Inventory ===
logMsg("📥 Downloading from URL: $inventoryUrl");

$inventoryXml = @file_get_contents($inventoryUrl);
if ($inventoryXml === false) {
    logMsg("❌ Failed to download Honey's Place inventory.");
    exit(1);
}

file_put_contents(__DIR__ . '/inventory.xml', $inventoryXml);
logMsg("✅ Inventory downloaded and saved to inventory.xml");

// === Parse XML ===
libxml_use_internal_errors(true);
$xml = simplexml_load_string($inventoryXml);
if ($xml === false) {
    logMsg("❌ Failed to parse XML.");
    foreach (libxml_get_errors() as $error) {
        logMsg("XML Error: " . $error->message);
    }
    exit(1);
}
logMsg("✅ XML parsed successfully");

// === Filter and Display Example SKUs ===
$filteredCount = 0;
foreach ($xml->children() as $item) {
    $sku = (string) $item->sku;
    $qty = (int) $item->qty;

    // Example filter: Only SKUs that start with "HP-" or some identifier
    if (stripos($sku, 'HP-') === 0) {
        logMsg("🔍 SKU: $sku | Qty: $qty");
        $filteredCount++;
    }
}
logMsg("✅ Finished filtering. Total filtered items: $filteredCount");

// === End ===
logMsg("✅ Inventory sync complete.");
