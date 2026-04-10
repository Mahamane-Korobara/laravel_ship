<?php

namespace App\Services;

interface RemoteRunner
{
    public function exec(string $command): string;

    public function execStreaming(string $command, callable $onOutput): void;

    public function uploadContent(string $content, string $remotePath): void;

    public function downloadFile(string $remotePath, string $localPath): void;

    public function disconnect(): void;
}

