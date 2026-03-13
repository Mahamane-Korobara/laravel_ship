<?php

namespace App\Http\Middleware;

use App\Services\InfrastructureService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EnsureInfrastructure
{
    public function handle(Request $request, Closure $next)
    {
        if (!config('ship.allow_infra_setup') || !config('ship.auto_setup_infra')) {
            return $next($request);
        }

        if (Cache::get('ship_infra_setup_done') || Cache::get('ship_infra_setup_running')) {
            return $next($request);
        }

        Cache::put('ship_infra_setup_running', true, now()->addMinutes(10));

        try {
            $service = app(InfrastructureService::class);
            $status = $service->status();
            $needsSetup = collect($status)->contains('error');

            if ($needsSetup) {
                $service->install();
            }

            Cache::put('ship_infra_setup_done', true, now()->addDays(30));
        } catch (\Throwable $e) {
            Log::warning('Auto-setup infra échoué: ' . $e->getMessage());
            Cache::put('ship_infra_setup_failed', true, now()->addMinutes(10));
        } finally {
            Cache::forget('ship_infra_setup_running');
        }

        return $next($request);
    }
}
