<?php

namespace App\Services;

use phpseclib3\Net\SSH2;
use phpseclib3\Net\SFTP;
use phpseclib3\Crypt\PublicKeyLoader;
use Exception;
use RuntimeException;

class SshService implements RemoteRunner
{
    private SSH2 $ssh;

    public function __construct(
        private string $ip,
        private string $user,
        private string $privateKey,
        private int    $port = 22,
    ) {
        $this->connect();
    }

    private function connect(): void
    {
        $this->ssh = new SSH2($this->ip, $this->port);
        $this->ssh->setTimeout(60);

        $key = PublicKeyLoader::load($this->privateKey);

        if (!$this->ssh->login($this->user, $key)) {
            throw new Exception(
                "Échec de la connexion SSH à {$this->user}@{$this->ip}:{$this->port}"
            );
        }
    }

    //  Exécuter une commande 
    public function exec(string $command): string
    {
        $output = $this->ssh->exec($command);

        if ($this->ssh->getExitStatus() !== 0) {
            throw new RuntimeException(
                "Commande échouée (exit {$this->ssh->getExitStatus()}) :\n$ {$command}\n{$output}"
            );
        }

        return $output;
    }

    //  Exécuter avec streaming (terminal temps réel) 
    public function execStreaming(string $command, callable $onOutput): void
    {
        $this->ssh->exec($command, function (string $output) use ($onOutput) {
            foreach (explode("\n", $output) as $line) {
                if (trim($line) !== '') {
                    $onOutput(trim($line));
                }
            }
        });

        if ($this->ssh->getExitStatus() !== 0) {
            throw new RuntimeException(
                "Commande échouée (exit {$this->ssh->getExitStatus()}) : {$command}"
            );
        }
    }

    //  Uploader un fichier via SFTP (utile pour les scripts de déploiement)
    public function uploadContent(string $content, string $remotePath): void
    {
        $sftp = new SFTP($this->ip, $this->port);
        $key  = PublicKeyLoader::load($this->privateKey);

        if (!$sftp->login($this->user, $key)) {
            throw new Exception('Échec connexion SFTP');
        }

        if (!$sftp->put($remotePath, $content)) {
            throw new Exception("Impossible d'écrire le fichier : {$remotePath}");
        }
    }

    //  Télécharger un fichier via SFTP
    public function downloadFile(string $remotePath, string $localPath): void
    {
        $sftp = new SFTP($this->ip, $this->port);
        $key  = PublicKeyLoader::load($this->privateKey);

        if (!$sftp->login($this->user, $key)) {
            throw new Exception('Échec connexion SFTP');
        }

        $dir = dirname($localPath);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new Exception("Impossible de créer le dossier local : {$dir}");
        }

        if (!$sftp->get($remotePath, $localPath)) {
            throw new Exception("Impossible de télécharger le fichier : {$remotePath}");
        }
    }

    //  Vérifier si un répertoire existe 
    public function dirExists(string $path): bool
    {
        $output = $this->ssh->exec("[ -d {$path} ] && echo 'yes' || echo 'no'");
        return str_contains($output, 'yes');
    }

    public function disconnect(): void
    {
        $this->ssh->disconnect();
    }

    public function getSystemMetrics(): array
    {
        $vcpu = (int) trim($this->exec('nproc'));

        $ramKb = (int) trim($this->exec("grep MemTotal /proc/meminfo | awk '{print $2}'"));
        $ramMb = (int) round($ramKb / 1024);

        $diskBytes = (int) trim($this->exec("df -B1 / | awk 'NR==2{print $2}'"));
        $diskGb = (int) round($diskBytes / 1024 / 1024 / 1024);

        return [
            'vcpu' => $vcpu ?: null,
            'ram_mb' => $ramMb ?: null,
            'disk_gb' => $diskGb ?: null,
        ];
    }
}
