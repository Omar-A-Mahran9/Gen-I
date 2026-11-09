<?php

namespace App\Exceptions;

use App\Support\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A business-rule refusal (e.g. last super administrator, invalid status
 * transition, insufficient stock). The message is already translated.
 *
 * Rendering: API → standard failure body; AJAX → {message}; forms → back with error flash.
 */
class AdminActionException extends Exception
{
    /**
     * @param  mixed  $data  optional structured context returned as "data" in the API failure body
     */
    public function __construct(string $message, protected int $status = 422, protected string $errorCode = 'business_rule_violation', protected mixed $data = null)
    {
        parent::__construct($message);
    }

    public static function conflict(string $message): self
    {
        return new self($message, 409, 'conflict');
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function data(): mixed
    {
        return $this->data;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->is('api/*')) {
            return ApiResponse::failure($request, $this->status, $this->getMessage(), $this->errorCode, null, $this->data);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], $this->status);
        }

        return back()->withInput()->with('error', $this->getMessage());
    }
}
