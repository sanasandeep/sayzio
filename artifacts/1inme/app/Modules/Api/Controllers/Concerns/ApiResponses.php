<?php

namespace App\Modules\Api\Controllers\Concerns;

use Illuminate\Http\JsonResponse;

trait ApiResponses
{
    protected function ok(mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data], $status);
    }

    protected function created(mixed $data = null): JsonResponse
    {
        return $this->ok($data, 201);
    }

    protected function noContent(): JsonResponse
    {
        return response()->json(null, 204);
    }

    protected function fail(string $message, int $status = 400, ?string $code = null, mixed $details = null): JsonResponse
    {
        $error = ['message' => $message];
        if ($code !== null)    $error['code']    = $code;
        if ($details !== null) $error['details'] = $details;
        return response()->json(['error' => $error], $status);
    }

    protected function notFound(string $message = 'Not found'): JsonResponse
    {
        return $this->fail($message, 404, 'not_found');
    }

    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->fail($message, 403, 'forbidden');
    }

    protected function unauthorized(string $message = 'Unauthorized', ?string $code = null): JsonResponse
    {
        return $this->fail($message, 401, $code ?? 'unauthorized');
    }
}
