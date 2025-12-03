<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>526 - Invalid SSL Certificate | Kasir POS System</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Instrument Sans', sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex items-center justify-center px-4">
        <div class="max-w-md w-full text-center">
            <div class="mb-8">
                <h1 class="text-9xl font-bold text-gray-300">526</h1>
            </div>
            <div class="mb-6">
                <h2 class="text-3xl font-semibold text-gray-900 mb-2">Invalid SSL Certificate</h2>
                <p class="text-gray-600 mb-4">
                    Cloudflare cannot establish an SSL connection to the origin server.
                </p>
                <p class="text-sm text-gray-500 mb-8">
                    This usually means the SSL certificate on the origin server is invalid, expired, or not properly configured.
                </p>
            </div>
            <div class="space-y-4">
                <button onclick="window.location.reload()"
                        class="inline-block px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition-colors">
                    Try Again
                </button>
                <div>
                    <a href="{{ url('/') }}"
                       class="text-gray-600 hover:text-gray-900 underline">
                        Go to Homepage
                    </a>
                </div>
            </div>
            <div class="mt-8 p-4 bg-yellow-50 border border-yellow-200 rounded-lg text-left">
                <p class="text-sm font-semibold text-yellow-800 mb-2">What to do:</p>
                <ul class="text-xs text-yellow-700 space-y-1 list-disc list-inside">
                    <li>Check if SSL certificate is valid and not expired</li>
                    <li>Verify SSL certificate is properly installed on origin server</li>
                    <li>Ensure certificate matches the domain name</li>
                    <li>Contact your hosting provider if the issue persists</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>

