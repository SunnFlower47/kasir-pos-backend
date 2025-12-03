<?php

/**
 * Script to fix all type hints in controllers
 */

$controllers = glob('app/Http/Controllers/Api/*.php');

foreach ($controllers as $controller) {
    echo "Processing: $controller\n";

    $content = file_get_contents($controller);
    $originalContent = $content;

    // Add User import if not exists
    if (strpos($content, 'use App\\Models\\User;') === false && strpos($content, 'Auth::user()') !== false) {
        // Find the last use statement
        $usePattern = '/^use\s+[^;]+;$/m';
        preg_match_all($usePattern, $content, $matches, PREG_OFFSET_CAPTURE);

        if (!empty($matches[0])) {
            $lastUse = end($matches[0]);
            $insertPosition = $lastUse[1] + strlen($lastUse[0]);
            $content = substr_replace($content, "\nuse App\\Models\\User;", $insertPosition, 0);
        }
    }

    // Fix type hints - replace /** @var User $user */ with proper format
    $content = preg_replace(
        '/(\s+)\/\*\*\s*@var\s+User\s+\$user\s+\*\/\s*\n\s*(\$user = Auth::user\(\);)/',
        "$1/** @var User \$user */\n$1$2",
        $content
    );

    // Add type hint if not exists
    if (strpos($content, '/** @var User $user */') === false) {
        $content = preg_replace(
            '/(\s+)(\$user = Auth::user\(\);)/',
            "$1/** @var User \$user */\n$1$2",
            $content
        );
    }

    if ($content !== $originalContent) {
        file_put_contents($controller, $content);
        echo "Fixed: $controller\n";
    } else {
        echo "No changes needed: $controller\n";
    }
}

echo "All type hints fixed!\n";
