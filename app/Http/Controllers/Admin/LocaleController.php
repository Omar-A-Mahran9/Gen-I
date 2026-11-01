<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Locale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Switch the UI language; remembered in the session and on the user's profile.
     */
    public function __invoke(Request $request, Locale $locales, string $locale): RedirectResponse
    {
        abort_unless($locales->isSupported($locale), 404);

        $request->session()->put('locale', $locale);
        $request->user()?->forceFill(['locale' => $locale])->save();

        return redirect()->back(fallback: route('admin.dashboard'));
    }
}
