<?php

namespace App\Http\Controllers\Concerns;

use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Response;

/**
 * API response helpers, ported from the legacy LMS App\Traits\RespondsWithHttpStatus
 * (success, successWithPagination, failure, validationFailure) onto the shared ApiResponse builder.
 *
 * Used by App\Http\Controllers\Api\V1\ApiController. It is not mixed into the admin base
 * Controller, whose success() serves AJAX/classic admin forms.
 */
trait RespondsWithHttpStatus
{
    protected function success(string $message, mixed $data = null, int $status = Response::HTTP_OK): JsonResponse
    {
        return ApiResponse::success($message, $data, $status);
    }

    protected function successWithPagination(string $message, ResourceCollection $collection, int $status = Response::HTTP_OK): JsonResponse
    {
        return ApiResponse::successWithPagination($message, $collection, $status);
    }

    protected function failure(string $message, int $status = Response::HTTP_BAD_REQUEST, ?string $code = null, mixed $data = null): JsonResponse
    {
        return ApiResponse::failure(request(), $status, $message, $code, null, $data);
    }

    /**
     * @param  array<string, array<int, string>>  $errors
     */
    protected function validationFailure(array $errors, int $status = Response::HTTP_UNPROCESSABLE_ENTITY): JsonResponse
    {
        return ApiResponse::failure(request(), $status, __('api.errors.validation_failed'), 'validation_failed', $errors);
    }
}
