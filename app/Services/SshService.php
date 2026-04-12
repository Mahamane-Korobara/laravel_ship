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

    private int $timeout = 60;

    public function __construct(
        private string $ip,
        private string $user,
        private string $privateKey,
        private int    $port = 22,
    ) {
        $this->connect();
    }

    /**
     * Définir le timeout de connexion SSH (en secondes)
     */
    public function setTimeout(int $seconds): self
    {
        $this->timeout = $seconds;
        $this->ssh->setTimeout($seconds);
        return $this;
    }

    private function connect(): void
    {
        $this->ssh = new SSH2($this->ip, $this->port, 15); // IMPORTANT: timeout de CONNEXION court (15s) pour éviter les blocages réseau
        $this->ssh->setTimeout($this->timeout); // Timeout pour les commandes

        $key = PublicKeyLoader::load($this->privateKey);

        if (!$this->ssh->login($this->user, $key)) {
            throw new Exception(
                "Échec de la connexion SSH à {$this->user}@{$this->ip}:{$this->port}"
            );
        }
    }

    /**
     * Exécuter une commande standard
     */
    public function exec(string $command): string
    {
        $output = $this->ssh->exec($command);

        $exitStatus = $this->ssh->getExitStatus();

        // Libère le canal pour la commande suivante sans déconnecter
        $this->ssh->reset();

        if ($exitStatus !== 0) {
            throw new RuntimeException(
                "Commande échouée (exit {$exitStatus}) :\n$ {$command}\n{$output}"
            );
        }

        return $output;
    }

    /**
     * Exécuter avec streaming (terminal temps réel)
     */
    public function execStreaming(string $command, callable $onOutput): void
    {
        // On utilise le callback de phpseclib pour capturer la sortie au fur et à mesure
        $this->ssh->exec($command, function (string $output) use ($onOutput) {
            foreach (explode("\n", $output) as $line) {
                if (trim($line) !== '') {
                    $onOutput(trim($line));
                }
            }
        });

        $exitStatus = $this->ssh->getExitStatus();

        // TRÈS IMPORTANT : On reset le canal après le stream pour éviter l'erreur "Channel 1 already open"
        $this->ssh->reset();

        if ($exitStatus !== 0) {
            throw new RuntimeException(
                "Commande échouée (exit {$exitStatus}) : {$command}"
            );
        }
    }

    /**
     * Uploader un fichier via SFTP
     */
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

        $sftp->disconnect();
    }

    /**
     * Télécharger un fichier via SFTP
     */
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

        $sftp->disconnect();
    }

    /**
     * Vérifier si un répertoire existe
     */
    public function dirExists(string $path): bool
    {
        $output = $this->ssh->exec("[ -d {$path} ] && echo 'yes' || echo 'no'");
        $this->ssh->reset();

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

    /**
     * Créer un tunnel SSH local pour accéder à l'agent sans firewall
     * Permet d'appeler localhost:8081 au lieu de l'IP publique
     * 
     * @param int $localPort Port local (défaut: 8081)
     * @param int $remotePort Port sur le serveur distant (défaut: 8081)
     * @return array ['process' => resource, 'keyFile' => string] Pour fermeture ultérieure
     */
    public function createLocalTunnel(int $localPort = 8081, int $remotePort = 8081): array
    {
        // On lance une commande SSH externe via proc_open pour créer le tunnel
        // Format: ssh -L localPort:127.0.0.1:remotePort -N -i keyfile user@host

        // Écrire la clé privée temporairement pour la commande SSH
        $keyFile = tempnam(sys_get_temp_dir(), 'ssh_key_');
        file_put_contents($keyFile, $this->privateKey);
        chmod($keyFile, 0600);

        $tunnelCmd = sprintf(
            "ssh -L %d:127.0.0.1:%d -N -i %s -o StrictHostKeyChecking=no -o UserKnownHostsFile=/dev/null -o ServerAliveInterval=30 -o ServerAliveCountMax=3 %s@%s -p %d",
            $localPort,
            $remotePort,
            escapeshellarg($keyFile),
            escapeshellarg($this->user),
            escapeshellarg($this->ip),
            $this->port
        );

        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($tunnelCmd, $descriptors, $pipes);

        if (!is_resource($process)) {
            unlink($keyFile);
            throw new RuntimeException('Tunnel SSH impossible à créer');
        }

        // Vérifier que le tunnel répond (avec retry)
        $maxRetries = 5;
        $initialDelay = 3; // 3 secondes avant le premier test (tunnel prend du temps à s'établir)
        $retryDelay = 2; // 2 secondes entre les essais
        $lastError = null;

        sleep($initialDelay); // Attendre que le tunnel soit bien établi

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            if ($attempt > 1) {
                sleep($retryDelay);
            }

            $ch = curl_init('http://127.0.0.1:' . $localPort . '/health');
            curl_setopt($ch, CURLOPT_TIMEOUT, 10); // 10 secondes par test
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FAILONERROR, false);

            $response = @curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($response !== false && $httpCode === 200) {
                // Succès! Le tunnel répond
                curl_close($ch);
                return [
                    'process' => $process,
                    'keyFile' => $keyFile,
                ];
            }

            $lastError = "HTTP {$httpCode}: " . ($response ?: curl_error($ch));
            curl_close($ch);
        }

        // Si on arrive ici, le tunnel n'a pas répondu après tous les essais
        proc_terminate($process);
        unlink($keyFile);
        throw new RuntimeException("Tunnel SSH créé mais /health ne répond pas après {$maxRetries} essais. Dernier: {$lastError}");
    }

    /**
     * Fermer un tunnel SSH créé par createLocalTunnel
     */
    public function closeLocalTunnel(array $tunnel): void
    {
        if (isset($tunnel['process']) && is_resource($tunnel['process'])) {
            proc_terminate($tunnel['process']);
        }

        if (isset($tunnel['keyFile']) && file_exists($tunnel['keyFile'])) {
            @unlink($tunnel['keyFile']);
        }
    }
}
