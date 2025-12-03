<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>503 - Service Unavailable | Kasir POS System</title>
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
                <h1 class="text-9xl font-bold text-gray-300">503</h1>
            </div>
            <div class="mb-6">
                <h2 class="text-3xl font-semibold text-gray-900 mb-2">Service Unavailable</h2>
                <p class="text-gray-600 mb-8">
                    The service is temporarily unavailable. We're working on it and will be back shortly.
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
        </div>
    </div>
</body>
</html>

