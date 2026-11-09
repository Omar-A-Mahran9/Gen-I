<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Auth\IssueTokenRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Personal-access-token authentication for API clients (Sanctum).
 * Separate from the admin dashboard's session login.
 */
class AuthTokenController extends ApiController
{
    public function __construct(protected AuditLogger $audit) {}

    public function store(IssueTokenRequest $request): JsonResponse
    {
        $user = User::query()->where('email', mb_strtolower($request->validated('email')))->first();

        if ($user === null || ! Hash::check($request->validated('password'), $user->password) || ! $user->is_active) {
            throw ValidationException::withMessages(['email' => [__('auth.failed')]]);
        }

        $token = $user->createToken($request->validated('device_name'));

        $this->audit->log('api.token_issued', $user, ['device_name' => $request->validated('device_name')], $user);

        return $this->success(__('api.messages.token_issued'), [
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new UserResource($user->load('roles')),
        ], Response::HTTP_CREATED);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();

        $this->audit->log('api.token_revoked', $user, [], $user);

        return $this->success(__('api.messages.token_revoked'));
    }
}
