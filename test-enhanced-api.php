<?php

// Test script untuk Enhanced Report API
require_once 'vendor/autoload.php';

use Illuminate\Http\Request;
use App\Http\Controllers\Api\EnhancedReportController;

// Simulate request
$request = new Request([
    'date_from' => '2024-01-01',
    'date_to' => '2024-12-31',
    'period' => 'daily'
]);

// Create controller instance
$controller = new EnhancedReportController();

try {
    $response = $controller->index($request);
    echo "API Response:\n";
    echo json_encode($response->getData(), JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString();
}

