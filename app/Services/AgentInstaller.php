<?php

namespace App\Services;

use App\Models\Server;
use RuntimeException;

class AgentInstaller
{
    public function install(Server $server, string $token, int $port, ?callable $onOutput = null): void
    {
        $emit = $onOutput ?? static function (string $line): void {};
        $ssh = new SshService(
            ip: $server->ip_address,
            user: $server->ssh_user,
            privateKey: $server->ssh_private_key,
            port: $server->ssh_port,
        );

        $emit('→ Préparation agent...');
        [$dockerBin, $ssh] = $this->resolveDockerBin($ssh, $server, $emit);
        $baseDir = $this->resolveBaseDir($ssh, $emit);
        $stagingDir = "/tmp/laravelship-agent-" . uniqid();

        $emit('→ Préparation dossier...');
        $ssh->exec("mkdir -p {$stagingDir}");

        $emit('→ Envoi des fichiers...');
        $ssh->uploadContent($this->dockerfile(), "{$stagingDir}/Dockerfile");
        $ssh->uploadContent($this->router(), "{$stagingDir}/router.php");
        $ssh->uploadContent($this->agentScript(), "{$stagingDir}/agent.php");
        $ssh->uploadContent($this->composeFile($token, $port), "{$stagingDir}/docker-compose.yml");

        if ($baseDir !== $stagingDir) {
            $emit("→ Déplacement vers {$baseDir}...");
            $ssh->execStreaming("mkdir -p {$baseDir}", $emit);
            $ssh->execStreaming("rm -rf {$baseDir}/*", $emit);
            $ssh->execStreaming("cp -r {$stagingDir}/* {$baseDir}/", $emit);
            $ssh->execStreaming("rm -rf {$stagingDir}", $emit);
        }

        $emit('→ Nettoyage ancien conteneur...');
        $ssh->exec("{$dockerBin} rm -f laravelship-agent 2>/dev/null || true");

        $emit('→ Build & démarrage agent...');
        $ssh->execStreaming("cd {$baseDir} && {$dockerBin} compose up -d --build", $emit);

        $emit('→ Vérification du démarrage...');
        $checkCmd = "{$dockerBin} ps --filter name=laravelship-agent --format '{{.Status}}' 2>/dev/null || true";
        $status = trim($ssh->exec($checkCmd));

        if (strpos($status, 'Up') === false) {
            // Le conteneur n'est pas actif, afficher les logs d'erreur
            $logs = $ssh->exec("cd {$baseDir} && {$dockerBin} compose logs --tail=30 2>/dev/null || true");
            $emit('⚠️  Conteneur non actif. Logs:');
            $emit($logs);
            throw new \RuntimeException("Agent n'a pas démarré correctement. Vérifiez les logs ci-dessus.");
        }

        $emit('→ Agent démarré ✓');
        $ssh->disconnect();
    }

    public function uninstall(Server $server, ?callable $onOutput = null): void
    {
        $emit = $onOutput ?? static function (string $line): void {};
        $ssh = new SshService(
            ip: $server->ip_address,
            user: $server->ssh_user,
            privateKey: $server->ssh_private_key,
            port: $server->ssh_port,
        );

        [$dockerBin, $ssh] = $this->resolveDockerBin($ssh, $server, $emit);
        $baseDir = $this->resolveExistingBaseDir($ssh) ?? '/opt/laravelship-agent';

        $emit('→ Arrêt de l’agent...');
        $ssh->execStreaming("if [ -d {$baseDir} ]; then cd {$baseDir} && {$dockerBin} compose down --remove-orphans || true; else echo 'Aucun dossier agent.'; fi", $emit);
        $emit('→ Suppression du conteneur...');
        $ssh->execStreaming("{$dockerBin} rm -f laravelship-agent 2>/dev/null || true", $emit);

        $emit('→ Suppression des images orphelines...');
        $ssh->exec("{$dockerBin} image prune -f --filter 'dangling=true' 2>/dev/null || true");

        $emit('→ Suppression des volumes orphelines...');
        $ssh->exec("{$dockerBin} volume prune -f 2>/dev/null || true");

        $emit('→ Suppression des réseaux orphelines...');
        $ssh->exec("{$dockerBin} network prune -f 2>/dev/null || true");

        $emit('→ Nettoyage fichiers...');
        $ssh->execStreaming("sudo -n rm -rf {$baseDir} 2>/dev/null || rm -rf {$baseDir}", $emit);

        $emit('→ Vérification du nettoyage...');
        $containerExists = trim($ssh->exec("{$dockerBin} ps -a --filter name=laravelship-agent --format '{{.ID}}' 2>/dev/null || true"));
        if ($containerExists !== '') {
            $emit('⚠️  Conteneur encore présent, suppression forcée...');
            $ssh->exec("{$dockerBin} rm -f {$containerExists} 2>/dev/null || true");
        } else {
            $emit('✓ Pas de conteneur résiduel');
        }

        $dirExists = trim($ssh->exec("[ -d {$baseDir} ] && echo 'oui' || echo 'non'"));
        if ($dirExists === 'oui') {
            $emit('⚠️  Dossier encore présent, suppression forcée...');
            $ssh->exec("sudo -n rm -rf {$baseDir} 2>/dev/null || rm -rf {$baseDir}");
        } else {
            $emit('✓ Dossier supprimé');
        }

        $emit('→ Agent complètement supprimé ✓');
        $ssh->disconnect();
    }

    private function resolveBaseDir(SshService $ssh, callable $emit): string
    {
        $sudoOk = trim($ssh->exec("sudo -n true >/dev/null 2>&1 && echo ok || echo fail")) === 'ok';
        if ($sudoOk) {
            $emit('→ Accès sudo détecté.');
            return '/opt/laravelship-agent';
        }

        $emit('→ Pas de sudo sans mot de passe. Installation dans le home.');
        $home = trim($ssh->exec('sh -lc \'printf %s "$HOME"\''));
        if ($home === '') {
            $home = trim($ssh->exec("sh -lc 'getent passwd \"$(whoami)\" | cut -d: -f6'"));
        }
        if ($home === '') {
            $home = '/home/' . $ssh->exec("sh -lc 'whoami'");
        }
        return rtrim(trim($home), '/') . '/laravelship-agent';
    }

    private function resolveExistingBaseDir(SshService $ssh): ?string
    {
        $paths = ['/opt/laravelship-agent', '~/laravelship-agent'];
        foreach ($paths as $path) {
            $exists = trim($ssh->exec("[ -d {$path} ] && echo yes || echo no")) === 'yes';
            if ($exists) {
                return $path;
            }
        }
        return null;
    }

    private function resolveDockerBin(SshService $ssh, Server $server, callable $emit): array
    {
        $dockerExit = trim($ssh->exec("sh -lc 'docker info >/dev/null 2>&1; echo $?'"));
        if ($dockerExit === '0') {
            return ['docker', $ssh];
        }

        $sudoDockerExit = trim($ssh->exec("sh -lc 'sudo -n docker info >/dev/null 2>&1; echo $?'"));
        if ($sudoDockerExit === '0') {
            $emit('→ Docker accessible via sudo.');
            return ['sudo -n docker', $ssh];
        }

        $sudoOk = trim($ssh->exec("sh -lc 'sudo -n true >/dev/null 2>&1; echo $?'"));
        if ($sudoOk === '0') {
            $emit('→ Correction des permissions Docker...');
            $ssh->exec("sudo -n usermod -aG docker {$server->ssh_user} || true");
            $emit('→ Utilisateur ajouté au groupe docker.');
            $emit('→ Reconnexion SSH automatique...');
            $ssh->disconnect();
            $ssh = new SshService(
                ip: $server->ip_address,
                user: $server->ssh_user,
                privateKey: $server->ssh_private_key,
                port: $server->ssh_port,
            );

            $dockerExit = trim($ssh->exec("sh -lc 'docker info >/dev/null 2>&1; echo $?'"));
            if ($dockerExit === '0') {
                $emit('→ Permissions Docker actives.');
                return ['docker', $ssh];
            }

            $sudoDockerExit = trim($ssh->exec("sh -lc 'sudo -n docker info >/dev/null 2>&1; echo $?'"));
            if ($sudoDockerExit === '0') {
                return ['sudo -n docker', $ssh];
            }

            $emit('→ Reconnexion insuffisante, un redémarrage peut être nécessaire.');
        }

        throw new RuntimeException('Docker indisponible pour l\'agent (groupe docker ou sudo requis).');
    }

    private function dockerfile(): string
    {
        return <<<DOCKER
            FROM php:8.2-cli
            WORKDIR /app

            # On ne garde que le strict nécessaire pour PHP et les outils de base.
            # On installe docker-ce-cli pour accéder à l'API Docker via le socket de la machine hôte.
            RUN apt-get update \\
                && apt-get install -y git curl unzip ca-certificates gnupg lsb-release \\
                && mkdir -m 0755 -p /etc/apt/keyrings \\
                && curl -fsSL https://download.docker.com/linux/debian/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg \\
                && echo "deb [arch=\$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/debian \$(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null \\
                && apt-get update \\
                && apt-get install -y docker-ce-cli \\
                && rm -rf /var/lib/apt/lists/*

            COPY agent.php /app/agent.php
            COPY router.php /app/router.php

            EXPOSE 8081

            # L'agent tourne via le serveur intégré de PHP pour rester léger
            CMD ["php", "-S", "0.0.0.0:8081", "/app/router.php"]
            DOCKER;
    }

    private function router(): string
    {
        return <<<'PHP'
<?php
require __DIR__ . '/agent.php';
PHP;
    }

    private function agentScript(): string
    {
        return <<<'PHP'
<?php

header('Content-Type: application/json');

$token = getenv('SHIP_AGENT_TOKEN') ?: '';
$allowAll = (getenv('SHIP_AGENT_ALLOW_ALL') ?: 'true') === 'true';
$allowedPrefixes = array_filter(array_map('trim', explode(',', getenv('SHIP_AGENT_ALLOW_PREFIXES') ?: 'docker,git,ln,mkdir,rm,cp,mv,chmod,chown,find,ls,echo,cat,sed,awk,tail,head,cut,tr,xargs,touch,test,sh')));

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// /health est accessible sans token pour valider le tunnel SSH
if ($method === 'GET' && $path === '/health') {
    echo json_encode(['ok' => true, 'status' => 'alive']);
    exit;
}

// Toutes les autres routes nécessitent le token
$requestToken = $_SERVER['HTTP_X_SHIP_TOKEN'] ?? '';
if (!$token || $requestToken !== $token) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

$payload = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

if ($path === '/exec') {
    $cmd = (string) ($payload['cmd'] ?? '');
    if ($cmd === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'cmd required']);
        exit;
    }

    if (!$allowAll) {
        $first = trim(strtok($cmd, ' '));
        if (!in_array($first, $allowedPrefixes, true)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Command not allowed']);
            exit;
        }
    }

    $descriptor = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($cmd, $descriptor, $pipes);
    if (!is_resource($process)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Process failed']);
        exit;
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    foreach ($pipes as $pipe) {
        fclose($pipe);
    }

    $exitCode = proc_close($process);
    echo json_encode([
        'ok' => $exitCode === 0,
        'exit_code' => $exitCode,
        'output' => trim($stdout . "\n" . $stderr),
    ]);
    exit;
}

if ($path === '/upload') {
    $remotePath = (string) ($payload['path'] ?? '');
    $content = (string) ($payload['content'] ?? '');
    if ($remotePath === '' || $content === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'path/content required']);
        exit;
    }

    $dir = dirname($remotePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    file_put_contents($remotePath, base64_decode($content));
    echo json_encode(['ok' => true]);
    exit;
}

if ($path === '/download') {
    $remotePath = (string) ($payload['path'] ?? '');
    if ($remotePath === '' || !file_exists($remotePath)) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'not found']);
        exit;
    }

    $content = base64_encode(file_get_contents($remotePath));
    echo json_encode(['ok' => true, 'content' => $content]);
    exit;
}

http_response_code(404);
echo json_encode(['ok' => false, 'error' => 'Not Found']);
PHP;
    }

    private function composeFile(string $token, int $port): string
    {
        $allowAll = config('ship.agent_allow_all', true) ? 'true' : 'false';
        $prefixes = config('ship.agent_allow_prefixes', 'docker,git,ln,mkdir,rm,cp,mv,chmod,chown,find,ls,echo,cat,sed,awk,tail,head,cut,tr,xargs,touch,test,sh');

        return <<<YAML
services:
  agent:
    build: .
    container_name: laravelship-agent
    restart: unless-stopped
    environment:
      - SHIP_AGENT_TOKEN={$token}
      - SHIP_AGENT_ALLOW_ALL={$allowAll}
      - SHIP_AGENT_ALLOW_PREFIXES={$prefixes}
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
      - /var/www:/var/www
    ports:
      - "{$port}:8081"
    user: "0:0"
YAML;
    }
}
