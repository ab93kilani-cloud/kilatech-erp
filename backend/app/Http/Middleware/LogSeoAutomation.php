<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogSeoAutomation
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // On log uniquement si l'utilisateur est le bot n8n ou si c'est une route SEO
        $user = $request->user();
        if ($user && $user->name === 'n8n_bot') {
            Log::channel('daily')->info('SEO_BOT_REQUEST', [
                'id' => $user->id,
                'email' => $user->email,
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'payload' => $request->except(['password', 'password_hash']),
                'response_status' => $response->status(),
                'ip' => $request->ip()
            ]);
        }

        return $response;
    }
}
