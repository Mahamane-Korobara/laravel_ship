<div class="space-y-6">
    <div><h1 class="text-3xl font-bold">Lancer le déploiement</h1><p class="text-sm text-[#8ea2c5]">Orchestrez votre mise en production en mode centre de contrôle.</p></div>
    <form wire:submit="deploy" class="space-y-5">
        <section class="ship-panel p-5 space-y-4">
            <h2 class="text-lg font-semibold">Configuration de base</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <div><label class="text-xs text-[#8ea2c5]">Nom du projet</label><input wire:model.defer="name" class="mt-1 w-full rounded-xl border border-[#2f3f61] bg-[#0b1426] px-3 py-2"/>@error('name')<p class="text-xs text-rose-300 mt-1">{{ $message }}</p>@enderror</div>
                <div>
                    <label class="text-xs text-[#8ea2c5]">Serveur</label>
                    <div class="mt-1">
                        <x-ui.select
                            wire:model.defer="server_id"
                            :options="collect($servers)->mapWithKeys(fn($s) => [$s->id => $s->name . ' (' . $s->masked_ip . ')'])->toArray()"
                        />
                    </div>
                    @error('server_id')<p class="text-xs text-rose-300 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-xs text-[#8ea2c5]">Branche Git</label>
                    <div class="mt-1 flex gap-2">
                        <x-ui.select
                            wire:model.defer="github_branch"
                            :options="collect($branches)->mapWithKeys(fn($b) => [$b => $b])->toArray()"
                        />
                        <button type="button" wire:click="loadBranches" class="ship-btn">Synchroniser</button>
                    </div>
                </div>
                <div>
                    <label class="text-xs text-[#8ea2c5]">PHP</label>
                    <div class="mt-1">
                        <x-ui.select
                            wire:model.defer="php_version"
                            :options="['7.4' => '7.4', '8.0' => '8.0', '8.1' => '8.1', '8.2' => '8.2', '8.3' => '8.3', '8.4' => '8.4']"
                        />
                    </div>
                </div>
                <div class="md:col-span-2"><label class="text-xs text-[#8ea2c5]">Domaine</label><input wire:model.defer="domain" placeholder="api.example.com" class="mt-1 w-full rounded-xl border border-[#2f3f61] bg-[#0b1426] px-3 py-2"/></div>
            </div>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4 text-sm">
                <label class="ship-panel flex items-center gap-2 px-3 py-2"><input type="checkbox" wire:model="run_migrations" class="rounded border-[#2f3f61] bg-[#0b1426]">Migrations</label>
                <label class="ship-panel flex items-center gap-2 px-3 py-2"><input type="checkbox" wire:model="run_seeders" class="rounded border-[#2f3f61] bg-[#0b1426]">Données d'initialisation</label>
                <label class="ship-panel flex items-center gap-2 px-3 py-2"><input type="checkbox" wire:model="run_npm_build" class="rounded border-[#2f3f61] bg-[#0b1426]">Compiler les ressources</label>
                <label class="ship-panel flex items-center gap-2 px-3 py-2"><input type="checkbox" wire:model="has_queue_worker" class="rounded border-[#2f3f61] bg-[#0b1426]">Worker de file</label>
            </div>
        </section>

        <section class="ship-panel p-5 space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">Audit des dépendances</h2>
                <button type="button" wire:click="runDependencyAudit" class="ship-btn">Analyser</button>
            </div>
            @if ($auditError)
                <div class="ship-panel border-rose-500/40 px-3 py-2 text-xs text-rose-300">{{ $auditError }}</div>
            @elseif ($auditRunning)
                <div class="ship-panel border-cyan-500/40 px-3 py-2 text-xs text-cyan-300">Analyse en cours...</div>
            @elseif (empty($dependencyAudit))
                <div class="text-xs text-[#8ea2c5]">Lance l’analyse pour voir les dépendances manquantes sur le VPS.</div>
            @else
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 text-sm">
                    @foreach ($dependencyAudit as $item)
                        @php
                            $badge = $item['status'] === 'ok'
                                ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30'
                                : 'bg-rose-500/15 text-rose-300 border border-rose-500/30';
                            $label = $item['status'] === 'ok' ? 'OK' : 'Manquant';
                        @endphp
                        <div class="ship-panel px-3 py-2 flex items-center justify-between">
                            <div>
                                <div class="text-white">{{ $item['label'] }}</div>
                                <div class="text-xs text-[#8ea2c5]">{{ $item['identifier'] }}</div>
                            </div>
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge }}">{{ $label }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="ship-panel p-5 space-y-4">
            <div class="flex items-center justify-between"><h2 class="text-lg font-semibold">Variables d'environnement</h2><button type="button" wire:click="addEnvVar" class="ship-btn">+ Variable</button></div>
            <div class="border-t border-[#24324d] pt-4"><livewire:deployments.upload-deployment-env /></div>
            <div @if ($deploymentEnvFilePath) style="opacity:.55;pointer-events:none" @endif class="space-y-3">
                @if ($deploymentEnvFilePath)<div class="ship-panel border-cyan-500/40 px-3 py-2 text-xs text-cyan-300">Fichier .env importé, les variables manuelles seront ignorées.</div>@endif
                @foreach ($envVars as $index => $var)
                    <div class="grid gap-3 md:grid-cols-[1fr_1fr_auto_auto]"><input wire:model.defer="envVars.{{ $index }}.key" placeholder="APP_NAME" class="rounded-xl border border-[#2f3f61] bg-[#0b1426] px-3 py-2"/><input wire:model.defer="envVars.{{ $index }}.value" placeholder="Laravel Ship" class="rounded-xl border border-[#2f3f61] bg-[#0b1426] px-3 py-2"/><label class="ship-panel flex items-center gap-2 px-3 py-2 text-xs"><input type="checkbox" wire:model="envVars.{{ $index }}.is_secret" class="rounded border-[#2f3f61] bg-[#0b1426]">Secret</label><button type="button" wire:click="removeEnvVar({{ $index }})" class="ship-btn border-rose-500/40 text-rose-300">Supprimer</button></div>
                @endforeach
            </div>
            @if ($errors->has('envVars.*.key'))<p class="text-xs text-rose-300">{{ $errors->first('envVars.*.key') }}</p>@endif
            @if ($errors->has('envVars.*.value'))<p class="text-xs text-rose-300">{{ $errors->first('envVars.*.value') }}</p>@endif
        </section>

        <button class="ship-btn ship-btn-primary">Déployer maintenant</button>
    </form>
</div>
