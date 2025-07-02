<?php
/**
 * Honey's Place → Shopify Inventory Sync Script
 */

date_default_timezone_set('UTC');

// Load credentials from environment variables
$hpUsername = getenv('HP_USERNAME');
$hpPassword = getenv('HP_PASSWORD');

$shopifyApiKey = getenv('SHOPIFY_API_KEY');
$shopifyApiPassword = getenv('SHOPIFY_API_PASSWORD');
$shopifyStoreDomain = getenv('SHOPIFY_STORE_DOMAIN');

$skuPrefix = 'HP-'; // Optional: only update SKUs starting with this
$logFile = __DIR__ . '/inventory_sync_log.txt';

file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Starting sync...\n", FILE_APPEND);

// === Step 1: Download Honey’s Place Inventory ===
$url = "https://honeysplace.com/API/inventory/index.php";
$context = stream_context_create([
    "http" => [
        "header" => "Authorization: Basic " . base64_encode("$hpUsername:$hpPassword")
    ]
]);

$xmlString = file_get_contents($url, false, $context);

if (!$xmlString) {
    file_put_contents($logFile, "❌ Failed to download inventory XML.\n", FILE_APPEND);
    exit(1);
}

file_put_contents($logFile, "✅ Downloaded Honey’s Place inventory XML.\n", FILE_APPEND);

// === Step 2: Parse XML ===
$xml = simplexml_load_string($xmlString);
if (!$xml) {
    file_put_contents($logFile, "❌ Failed to parse XML.\n", FILE_APPEND);
    exit(1);
}

$updatedCount = 0;

foreach ($xml->product as $product) {
    $sku = trim((string)$product->sku);
    $qty = (int)$product->qty;

    // Filter: Only update SKUs from Honey’s Place
    if (!str_starts_with($sku, $skuPrefix)) {
        continue;
    }

    // === Step 3: Search product by SKU in Shopify ===
    $searchUrl = "https://$shopifyApiKey:$shopifyApiPassword@$shopifyStoreDomain/admin/api/2024-01/products.json?fields=id,variants&handle=$sku";

    $productData = file_get_contents($searchUrl);
    if (!$productData) {
        file_put_contents($logFile, "⚠️ Could not find product for SKU $sku\n", FILE_APPEND);
        continue;
    }

    $productJson = json_decode($productData, true);
    if (empty($productJson['products'])) {
        file_put_contents($logFile, "⚠️ No product found in Shopify for SKU $sku\n", FILE_APPEND);
        continue;
    }

    foreach ($productJson['products'] as $p) {
        foreach ($p['variants'] as $variant) {
            if ($variant['sku'] === $sku) {
                $variantId = $variant['id'];

                // === Step 4: Update Shopify inventory level ===
                $updateUrl = "https://$shopifyApiKey:$shopifyApiPassword@$shopifyStoreDomain/admin/api/2024-01/variants/$variantId.json";
                $data = json_encode([
                    "variant" => [
                        "id" => $variantId,
                        "inventory_quantity" => $qty,
                        "inventory_management" => "shopify"
                    ]
                ]);

                $opts = [
                    "http" => [
                        "method" => "PUT",
                        "header" => "Content-Type: application/json",
                        "content" => $data
                    ]
                ];
                $context = stream_context_create($opts);
                $result = file_get_contents($updateUrl, false, $context);

                if ($result) {
                    file_put_contents($logFile, "✅ Updated SKU $sku to qty $qty\n", FILE_APPEND);
                    $updatedCount++;
                } else {
                    file_put_contents($logFile, "❌ Failed to update SKU $sku\n", FILE_APPEND);
                }
            }
        }
    }
}

file_put_contents($logFile, "✅ Sync complete. Updated $updatedCount products.\n", FILE_APPEND);
echo "✅ Inventory sync finished. Updated $updatedCount products.\n";
