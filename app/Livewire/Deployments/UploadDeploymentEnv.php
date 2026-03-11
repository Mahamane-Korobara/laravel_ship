<?php

namespace App\Livewire\Deployments;

use Livewire\Component;
use Livewire\WithFileUploads;

class UploadDeploymentEnv extends Component
{
    use WithFileUploads;

    public $envFile;
    public ?string $envFilePath = null;

    public function uploadEnvFile(): void
    {
        $this->validate([
            'envFile' => 'required|file|max:1024', // 1MB max - accept any file
        ]);

        // Uploader le fichier
        $path = $this->envFile->storeAs(
            path: 'deployment-env-files',
            name: "temp_" . uniqid() . ".env",
            options: 'local'
        );

        $this->envFilePath = $path;

        // Émettre un événement vers le parent
        $this->dispatch('env-file-uploaded', filePath: $path);

        // Reset l'upload
        $this->envFile = null;

        $this->dispatch('notify', message: "Fichier .env uploadé avec succès ✓", type: 'success');
    }

    public function clearEnvFile(): void
    {
        if ($this->envFilePath) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($this->envFilePath);
        }

        $this->envFilePath = null;
        $this->envFile = null;

        $this->dispatch('env-file-cleared');
        $this->dispatch('notify', message: "Fichier .env supprimé", type: 'info');
    }

    public function render()
    {
        return view('livewire.deployments.upload-deployment-env');
    }
}
