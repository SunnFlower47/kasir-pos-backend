<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\QueryException;
use Throwable;

class ApiExceptionHandler
{
    public static function handle(Throwable $e, Request $request): ?JsonResponse
    {
        // 404 - Model Not Found
        if ($e instanceof ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'message' => 'Resource not found',
                'error' => 'The requested resource does not exist',
            ], 404);
        }

        // 404 - Route Not Found
        if ($e instanceof NotFoundHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Endpoint not found',
                'error' => 'The requested endpoint does not exist',
                'path' => $request->path(),
            ], 404);
        }

        // 405 - Method Not Allowed
        if ($e instanceof MethodNotAllowedHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Method not allowed',
                'error' => 'The HTTP method is not allowed for this endpoint',
                'method' => $request->method(),
                'path' => $request->path(),
            ], 405);
        }

        // 401 - Authentication Error
        if ($e instanceof AuthenticationException || $e instanceof UnauthorizedHttpException) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
                'error' => 'You must be authenticated to access this resource',
            ], 401);
        }

        // 403 - Authorization Error
        if ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
                'error' => 'You do not have permission to perform this action',
            ], 403);
        }

        // 422 - Validation Error
        if ($e instanceof ValidationException) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'error' => 'The given data was invalid',
                'errors' => $e->errors(),
            ], 422);
        }

        // Database Errors
        if ($e instanceof QueryException) {
            return self::handleDatabaseError($e);
        }

        // 500 - General Server Error
        return self::handleServerError($e);
    }

    private static function handleDatabaseError(QueryException $e): JsonResponse
    {
        $errorMessage = $e->getMessage();

        // Don't expose database details in production
        if (app()->environment('production')) {
            $errorMessage = 'A database error occurred. Please try again later.';
        }

        // Handle specific database errors
        if (str_contains($errorMessage, 'SQLSTATE[23000]')) {
            return response()->json([
                'success' => false,
                'message' => 'Database constraint violation',
                'error' => 'The operation violates database constraints',
            ], 409); // Conflict
        }

        if (str_contains($errorMessage, 'SQLSTATE[42S02]')) {
            return response()->json([
                'success' => false,
                'message' => 'Database table not found',
                'error' => 'A required database table does not exist',
            ], 500);
        }

        if (str_contains($errorMessage, 'SQLSTATE[HY000]')) {
            return response()->json([
                'success' => false,
                'message' => 'Database connection error',
                'error' => app()->environment('production')
                    ? 'Unable to connect to database'
                    : $errorMessage,
            ], 500);
        }

        if (str_contains($errorMessage, 'SQLSTATE[42000]')) {
            return response()->json([
                'success' => false,
                'message' => 'Database syntax error',
                'error' => app()->environment('production')
                    ? 'A database query error occurred'
                    : $errorMessage,
            ], 500);
        }

        return response()->json([
            'success' => false,
            'message' => 'Database error',
            'error' => app()->environment('production')
                ? 'A database error occurred. Please try again later.'
                : $errorMessage,
        ], 500);
    }

    private static function handleServerError(Throwable $e): JsonResponse
    {
        // Safely get status code if available
        $statusCode = 500;
        if ($e instanceof HttpException) {
            $statusCode = $e->getStatusCode();
        }

        $response = [
            'success' => false,
            'message' => 'Server error',
            'error' => app()->environment('production')
                ? 'An error occurred while processing your request'
                : $e->getMessage(),
        ];

        // Include debug info in development
        if (app()->environment('local', 'development')) {
            $response['debug'] = [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ];
        }

        return response()->json($response, $statusCode);
    }
}

