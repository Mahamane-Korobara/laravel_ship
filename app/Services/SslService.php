<?php

namespace App\Services;

class SslService
{
    public function __construct(private SshService $ssh) {}

    public function obtain(string $domain, string $email): void
    {
        $this->ssh->exec(
            "sudo certbot --apache -d {$domain} " .
                "--non-interactive --agree-tos -m {$email} --redirect"
        );
    }

    public function remove(string $domain): void
    {
        $this->ssh->exec("sudo certbot delete --cert-name {$domain} --non-interactive -q || true");
        $this->ssh->exec("sudo rm -rf /etc/letsencrypt/live/{$domain} /etc/letsencrypt/archive/{$domain} /etc/letsencrypt/renewal/{$domain}.conf || true");
    }
}
