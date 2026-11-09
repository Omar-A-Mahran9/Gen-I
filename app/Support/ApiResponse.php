<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * The single place where /api/v1 JSON bodies are built (docs/api-contracts.md §2), following
 * the legacy LMS RespondsWithHttpStatus structure:
 *
 *   Success:    {"success": true,  "data": …, "message": "…"}
 *   Paginated:  {"success": true,  "data": [...], "links": {...}, "meta": {...}, "message": "…"}
 *   Failure:    {"success": false, "message": "…", "code": "…", "errors"?: {...}, "data"?: …, "request_id": "…"}
 *
 * "errors" carries field validation messages (LMS validationFailure). "code" and "request_id"
 * are GEN-I additions required by S1-D2-01 (stable error codes and correlation IDs).
 */
class ApiResponse
{
    /**
     * @param  array<string, string>  $headers
     */
    public static function success(string $message, mixed $data = null, int $status = 200, array $headers = []): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
        ], $status, $headers);
    }

    /**
     * Paginated resource collection: data + Laravel paginator links/meta.
     */
    public static function successWithPagination(string $message, ResourceCollection $collection, int $status = 200): JsonResponse
    {
        $page = $collection->response()->getData(true);

        return response()->json([
            'success' => true,
            'data' => $page['data'] ?? [],
            'links' => $page['links'] ?? null,
            'meta' => $page['meta'] ?? null,
            'message' => $message,
        ], $status);
    }

    /**
     * @param  array<string, array<int, string>>|null  $errors
     * @param  array<string, string>  $headers
     */
    public static function failure(Request $request, int $status, string $message, ?string $code = null, ?array $errors = null, mixed $data = null, array $headers = []): JsonResponse
    {
        return response()->json(array_filter([
            'success' => false,
            'message' => $message,
            'code' => $code ?? self::codeForStatus($status),
            'errors' => $errors,
            'data' => $data,
            'request_id' => $request->attributes->get('request_id'),
        ], fn (mixed $value): bool => $value !== null), $status, $headers);
    }

    /**
     * Stable machine-readable error code for an HTTP status.
     */
    public static function codeForStatus(int $status): string
    {
        return match ($status) {
            400 => 'bad_request',
            401 => 'unauthenticated',
            403 => 'forbidden',
            404 => 'not_found',
            405 => 'method_not_allowed',
            409 => 'conflict',
            413 => 'payload_too_large',
            415 => 'unsupported_media_type',
            422 => 'validation_failed',
            429 => 'rate_limited',
            503 => 'service_unavailable',
            500 => 'server_error',
            default => 'http_error',
        };
    }
}
