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
}
