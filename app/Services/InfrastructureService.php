<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class InfrastructureService
{
    public function install(): array
    {
        $logs = [];

        $this->log($logs, '⚙️ Préparation infrastructure Reverb + Queue...');

        $this->ensureSudo($logs);
        $this->ensurePackage($logs, 'redis-server');
        $this->ensurePackage($logs, 'supervisor');

        $this->run($logs, 'sudo -n systemctl enable --now redis-server');
        $this->run($logs, 'sudo -n systemctl enable --now supervisor');

        $this->run($logs, 'sudo -n a2enmod proxy proxy_wstunnel headers rewrite');

        $this->writeApacheWsProxyConf($logs);
        $this->writeSupervisorConfigs($logs);
        $this->writeCron($logs);

        $this->run($logs, 'sudo -n supervisorctl reread');
        $this->run($logs, 'sudo -n supervisorctl update');
        $this->run($logs, 'sudo -n supervisorctl restart laravel-ship-reverb');
        $this->run($logs, 'sudo -n supervisorctl restart laravel-ship-queue');

        $this->run($logs, 'sudo -n systemctl reload apache2');

        $this->log($logs, '✅ Infrastructure prête.');

        return $logs;
    }

    public function status(): array
    {
        return [
            'redis' => $this->check('systemctl is-active redis-server'),
            'supervisor' => $this->check('systemctl is-active supervisor'),
            'reverb' => $this->check('sudo -n supervisorctl status laravel-ship-reverb'),
            'queue' => $this->check('sudo -n supervisorctl status laravel-ship-queue'),
            'apache_ws' => $this->check('apache2ctl -M | grep -E \"proxy_wstunnel|proxy\"'),
            'cron' => $this->check('test -f /etc/cron.d/laravel-ship && echo ok'),
        ];
    }

    private function ensureSudo(array &$logs): void
    {
        $process = $this->process('sudo -n true');
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('Sudo non configuré (NOPASSWD). Ajoute les droits sudo pour www-data.');
        }
    }

    private function ensurePackage(array &$logs, string $package): void
    {
        if ($this->check("dpkg -s {$package}") === 'ok') {
            $this->log($logs, "✓ {$package} déjà installé");
            return;
        }

        $this->run($logs, "sudo -n apt-get update -y");
        $this->run($logs, "sudo -n apt-get install -y {$package}");
    }

    private function writeApacheWsProxyConf(array &$logs): void
    {
        $port = (int) config('ship.reverb_port', 8080);
        $conf = <<<CONF
ProxyPreserveHost On
ProxyPass \"/app\" \"ws://127.0.0.1:{$port}/app\"
ProxyPassReverse \"/app\" \"ws://127.0.0.1:{$port}/app\"
ProxyPass \"/apps\" \"ws://127.0.0.1:{$port}/apps\"
ProxyPassReverse \"/apps\" \"ws://127.0.0.1:{$port}/apps\"
CONF;

        $tmp = '/tmp/laravel-ship-reverb.conf';
        file_put_contents($tmp, $conf);

        $this->run($logs, "sudo -n mv {$tmp} /etc/apache2/conf-available/laravel-ship-reverb.conf");
        $this->run($logs, 'sudo -n a2enconf laravel-ship-reverb.conf');
    }

    private function writeSupervisorConfigs(array &$logs): void
    {
        $path = base_path();
        $reverbConf = <<<CONF
[program:laravel-ship-reverb]
command=php {$path}/artisan reverb:start --host=0.0.0.0 --port=8080
directory={$path}
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/laravel-ship-reverb.log
CONF;

        $queueConf = <<<CONF
[program:laravel-ship-queue]
command=php {$path}/artisan queue:work redis --sleep=3 --tries=1 --timeout=600
directory={$path}
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/laravel-ship-queue.log
CONF;

        file_put_contents('/tmp/laravel-ship-reverb.conf', $reverbConf);
        file_put_contents('/tmp/laravel-ship-queue.conf', $queueConf);

        $this->run($logs, 'sudo -n mv /tmp/laravel-ship-reverb.conf /etc/supervisor/conf.d/laravel-ship-reverb.conf');
        $this->run($logs, 'sudo -n mv /tmp/laravel-ship-queue.conf /etc/supervisor/conf.d/laravel-ship-queue.conf');
    }

    private function writeCron(array &$logs): void
    {
        $path = base_path();
        $user = config('ship.cron_user', 'www-data');
        $php = config('ship.cron_php', '/usr/bin/php');

        $cron = "* * * * * {$user} {$php} {$path}/artisan schedule:run >> /var/log/laravel-ship-schedule.log 2>&1\n";
        file_put_contents('/tmp/laravel-ship-cron', $cron);

        $this->run($logs, 'sudo -n mv /tmp/laravel-ship-cron /etc/cron.d/laravel-ship');
        $this->run($logs, 'sudo -n chown root:root /etc/cron.d/laravel-ship');
        $this->run($logs, 'sudo -n chmod 644 /etc/cron.d/laravel-ship');
        $this->log($logs, '✓ Cron schedule:run configuré');
    }

    private function run(array &$logs, string $command): void
    {
        $process = $this->process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            $error = trim($process->getErrorOutput() ?: $process->getOutput());
            $this->log($logs, "❌ {$command}");
            throw new RuntimeException($error !== '' ? $error : 'Commande échouée.');
        }

        $output = trim($process->getOutput());
        if ($output !== '') {
            $this->log($logs, $output);
        }
    }

    private function check(string $command): string
    {
        $process = $this->process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            return 'error';
        }

        $output = trim($process->getOutput());
        if ($output === '' || $output === 'inactive') {
            return 'error';
        }

        return 'ok';
    }

    private function process(string $command): Process
    {
        $process = Process::fromShellCommandline($command);
        $process->setTimeout(300);

        return $process;
    }

    private function log(array &$logs, string $line): void
    {
        $logs[] = $line;
    }
}
