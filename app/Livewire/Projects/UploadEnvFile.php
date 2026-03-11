<?php

namespace App\Livewire\Projects;

use App\Models\Project;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UploadEnvFile extends Component
{
    use WithFileUploads;

    public Project $project;
    public $envFile;
    public bool $hasExistingFile = false;
    public ?string $existingFilePath = null;

    public function mount(Project $project): void
    {
        $this->project = $project;
        $this->hasExistingFile = !empty($project->env_file_path);
        $this->existingFilePath = $project->env_file_path;
    }

    public function uploadEnvFile(): void
    {
        $this->validate([
            'envFile' => 'required|file|mimes:env,txt|max:1024', // 1MB max
        ]);

        // Supprimer l'ancien fichier s'il existe
        if ($this->project->env_file_path) {
            Storage::disk('local')->delete($this->project->env_file_path);
        }

        // Uploader le nouveau fichier
        $path = $this->envFile->storeAs(
            path: 'env-files',
            name: "project_{$this->project->id}_{now()->timestamp}.env",
            options: 'local'
        );

        // Mettre à jour le projet
        $this->project->update(['env_file_path' => $path]);

        // Reset l'upload
        $this->envFile = null;
        $this->hasExistingFile = true;
        $this->existingFilePath = $path;

        $this->dispatch('notify', message: "Fichier .env uploadé avec succès ✓", type: 'success');
    }

    public function deleteEnvFile(): void
    {
        if ($this->project->env_file_path) {
            Storage::disk('local')->delete($this->project->env_file_path);
            $this->project->update(['env_file_path' => null]);

            $this->hasExistingFile = false;
            $this->existingFilePath = null;

            $this->dispatch('notify', message: "Fichier .env supprimé", type: 'info');
        }
    }

    public function downloadEnvFile(): ?StreamedResponse
    {
        if ($this->project->env_file_path) {
            return Storage::disk('local')->download($this->project->env_file_path, '.env');
        }

        return null;
    }

    public function render()
    {
        return view('livewire.projects.upload-env-file');
    }
}
