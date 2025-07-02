<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('UTC');

// Log file in current directory (repo root)
$logFile = __DIR__ . '/inventory_sync_log.txt';

// Clear previous log file on each run
if (file_exists($logFile)) {
    unlink($logFile);
}

function logMsg($msg) {
    global $logFile;
    $time = date('Y-m-d H:i:s');
    $line = "[$time] $msg\n";
    // Append to file
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    // Print to console for GitHub Actions logs
    echo $line;
}

function sendEmail($subject, $body) {
    global $emailTo;
    if (!$emailTo) return;
    $headers = "From: no-reply@yourdomain.com\r\n";
    mail($emailTo, $subject, $body, $headers);
}

try {
    logMsg("Script started");

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

    logMsg("Starting Honey's Place i
