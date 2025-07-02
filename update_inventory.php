<?php
/**
 * Honey's Place Inventory Sync Script
 * 
 * Usage: Run via GitHub Actions, requires env vars:
 * HP_USERNAME, HP_PASSWORD, SHOPIFY_API_KEY, SHOPIFY_API_PASSWORD, SHOPIFY_STORE_DOMAIN
 */

date_default_timezone_set('UTC');

$logFile = __DIR__ . '/inventory_sync_log.txt';

function logMsg($msg) {
    global $logFile;
    $time = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$time] $msg\n", FILE_APPEND);
    echo "$msg\n";
}

// Load environment variables
$hpUsername = getenv('HP_USERNAME');
$hpPassword = getenv('HP_PASSWORD');
$shopifyApiKey = getenv('SHOPIFY_API_KEY');
$shopifyApiPassword = getenv('SHOPIFY_API_PASSWORD');
$shopifyStoreDomain = getenv('SHOPIFY_STORE_DOMAIN');

if (!$hpUsername || !$hpPassword || !$shopifyApiKey || !$shopifyApiPassword || !$shopifyStoreDomain) {
    logMsg("❌ Missing required environment variables.");
    exit(1);
}

logMsg("Starting Honey's Place inventory sync...");

// Step 1: Download Honey’s Place Inventory XML
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
    exit(1);
}

logMsg("✅ Downloaded Honey's Place inventory XML.");

// Step 2: Parse XML
libxml_use_internal_errors(true);
$xml = simplexml_load_string($xmlString);
if (!$xml) {
    logMsg("❌ Failed to parse XML:");
    foreach(libxml_get_errors() as $error) {
        logMsg("  - " . trim($error->message));
    }
    exit(1);
}

// Optional SKU prefix filter - update only SKUs starting with this prefix
$skuPrefix = 'HP-';

$updatedCount = 0;

foreach ($xml->product as $product) {
    $sku = trim((string)$product->sku);
    $qty = (int)$product->qty;

    if (!str_starts_with($sku, $skuPrefix)) {
        // Skip SKUs not from Honey's Place
        continue;
    }

    logMsg("Processing SKU: $sku with qty: $qty");

    // Step 3: Find product variant in Shopify by SKU
    $endpoint = "https://$shopifyStoreDomain/admin/api/2024-01/products.json?limit=250&fields=id,variants";

    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_USERPWD, "$shopifyApiKey:$shopifyApiPassword");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);

    if ($response === false) {
        logMsg("❌ Shopify API request failed for SKU $sku: " . curl_error($ch));
        curl_close($ch);
        continue;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        logMsg("❌ Shopify API returned HTTP $httpCode for SKU $sku");
        continue;
    }

    $productsData = json_decode($response, true);
    if (empty($productsData['products'])) {
        logMsg("⚠️ No products found in Shopify.");
        continue;
    }

    $variantId = null;
    foreach ($productsData['products'] as $prod) {
        foreach ($prod['variants'] as $variant) {
            if ($variant['sku'] === $sku) {
                $variantId = $variant['id'];
                break 2;
            }
        }
    }

    if (!$variantId) {
        logMsg("⚠️ No Shopify variant found with SKU $sku");
        continue;
    }

    // Step 4: Update inventory_quantity for the variant
    $updateUrl = "https://$shopifyStoreDomain/admin/api/2024-01/variants/$variantId.json";

    $updatePayload = json_encode([
        "variant" => [
            "id" => $variantId,
            "inventory_quantity" => $qty,
            "inventory_management" => "shopify"
        ]
    ]);

    $ch = curl_init($updateUrl);
    curl_setopt($ch, CURLOPT_USERPWD, "$shopifyApiKey:$shopifyApiPassword");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $updatePayload);

    $updateResponse = curl_exec($ch);

    if ($updateResponse === false) {
        logMsg("❌ Failed to update SKU $sku: " . curl_error($ch));
        curl_close($ch);
        continue;
    }

    $updateHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($updateHttpCode === 200) {
        logMsg("✅ Updated SKU $sku quantity to $qty");
        $updatedCount++;
    } else {
        logMsg("❌ Shopify update failed for SKU $sku with HTTP code $updateHttpCode");
    }
}

logMsg("Sync complete. Updated $updatedCount product variants.");

echo "✅ Inventory sync finished. Updated $updatedCount products.\n";
