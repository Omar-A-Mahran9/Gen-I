<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A business-rule refusal (e.g. last super administrator, invalid status
 * transition, insufficient stock). The message is already translated.
 *
 * Rendering: API → standard error envelope; AJAX → {message}; forms → back with error flash.
 */
class AdminActionException extends Exception
{
    public function __construct(string $message, protected int $status = 422, protected string $errorCode = 'business_rule_violation')
    {
        parent::__construct($message);
    }

    public static function conflict(string $message): self
    {
        return new self($message, 409, 'conflict');
    }

    public function status(): int
    {
        return $this->status;
    }

    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->is('api/*')) {
            return response()->json(['error' => array_filter([
                'code' => $this->errorCode,
                'message' => $this->getMessage(),
                'request_id' => $request->attributes->get('request_id'),
            ])], $this->status);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], $this->status);
        }

        return back()->withInput()->with('error', $this->getMessage());
    }
}
