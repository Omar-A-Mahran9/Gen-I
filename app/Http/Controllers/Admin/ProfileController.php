<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Profile\UpdatePasswordRequest;
use App\Http\Requests\Admin\Profile\UpdateProfileRequest;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(protected AuditLogger $audit) {}

    public function edit(Request $request): View
    {
        return view('admin.profile.edit', [
            'user' => $request->user()->load('roles'),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $user->fill($request->safe()->only(['name', 'email', 'locale']));
        $changed = array_keys($user->getDirty());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($user->wasChanged('locale') && $user->locale !== null) {
            $request->session()->put('locale', $user->locale);
        }

        if ($changed !== []) {
            $this->audit->log('profile.updated', $user, ['changed' => $changed], $user);
        }

        return $this->success($request, __('admin.profile.messages.updated'), route('admin.profile.edit'));
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $user->forceFill(['password' => $request->validated('password')])->save();

        // Sign out other browsers that are using the old password.
        Auth::guard('web')->logoutOtherDevices($request->validated('password'));
        $request->session()->regenerate();

        $this->audit->log('profile.password_changed', $user, [], $user);

        return $this->success($request, __('admin.profile.messages.password_updated'), route('admin.profile.edit'));
    }
}
