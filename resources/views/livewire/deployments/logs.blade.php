<div class="space-y-5">
    <div class="flex items-center justify-between"><div><h1 class="text-2xl font-bold">Logs Archive</h1><p class="text-sm text-[#8ea2c5]">{{ $deployment->project->name }} · {{ $deployment->release_name }}</p></div><a href="{{ route('deployments.show', $deployment) }}" wire:navigate class="ship-btn">Back Console</a></div>
    <div class="ship-panel bg-black/80 p-4"><pre class="max-h-[70vh] overflow-auto whitespace-pre-wrap text-xs leading-6 text-[#7fffd4]">{{ $deployment->log ?: 'No logs available.' }}</pre></div>
</div>
