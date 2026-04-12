@php
$statusBg = match ($project->status) {
'deployed' => 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30',
'deploying' => 'bg-blue-500/20 text-blue-400 border border-blue-500/30',
'failed' => 'bg-red-500/20 text-red-400 border border-red-500/30',
default => 'bg-slate-700/40 text-slate-400 border border-slate-600/30',
};
$statusDot = match ($project->status) {
'deployed' => 'bg-emerald-400',
'deploying' => 'bg-blue-400',
'failed' => 'bg-red-400',
'idle' => 'bg-slate-500',
default => 'bg-slate-500',
};
@endphp

<div class="space-y-6">
    {{-- Back link --}}
    <a href="{{ route('projects.show', $project) }}" wire:navigate class="inline-flex items-center gap-2 text-sm text-slate-400 hover:text-white transition">
        <x-icon name="lucide-arrow-left" class="h-4 w-4" />
        Retour au projet
    </a>

    {{-- Header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="flex items-start gap-3 sm:gap-4">
            <div class="flex h-11 w-11 items-center justify-center rounded-lg bg-indigo-600/80 shadow-lg sm:h-12 sm:w-12">
                <x-icon name="lucide-rocket" class="h-6 w-6 text-white" />
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-2xl font-bold text-white">Lancer le déploiement</h1>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusBg }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $statusDot }}"></span>
                        {{ $project->status_label }}
                    </span>
                </div>
                <p class="mt-1 text-sm text-slate-400">{{ $project->name }} • {{ $project->github_branch }}</p>
            </div>
        </div>
    </div>

    {{-- Form --}}
    <form wire:submit="deploy" class="space-y-6">
        {{-- Configuration de base --}}
        <section class="space-y-4">
            <div class="flex items-center gap-3 mb-4">
                <div class="flex h-8 w-8 items-center justify-center rounded-md bg-slate-800">
                    <x-icon name="lucide-settings" class="h-4 w-4 text-slate-400" />
                </div>
                <h2 class="text-lg font-semibold text-white">Configuration de base</h2>
            </div>

            <div class="rounded-lg border border-slate-800 bg-slate-900/50 p-6 space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    {{-- Nom du projet --}}
                    <div>
                        <label class="text-sm font-medium text-slate-300 mb-1.5 block">Nom du projet</label>
                        <x-ui.input wire:model.defer="name" placeholder="Mon projet" />
                        @error('name') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Serveur --}}
                    <div>
                        <label class="text-sm font-medium text-slate-300 mb-1.5 block">Serveur</label>
                        <x-ui.select
                            wire:model.live="server_id"
                            :options="collect($servers)->mapWithKeys(fn($s) => [$s->id => $s->name . ' (' . $s->masked_ip . ')'])->toArray()" />
                        @error('server_id') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Branche Git --}}
                    <div>
                        <label class="text-sm font-medium text-slate-300 mb-1.5 block">Branche Git</label>
                        <div class="flex gap-2">
                            <div class="flex-1">
                                <x-ui.select
                                    wire:model.defer="github_branch"
                                    :options="collect($branches)->mapWithKeys(fn($b) => [$b => $b])->toArray()" />
                            </div>
                            <x-ui.button type="button" wire:click="loadBranches" wire:loading.attr="disabled" wire:target="loadBranches" variant="secondary" size="md" class="whitespace-nowrap">
                                <span wire:loading.remove wire:target="loadBranches" class="inline-flex items-center gap-2">
                                    <x-icon name="lucide-refresh-cw" class="h-4 w-4" />
                                    Sync
                                </span>
                                <span wire:loading wire:target="loadBranches" class="inline-flex items-center gap-2">
                                    <x-ui.spinner size="sm" />
                                    Sync...
                                </span>
                            </x-ui.button>
                        </div>
                    </div>

                    {{-- Domaine --}}
                    <div>
                        <label class="text-sm font-medium text-slate-300 mb-1.5 block">Domaine</label>
                        <x-ui.input wire:model.defer="domain" placeholder="api.example.com" />
                    </div>
                </div>

                {{-- Options de déploiement --}}
                <div class="border-t border-slate-800 pt-4">
                    <p class="text-sm text-slate-400 mb-3">Options du déploiement</p>
                    <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                        <label class="flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-800 hover:border-slate-700 cursor-pointer transition">
                            <input type="checkbox" wire:model="run_migrations" class="rounded border-slate-700 bg-slate-900 text-emerald-600">
                            <span class="text-sm text-slate-300">Migrations</span>
                        </label>
                        <label class="flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-800 hover:border-slate-700 cursor-pointer transition">
                            <input type="checkbox" wire:model="run_seeders" class="rounded border-slate-700 bg-slate-900 text-emerald-600">
                            <span class="text-sm text-slate-300">Données d'initialisation</span>
                        </label>
                        <label class="flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-800 hover:border-slate-700 cursor-pointer transition">
                            <input type="checkbox" wire:model="run_npm_build" class="rounded border-slate-700 bg-slate-900 text-emerald-600">
                            <span class="text-sm text-slate-300">Compiler les ressources</span>
                        </label>
                        <label class="flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-800 hover:border-slate-700 cursor-pointer transition">
                            <input type="checkbox" wire:model="has_queue_worker" class="rounded border-slate-700 bg-slate-900 text-emerald-600">
                            <span class="text-sm text-slate-300">Worker de file</span>
                        </label>
                    </div>
                </div>
            </div>
        </section>

        {{-- Audit Docker --}}
        <section class="space-y-4">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-md bg-slate-800">
                        <x-icon name="lucide-code" class="h-4 w-4 text-slate-400" />
                    </div>
                    <h2 class="text-lg font-semibold text-white">Audit Docker</h2>
                </div>
                <x-ui.button type="button" wire:click="runDependencyAudit"
                    wire:loading.attr="disabled"
                    wire:target="runDependencyAudit"
                    variant="secondary"
                    size="sm"
                    :disabled="!$server_id">
                    <span wire:loading.remove wire:target="runDependencyAudit" class="inline-flex items-center gap-2">
                        <x-icon name="lucide-activity" class="h-4 w-4" />
                        Analyser
                    </span>
                    <span wire:loading wire:target="runDependencyAudit" class="inline-flex items-center gap-2">
                        <x-ui.spinner size="sm" />
                        Analyse...
                    </span>
                </x-ui.button>

                @if ($server_id)
                @php
                $selectedServer = collect($servers)->firstWhere('id', $server_id);
                $serverName = $selectedServer ? $selectedServer->name : 'Serveur inconnu';
                @endphp
                <p class="text-xs text-slate-400 mt-3">
                    Audit sur : <span class="text-indigo-400 font-semibold">{{ $serverName }}</span>
                </p>
                @else
                <p class="text-xs text-rose-400 mt-3">
                    ⚠️ Sélectionne un serveur pour auditer ses dépendances
                </p>
                @endif
            </div>

            <div class="rounded-lg border border-slate-800 bg-slate-900/50 p-6">
                @if ($auditError)
                <div class="rounded-lg border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-300 flex items-start gap-2">
                    <x-icon name="lucide-alert-triangle" class="h-5 w-5 flex-shrink-0 mt-0.5" />
                    <div>{{ $auditError }}</div>
                </div>
                @elseif ($auditRunning)
                <div class="rounded-lg border border-blue-500/30 bg-blue-500/10 px-4 py-3 text-sm text-blue-300 flex items-start gap-2">
                    <x-ui.spinner size="sm" class="flex-shrink-0 mt-0.5" />
                    <div>Analyse en cours...</div>
                </div>
                @elseif (empty($dependencyAudit))
                <p class="text-sm text-slate-400">Vérifie que Docker est disponible sur le VPS avant le déploiement.</p>
                @else
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($dependencyAudit as $item)
                    @php
                    $isOk = $item['status'] === 'ok';
                    $borderClass = $isOk ? 'border-emerald-500/30' : 'border-rose-500/30';
                    $bgClass = $isOk ? 'bg-emerald-500/10' : 'bg-rose-500/10';
                    $badgeClass = $isOk ? 'bg-emerald-600 text-emerald-50' : 'bg-rose-600 text-rose-50';
                    $label = $isOk ? 'OK' : 'Manquant';
                    @endphp
                    <div class="rounded-lg border {{ $borderClass }} {{ $bgClass }} p-4 flex items-center justify-between">
                        <div>
                            <div class="text-sm font-medium text-white">{{ $item['label'] }}</div>
                            <div class="text-xs text-slate-400">{{ $item['identifier'] }}</div>
                        </div>
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $badgeClass }}">{{ $label }}</span>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </section>

        {{-- Checklist CD + SSL --}}
        <section class="space-y-4">
            <div class="flex items-center gap-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-md bg-slate-800">
                    <x-icon name="lucide-check-circle" class="h-4 w-4 text-slate-400" />
                </div>
                <h2 class="text-lg font-semibold text-white">Checklist avant déploiement</h2>
            </div>

            <div class="rounded-lg border border-slate-800 bg-slate-900/50 p-6 space-y-4">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="rounded-lg border border-slate-700/50 p-4 space-y-2">
                        <div class="text-sm font-semibold text-white flex items-center gap-2">
                            <x-icon name="lucide-git-branch" class="h-4 w-4 text-indigo-400" />
                            Déploiement continu (CD)
                        </div>
                        <ul class="text-xs text-slate-400 space-y-1">
                            <li class="flex gap-2"><span class="text-slate-600">1.</span> Repo + branche corrects et accessibles</li>
                            <li class="flex gap-2"><span class="text-slate-600">2.</span> Webhook GitHub activé sur le projet</li>
                            <li class="flex gap-2"><span class="text-slate-600">3.</span> Variables .env complètes et à jour</li>
                            <li class="flex gap-2"><span class="text-slate-600">4.</span> Docker et Docker Compose validés</li>
                        </ul>
                    </div>

                    <div class="rounded-lg border border-slate-700/50 p-4 space-y-2">
                        <div class="text-sm font-semibold text-white flex items-center gap-2">
                            <x-icon name="lucide-shield" class="h-4 w-4 text-green-400" />
                            SSL automatique
                        </div>
                        <ul class="text-xs text-slate-400 space-y-1">
                            <li class="flex gap-2"><span class="text-slate-600">1.</span> Domaine renseigné dans le projet</li>
                            <li class="flex gap-2"><span class="text-slate-600">2.</span> DNS A/AAAA pointent vers le VPS</li>
                            <li class="flex gap-2"><span class="text-slate-600">3.</span> Ports 80 et 443 ouverts</li>
                            <li class="flex gap-2"><span class="text-slate-600">4.</span> Certificat Let's Encrypt auto-généré</li>
                        </ul>
                    </div>
                </div>

                <div class="rounded-lg border border-blue-500/30 bg-blue-500/10 p-3 text-xs text-blue-300 flex gap-2">
                    <x-icon name="lucide-info" class="h-4 w-4 flex-shrink-0 mt-0.5" />
                    <div><strong>Test local :</strong> Utilise une VM (Multipass, VirtualBox, Vagrant) avec Docker, puis indique son IP comme serveur.</div>
                </div>
            </div>
        </section>

        {{-- Variables d'environnement --}}
        <section class="space-y-4">
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-8 w-8 items-center justify-center rounded-md bg-slate-800">
                        <x-icon name="lucide-settings" class="h-4 w-4 text-slate-400" />
                    </div>
                    <h2 class="text-lg font-semibold text-white">Variables d'environnement</h2>
                </div>
                <x-ui.button type="button" wire:click="addEnvVar" wire:loading.attr="disabled" wire:target="addEnvVar" variant="secondary" size="sm">
                    <span wire:loading.remove wire:target="addEnvVar" class="inline-flex items-center gap-2">
                        <x-icon name="lucide-plus" class="h-4 w-4" />
                        Ajouter variable
                    </span>
                    <span wire:loading wire:target="addEnvVar" class="inline-flex items-center gap-2">
                        <x-ui.spinner size="sm" />
                        Ajout...
                    </span>
                </x-ui.button>
            </div>

            <div class="rounded-lg border border-slate-800 bg-slate-900/50 p-6 space-y-4">
                <div class="border-b border-slate-800 pb-4">
                    <livewire:deployments.upload-deployment-env />
                </div>

                <div @if ($deploymentEnvFilePath) style="opacity:.55;pointer-events:none" @endif class="space-y-3">
                    @if ($deploymentEnvFilePath)
                    <div class="rounded-lg border border-blue-500/30 bg-blue-500/10 px-4 py-3 text-xs text-blue-300 flex items-start gap-2">
                        <x-icon name="lucide-info" class="h-4 w-4 flex-shrink-0 mt-0.5" />
                        <div>Fichier .env importé. Les variables manuelles seront ignorées.</div>
                    </div>
                    @endif

                    @foreach ($envVars as $index => $var)
                    <div class="grid gap-3 md:grid-cols-[1fr_1fr_120px_auto]">
                        <x-ui.input wire:model.defer="envVars.{{ $index }}.key" placeholder="APP_NAME" type="text" />
                        <x-ui.input wire:model.defer="envVars.{{ $index }}.value" placeholder="Valeur" type="text" />
                        <label class="flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-800 hover:border-slate-700 cursor-pointer transition">
                            <input type="checkbox" wire:model="envVars.{{ $index }}.is_secret" class="rounded border-slate-700 bg-slate-900 text-emerald-600">
                            <span class="text-sm text-slate-300">Secret</span>
                        </label>
                        <x-ui.button type="button" wire:click="removeEnvVar({{ $index }})" wire:loading.attr="disabled" wire:target="removeEnvVar" variant="danger" size="md">
                            <span wire:loading.remove wire:target="removeEnvVar" class="inline-flex items-center gap-2">
                                <x-icon name="lucide-trash-2" class="h-4 w-4" />
                            </span>
                            <span wire:loading wire:target="removeEnvVar" class="inline-flex items-center gap-2">
                                <x-ui.spinner size="sm" />
                            </span>
                        </x-ui.button>
                    </div>
                    @endforeach
                </div>

                @if ($errors->has('envVars.*.key'))
                <p class="text-xs text-rose-400">{{ $errors->first('envVars.*.key') }}</p>
                @endif
                @if ($errors->has('envVars.*.value'))
                <p class="text-xs text-rose-400">{{ $errors->first('envVars.*.value') }}</p>
                @endif
            </div>
        </section>

        {{-- Actions --}}
        <div class="flex gap-3 pt-4">
            <x-ui.button href="{{ route('projects.show', $project) }}" wire:navigate variant="secondary" size="md">
                <x-icon name="lucide-x" class="h-4 w-4" />
                Annuler
            </x-ui.button>
            <x-ui.button type="submit" variant="indigo" size="md" wire:loading.attr="disabled" wire:target="deploy" class="flex-1">
                <span wire:loading.remove wire:target="deploy" class="inline-flex items-center justify-center gap-2">
                    <x-icon name="lucide-rocket" class="h-4 w-4" />
                    Déployer maintenant
                </span>
                <span wire:loading wire:target="deploy" class="inline-flex items-center justify-center gap-2">
                    <x-ui.spinner size="sm" />
                    Déploiement en cours...
                </span>
            </x-ui.button>
        </div>
    </form>
</div>