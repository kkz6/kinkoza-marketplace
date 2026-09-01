<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;
use UnexpectedValueException;

class SetLocale
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $supportedLocales = config('locales.supported', []);

        if (! is_array($supportedLocales)) {
            throw new UnexpectedValueException('Supported locales configuration must be an array.');
        }

        $supported = array_keys($supportedLocales);
        $locale = $request->session()->get('locale');

        if (! is_string($locale) || ! in_array($locale, $supported, true)) {
            $user = $request->user();
            $locale = $user instanceof User ? $user->preferredLocale() : config('app.locale');
        }

        if (is_string($locale) && in_array($locale, $supported, true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
