<?php

namespace App\Services;

interface RemoteRunner
{
    /**
     * Configurer le timeout de connexion (en secondes)
     */
    public function setTimeout(int $seconds): self;

    public function exec(string $command): string;

    public function execStreaming(string $command, callable $onOutput): void;

    public function uploadContent(string $content, string $remotePath): void;

    public function downloadFile(string $remotePath, string $localPath): void;

    public function disconnect(): void;
}
