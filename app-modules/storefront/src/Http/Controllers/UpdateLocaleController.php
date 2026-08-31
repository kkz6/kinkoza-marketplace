<?php

namespace Kinkoza\Storefront\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class UpdateLocaleController
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        $supported = config('locales.supported', []);

        abort_unless(is_array($supported) && array_key_exists($locale, $supported), 404);

        $request->session()->put('locale', $locale);
        App::setLocale($locale);

        $user = $request->user();

        if ($user instanceof User && $user->preferredLocale() !== $locale) {
            $user->forceFill(['locale' => $locale])->save();
        }

        return back();
    }
}
