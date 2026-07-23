<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Builds the project's standard API envelope so every endpoint and error
 * returns the same shape: { success, message, data, meta } or
 * { success:false, message, errors, error_code }.
 */
class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'OK', array $meta = [], int $status = 200): JsonResponse
    {
        $payload = ['success' => true, 'message' => $message, 'data' => $data];
        if (! empty($meta)) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    public static function error(string $message, string $code = 'ERROR', int $status = 400, array $errors = []): JsonResponse
    {
        $payload = ['success' => false, 'message' => $message, 'error_code' => $code];
        if (! empty($errors)) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
