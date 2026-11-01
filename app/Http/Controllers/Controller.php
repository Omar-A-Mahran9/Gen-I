<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Respond to a successful admin action as JSON (AJAX forms/actions)
     * or as a redirect with a flash message (classic form posts).
     *
     * @param  array<string, mixed>  $data
     */
    protected function success(Request $request, string $message, ?string $redirect = null, array $data = []): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(array_merge(['message' => $message, 'redirect' => $redirect], $data));
        }

        return ($redirect !== null ? redirect()->to($redirect) : back())->with('success', $message);
    }
}
