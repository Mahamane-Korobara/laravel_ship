<?php

namespace App\Livewire\Projects;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ProjectList extends Component
{
    public string $search = '';
    public string $view = 'grid';

    public function setView(string $view): void
    {
        if (!in_array($view, ['grid', 'list'], true)) {
            return;
        }

        $this->view = $view;
    }

    public function render()
    {
        $projects = Auth::user()
            ->projects()
            ->with('server')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('name', 'like', '%' . $this->search . '%')
                        ->orWhere('github_repo', 'like', '%' . $this->search . '%')
                        ->orWhere('domain', 'like', '%' . $this->search . '%');
                });
            })
            ->latest()
            ->get();

        return view('livewire.projects.index', [
            'projects' => $projects,
        ]);
    }
}
