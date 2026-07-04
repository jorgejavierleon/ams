<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Apply the request's preferred locale for the remainder of the request.
     *
     * Resolution order: the locale persisted in the session (set by the locale
     * switch route), falling back to the application default. Only locales
     * declared in config('localization.supported') are honoured.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get(
            config('localization.session_key'),
            config('app.locale'),
        );

        if (array_key_exists($locale, config('localization.supported'))) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
