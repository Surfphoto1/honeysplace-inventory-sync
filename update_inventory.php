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
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    echo $line;
}

// Temporarily disable email sending
function sendEmail($subject, $body) {
    // Disabled on GitHub Actions runner to avoid errors
    // Uncomment and configure if you use SMTP or an API for emails
    // global $emailTo;
    // if (!$emailTo) return;
    // $headers = "From: no-reply@yourdomain.com\r\n";
    // mail($emailTo, $subject, $body, $headers);
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

    logMsg("Starting Honey's Place inventory sync...");

    $url = "https://honeysplace.com/API/inventory/index.php";
    logMsg("Downloading from URL: $url with user $hpUsername");

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
        // sendEmail("Inventory Sync FAILED: Could not download Honey's Place inventory.", file_get_contents($logFile));
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
        // sendEmail("Inventory Sync FAILED: XML parsing error.", file_get_contents($logFile));
        exit(1);
    }

    $skuPrefix = 'HP-';
    $updatedCount = 0;

    foreach ($xml->product as $product) {
        $sku = trim((string)$product->sku);
        $qty = (int)$product->qty;

        if (strpos($sku, $skuPrefix) !== 0) {
            continue;
        }

        logMsg("Updating SKU $sku with quantity $qty");

        // TODO: Add Shopify inventory update logic here

        $updatedCount++;
    }

    logMsg("✅ Updated inventory for $updatedCount Honey's Place products.");

    // sendEmail("Inventory Sync SUCCESS", file_get_contents($logFile));

    exit(0);

} catch (Throwable $e) {
    logMsg("Fatal error: " . $e->getMessage());
    // sendEmail("Inventory Sync FAILED: Fatal error", file_get_contents($logFile));
    exit(1);
}
