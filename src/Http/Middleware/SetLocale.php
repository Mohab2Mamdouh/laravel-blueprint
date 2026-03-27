<?php

namespace Mohab\LaravelBlueprint\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class SetLocale
{
    /**
     * Handle an incoming request by setting the application locale from the session.
     */
    public function handle(Request $request, Closure $next)
    {
        $locale = Session::get('locale', config('app.locale'));

        $supportedLocales = config('blueprint.supported_locales', ['en', 'ar']);

        if (in_array($locale, $supportedLocales)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
