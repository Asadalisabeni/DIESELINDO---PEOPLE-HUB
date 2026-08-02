<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $supportedLocales = ['id', 'en'];
        $locale = $request->session()->get('locale', config('app.locale'));

        if (! is_string($locale) || ! in_array($locale, $supportedLocales, true)) {
            $locale = 'id';
            $request->session()->forget('locale');
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
