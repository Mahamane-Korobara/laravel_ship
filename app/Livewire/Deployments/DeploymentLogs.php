<?php

namespace App\Livewire\Deployments;

use App\Models\Deployment;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class DeploymentLogs extends Component
{
    public Deployment $deployment;

    public function mount(Deployment $deployment): void
    {
        abort_if($deployment->project->user_id !== Auth::id(), 403);
        $this->deployment = $deployment;
    }

    public function render()
    {
        return view('livewire.deployments.logs');
    }
}
