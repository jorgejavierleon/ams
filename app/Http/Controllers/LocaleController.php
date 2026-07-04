<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Persist the requested locale in the session and return to the previous page.
     *
     * The locale takes effect on the next request via the SetLocale middleware.
     * Unsupported locales are rejected with a 404.
     */
    public function update(Request $request, string $locale): RedirectResponse
    {
        abort_unless(array_key_exists($locale, config('localization.supported')), 404);

        $request->session()->put(config('localization.session_key'), $locale);

        return back();
    }
}
