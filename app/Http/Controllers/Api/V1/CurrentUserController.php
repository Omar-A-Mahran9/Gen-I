<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrentUserController extends ApiController
{
    public function __invoke(Request $request): JsonResponse
    {
        return $this->success(__('api.messages.retrieved'), new UserResource($request->user()->load('roles')));
    }
}
