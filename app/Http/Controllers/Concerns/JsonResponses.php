<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;

/**
 * Унифицированные JSON ответы для API контроллеров.
 */
trait JsonResponses
{
    protected function success(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        $response = ['success' => true];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $status);
    }

    protected function created(mixed $data = null, ?string $message = null): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function accepted(mixed $data = null, ?string $message = null): JsonResponse
    {
        return $this->success($data, $message, 202);
    }

    protected function error(string $error, int $status = 400, array $extra = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => $error,
            ...$extra,
        ], $status);
    }

    protected function notFound(string $error = 'Ресурс не найден.'): JsonResponse
    {
        return $this->error($error, 404);
    }

    protected function conflict(string $error, array $extra = []): JsonResponse
    {
        return $this->error($error, 409, $extra);
    }

    protected function unprocessable(string $error): JsonResponse
    {
        return $this->error($error, 422);
    }

    protected function serviceUnavailable(string $error = 'Сервис временно недоступен.'): JsonResponse
    {
        return $this->error($error, 503);
    }
}
