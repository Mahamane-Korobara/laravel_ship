<?php

namespace App\Livewire\Deployments;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class DeploymentList extends Component
{
    public string $search = '';
    public string $status = 'all';

    public function render()
    {
        $userId = Auth::id();
        $query = \App\Models\Deployment::query()
            ->whereHas('project', function ($q) use ($userId) {
                $q->where('user_id', $userId);
            })
            ->with(['project'])
            ->latest();

        if ($this->search !== '') {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->where('release_name', 'like', "%{$search}%")
                    ->orWhere('git_branch', 'like', "%{$search}%")
                    ->orWhere('git_commit', 'like', "%{$search}%")
                    ->orWhereHas('project', function ($p) use ($search) {
                        $p->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if (!in_array($this->status, ['all', 'success', 'failed', 'rolled_back'], true)) {
            $this->status = 'all';
        }

        if ($this->status !== 'all') {
            $query->where('status', $this->status);
        }

        $deployments = $query->get();

        return view('livewire.deployments.index', [
            'deployments' => $deployments,
        ]);
    }
}
