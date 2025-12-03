<?php

/**
 * Script to fix all controller method_exists issues
 */

$controllers = glob('app/Http/Controllers/Api/*.php');

foreach ($controllers as $controller) {
    echo "Processing: $controller\n";

    $content = file_get_contents($controller);
    $originalContent = $content;

    // Fix method_exists checks for can()
    $content = preg_replace(
        '/if\s*\(\s*!\s*\$user\s*\|\|\s*!\s*method_exists\s*\(\s*\$user\s*,\s*[\'"]can[\'"]\s*\)\s*\|\|\s*!\s*\$user->can\(/',
        'if (!$user || !$user->can(',
        $content
    );

    // Fix method_exists checks for hasRole()
    $content = preg_replace(
        '/if\s*\(\s*!\s*\$user\s*\|\|\s*!\s*method_exists\s*\(\s*\$user\s*,\s*[\'"]hasRole[\'"]\s*\)\s*\|\|\s*!\s*\$user->hasRole\(/',
        'if (!$user || !$user->hasRole(',
        $content
    );

    // Add User import if not exists and if file uses Auth::user()
    if (strpos($content, 'Auth::user()') !== false && strpos($content, 'use App\\Models\\User;') === false) {
        $content = preg_replace(
            '/(use App\\Http\\Controllers\\Controller;)/',
            "$1\nuse App\\Models\\User;",
            $content
        );
    }

    // Add type hint for user variable if not exists
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

echo "All controllers processed!\n";
