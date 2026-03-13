@php
$items = [
    'redis' => 'Redis',
    'supervisor' => 'Supervisor',
    'reverb' => 'Reverb (WebSocket)',
    'queue' => 'Worker de file',
    'apache_ws' => 'Proxy WS Apache',
    'cron' => 'Cron (schedule:run)',
];
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-white">Infrastructure</h1>
            <p class="mt-1 text-sm text-slate-400">Configuration automatique Reverb + file + Apache WS + cron.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.button type="button" wire:click="refreshStatus" variant="default" size="sm">
                <x-icon name="lucide-refresh-cw" class="h-4 w-4" />
                Vérifier
            </x-ui.button>
            <x-ui.button type="button" wire:click="install" variant="danger" size="sm" :disabled="$running">
                <x-icon name="lucide-settings" class="h-4 w-4" />
                Configurer automatiquement
            </x-ui.button>
        </div>
    </div>

    @if (!config('ship.allow_infra_setup'))
        <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
            Active <span class="font-mono">SHIP_ALLOW_INFRA_SETUP=true</span> dans le fichier .env pour autoriser l’installation.
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($items as $key => $label)
            @php
                $state = $status[$key] ?? 'error';
                $badge = match ($state) {
                    'ok' => 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30',
                    'error' => 'bg-rose-500/15 text-rose-300 border border-rose-500/30',
                    default => 'bg-slate-700/40 text-slate-300 border border-slate-600/30',
                };
                $text = $state === 'ok' ? 'Actif' : ($state === 'error' ? 'Inactif' : 'Inconnu');
            @endphp
            <x-ui.card class="rounded-xl border border-slate-800 bg-[#131525] p-4">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-white">{{ $label }}</span>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge }}">
                        {{ $text }}
                    </span>
                </div>
            </x-ui.card>
        @endforeach
    </div>
    <p class="text-xs text-slate-500">Le cron lance <span class="font-mono">schedule:run</span> chaque minute pour les sauvegardes programmées.</p>

    <div class="rounded-xl border border-slate-800 bg-[#131525] overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-800">
            <h2 class="text-sm font-semibold text-white">Logs d’installation</h2>
        </div>
        <div class="px-5 py-4 text-xs text-slate-300 font-mono whitespace-pre-wrap">
            @if ($running)
                <div class="text-slate-400">Installation en cours…</div>
            @elseif (empty($logs))
                <div class="text-slate-500">Aucun log pour le moment.</div>
            @else
                @foreach ($logs as $line)
                    <div>{{ $line }}</div>
                @endforeach
            @endif
        </div>
    </div>
</div>
