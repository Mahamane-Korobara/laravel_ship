<div class="space-y-6">
    <div><h1 class="text-3xl font-bold">Launch Deployment</h1><p class="text-sm text-[#8ea2c5]">Orchestrez votre release en mode control room.</p></div>
    <form wire:submit="deploy" class="space-y-5">
        <section class="ship-panel p-5 space-y-4">
            <h2 class="text-lg font-semibold">Core Setup</h2>
            <div class="grid gap-4 md:grid-cols-2">
                <div><label class="text-xs text-[#8ea2c5]">Project Name</label><input wire:model.defer="name" class="mt-1 w-full rounded-xl border border-[#2f3f61] bg-[#0b1426] px-3 py-2"/>@error('name')<p class="text-xs text-rose-300 mt-1">{{ $message }}</p>@enderror</div>
                <div><label class="text-xs text-[#8ea2c5]">Server</label><select wire:model.defer="server_id" class="mt-1 w-full rounded-xl border border-[#2f3f61] bg-[#0b1426] px-3 py-2"><option value="0">Choose...</option>@foreach($servers as $server)<option value="{{ $server->id }}">{{ $server->name }} ({{ $server->masked_ip }})</option>@endforeach</select>@error('server_id')<p class="text-xs text-rose-300 mt-1">{{ $message }}</p>@enderror</div>
                <div><label class="text-xs text-[#8ea2c5]">Git Branch</label><div class="mt-1 flex gap-2"><select wire:model.defer="github_branch" class="w-full rounded-xl border border-[#2f3f61] bg-[#0b1426] px-3 py-2">@foreach($branches as $branch)<option value="{{ $branch }}">{{ $branch }}</option>@endforeach</select><button type="button" wire:click="loadBranches" class="ship-btn">Sync</button></div></div>
                <div><label class="text-xs text-[#8ea2c5]">PHP</label><select wire:model.defer="php_version" class="mt-1 w-full rounded-xl border border-[#2f3f61] bg-[#0b1426] px-3 py-2"><option value="7.4">7.4</option><option value="8.0">8.0</option><option value="8.1">8.1</option><option value="8.2">8.2</option><option value="8.3">8.3</option><option value="8.4">8.4</option></select></div>
                <div class="md:col-span-2"><label class="text-xs text-[#8ea2c5]">Domain</label><input wire:model.defer="domain" placeholder="api.example.com" class="mt-1 w-full rounded-xl border border-[#2f3f61] bg-[#0b1426] px-3 py-2"/></div>
            </div>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4 text-sm">
                <label class="ship-panel flex items-center gap-2 px-3 py-2"><input type="checkbox" wire:model="run_migrations" class="rounded border-[#2f3f61] bg-[#0b1426]">Migrations</label>
                <label class="ship-panel flex items-center gap-2 px-3 py-2"><input type="checkbox" wire:model="run_seeders" class="rounded border-[#2f3f61] bg-[#0b1426]">Seeders</label>
                <label class="ship-panel flex items-center gap-2 px-3 py-2"><input type="checkbox" wire:model="run_npm_build" class="rounded border-[#2f3f61] bg-[#0b1426]">Build assets</label>
                <label class="ship-panel flex items-center gap-2 px-3 py-2"><input type="checkbox" wire:model="has_queue_worker" class="rounded border-[#2f3f61] bg-[#0b1426]">Queue worker</label>
            </div>
        </section>

        <section class="ship-panel p-5 space-y-4">
            <div class="flex items-center justify-between"><h2 class="text-lg font-semibold">Environment Matrix</h2><button type="button" wire:click="addEnvVar" class="ship-btn">+ Variable</button></div>
            <div class="border-t border-[#24324d] pt-4"><livewire:deployments.upload-deployment-env /></div>
            <div @if ($deploymentEnvFilePath) style="opacity:.55;pointer-events:none" @endif class="space-y-3">
                @if ($deploymentEnvFilePath)<div class="ship-panel border-cyan-500/40 px-3 py-2 text-xs text-cyan-300">.env file uploaded, manual variables will be ignored.</div>@endif
                @foreach ($envVars as $index => $var)
                    <div class="grid gap-3 md:grid-cols-[1fr_1fr_auto_auto]"><input wire:model.defer="envVars.{{ $index }}.key" placeholder="APP_NAME" class="rounded-xl border border-[#2f3f61] bg-[#0b1426] px-3 py-2"/><input wire:model.defer="envVars.{{ $index }}.value" placeholder="Laravel Ship" class="rounded-xl border border-[#2f3f61] bg-[#0b1426] px-3 py-2"/><label class="ship-panel flex items-center gap-2 px-3 py-2 text-xs"><input type="checkbox" wire:model="envVars.{{ $index }}.is_secret" class="rounded border-[#2f3f61] bg-[#0b1426]">Secret</label><button type="button" wire:click="removeEnvVar({{ $index }})" class="ship-btn border-rose-500/40 text-rose-300">Delete</button></div>
                @endforeach
            </div>
            @if ($errors->has('envVars.*.key'))<p class="text-xs text-rose-300">{{ $errors->first('envVars.*.key') }}</p>@endif
            @if ($errors->has('envVars.*.value'))<p class="text-xs text-rose-300">{{ $errors->first('envVars.*.value') }}</p>@endif
        </section>

        <button class="ship-btn ship-btn-primary">Deploy Now</button>
    </form>
</div>
