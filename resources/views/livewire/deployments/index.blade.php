@php
    $statusStyles = [
        'success' => 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
        'failed' => 'bg-rose-500/15 text-rose-300 border-rose-500/30',
        'rolled_back' => 'bg-orange-500/15 text-orange-300 border-orange-500/30',
    ];
    $triggerStyles = [
        'manual' => 'bg-slate-500/15 text-slate-300 border-slate-500/30',
        'webhook' => 'bg-purple-500/15 text-purple-300 border-purple-500/30',
    ];
@endphp

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-white">Déploiements</h1>
        <p class="text-sm text-slate-400">{{ $deployments->count() }} déploiement{{ $deployments->count() > 1 ? 's' : '' }} au total</p>
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="relative w-full sm:max-w-md">
            <x-icon name="lucide-search" class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
            <input
                wire:model.live.debounce.300ms="search"
                type="text"
                placeholder="Rechercher..."
                class="w-full rounded-xl border border-slate-800 bg-slate-900/60 pl-9 pr-3 py-2 text-sm text-slate-100 placeholder-slate-500 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500/50" />
        </div>

        <x-ui.select
            wire:model.live="status"
            icon="lucide-filter"
            :options="['all' => 'Tous', 'success' => 'Succès', 'failed' => 'Échoué', 'rolled_back' => 'Retour arrière']"
        />
    </div>

    <div class="space-y-3">
        @forelse ($deployments as $deployment)
            @php
                $statusBadge = $statusStyles[$deployment->status] ?? 'bg-slate-700/40 text-slate-300 border-slate-600/30';
                $trigger = $deployment->triggered_by ?: 'manual';
                $triggerBadge = $triggerStyles[$trigger] ?? 'bg-slate-500/15 text-slate-300 border-slate-500/30';
                $triggerLabel = match ($trigger) {
                    'manual' => 'Manuel',
                    'webhook' => 'Webhook',
                    default => ucfirst($trigger),
                };
                $projectName = $deployment->project?->name ?? 'Projet';
                $commit = $deployment->git_commit ? substr($deployment->git_commit, 0, 7) : '—';
            @endphp
            <a href="{{ route('deployments.show', $deployment) }}" wire:navigate class="flex flex-col gap-4 rounded-2xl border border-slate-800 bg-slate-900/60 p-4 transition hover:bg-slate-900/80 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600/15 text-indigo-300">
                        <x-icon name="lucide-activity" class="h-5 w-5" />
                    </div>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-sm font-semibold text-white">{{ $projectName }}</p>
                            <span class="text-xs text-slate-400">{{ $deployment->release_name }}</span>
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-3 text-xs text-slate-400">
                            <span class="inline-flex items-center gap-1">
                                <x-icon name="lucide-git-branch" class="h-3.5 w-3.5" />
                                {{ $deployment->git_branch }}
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <x-icon name="lucide-git-commit" class="h-3.5 w-3.5" />
                                {{ $commit }}
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <x-icon name="lucide-clock" class="h-3.5 w-3.5" />
                                {{ $deployment->duration_human }}
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <x-icon name="lucide-calendar" class="h-3.5 w-3.5" />
                                {{ $deployment->created_at?->format('d M Y, H:i') }}
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-medium {{ $statusBadge }}">
                        {{ $deployment->status_label }}
                    </span>
                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-[11px] font-medium {{ $triggerBadge }}">
                        {{ $triggerLabel }}
                    </span>
                </div>
            </a>
        @empty
            <x-ui.card class="rounded-2xl border border-slate-800 bg-slate-900/60 p-8 text-center text-sm text-slate-500">
                Aucun déploiement trouvé.
            </x-ui.card>
        @endforelse
    </div>
</div>
