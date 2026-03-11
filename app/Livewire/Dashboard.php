<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use App\Models\Deployment;

#[Layout('layouts.app')]
class Dashboard extends Component
{
    public function render()
    {
        /** @var User $user */
        $user = Auth::user();

        return view('livewire.dashboard', [
            'servers'           => $user->servers()->withCount('projects')->get(),
            'projects'          => $user->projects()->with(['server', 'lastDeployment'])->latest()->get(),
            'recentDeployments' => Deployment::whereHas(
                'project',
                fn($q) => $q->where('user_id', $user->id)
            )->with('project')->latest()->take(10)->get(),
            'stats' => [
                'total_projects'    => $user->projects()->count(),
                'total_servers'     => $user->servers()->count(),
                'total_deployments' => Deployment::whereHas(
                    'project',
                    fn($q) => $q->where('user_id', $user->id)
                )->count(),
                'deployed_projects' => $user->projects()->where('status', 'deployed')->count(),
            ],
        ]);
    }
}
