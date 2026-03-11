<?php

namespace App\Livewire\Servers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ServerList extends Component
{
    public function render()
    {
        /** @var User $user */
        $user = Auth::user();

        return view('livewire.servers.index', [
            'servers' => $user
                ->servers()
                ->withCount('projects')
                ->latest()
                ->get(),
        ]);
    }
}
