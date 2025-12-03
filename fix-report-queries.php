<?php

/**
 * Script to fix all report queries to handle show_all_data parameter
 */

$file = 'app/Http/Controllers/Api/ReportController.php';
$content = file_get_contents($file);

// Fix purchase queries in profit report
$patterns = [
    // Purchase query in profit report
    [
        'search' => '/\/\/ Calculate purchase costs \(expenses\) from purchases\n\s*\$purchaseQuery = DB::table\(\'purchases\'\)\n\s*->where\(\'status\', \'!=\', \'cancelled\'\)\n\s*->whereBetween\(\'purchase_date\', \[\n\s*\$dateFrom \. \' 00:00:00\',\n\s*\$dateTo \. \' 23:59:59\'\n\s*\]\);/',
        'replace' => '// Calculate purchase costs (expenses) from purchases
        $purchaseQuery = DB::table(\'purchases\')
            ->where(\'status\', \'!=\', \'cancelled\');

        // Apply date filter only if not showing all data
        if (!$request->show_all_data && $dateFrom && $dateTo) {
            $purchaseQuery->whereBetween(\'purchase_date\', [
                $dateFrom . \' 00:00:00\',
                $dateTo . \' 23:59:59\'
            ]);
        }'
    ],

    // COGS query in profit report
    [
        'search' => '/\/\/ Apply date filter\n\s*\$cogsQuery->whereBetween\(\'transactions\.transaction_date\', \[\n\s*\$dateFrom \. \' 00:00:00\',\n\s*\$dateTo \. \' 23:59:59\'\n\s*\]\);/',
        'replace' => '// Apply date filter only if not showing all data
        if (!$request->show_all_data && $dateFrom && $dateTo) {
            $cogsQuery->whereBetween(\'transactions.transaction_date\', [
                $dateFrom . \' 00:00:00\',
                $dateTo . \' 23:59:59\'
            ]);
        }'
    ],

    // Top products query in profit report
    [
        'search' => '/->whereBetween\(\'transactions\.transaction_date\', \[\n\s*\$dateFrom \. \' 00:00:00\',\n\s*\$dateTo \. \' 23:59:59\'\n\s*\]\)/',
        'replace' => '// Apply date filter only if not showing all data
        if (!$request->show_all_data && $dateFrom && $dateTo) {
            $query->whereBetween(\'transactions.transaction_date\', [
                $dateFrom . \' 00:00:00\',
                $dateTo . \' 23:59:59\'
            ]);
        }'
    ]
];

foreach ($patterns as $pattern) {
    $content = preg_replace($pattern['search'], $pattern['replace'], $content);
}

file_put_contents($file, $content);
echo "Report queries fixed!\n";
