<?php

/**
 * Script to fix User import issues in all controllers
 */

$controllers = [
    'app/Http/Controllers/Api/ProductController.php',
    'app/Http/Controllers/Api/TransactionController.php',
    'app/Http/Controllers/Api/PurchaseController.php',
    'app/Http/Controllers/Api/CategoryController.php',
    'app/Http/Controllers/Api/StockTransferController.php',
    'app/Http/Controllers/Api/StockController.php',
    'app/Http/Controllers/Api/UnitController.php',
    'app/Http/Controllers/Api/CustomerController.php',
    'app/Http/Controllers/Api/SupplierController.php',
];

foreach ($controllers as $controller) {
    echo "Processing: $controller\n";

    if (!file_exists($controller)) {
        echo "File not found: $controller\n";
        continue;
    }

    $content = file_get_contents($controller);
    $originalContent = $content;

    // Add User import if not exists
    if (strpos($content, 'use App\\Models\\User;') === false) {
        // Find the last use statement
        $usePattern = '/^use\s+[^;]+;$/m';
        preg_match_all($usePattern, $content, $matches, PREG_OFFSET_CAPTURE);

        if (!empty($matches[0])) {
            $lastUse = end($matches[0]);
            $insertPosition = $lastUse[1] + strlen($lastUse[0]);
            $content = substr_replace($content, "\nuse App\\Models\\User;", $insertPosition, 0);
        }
    }

    if ($content !== $originalContent) {
        file_put_contents($controller, $content);
        echo "Fixed: $controller\n";
    } else {
        echo "No changes needed: $controller\n";
    }
}

echo "All User imports fixed!\n";
