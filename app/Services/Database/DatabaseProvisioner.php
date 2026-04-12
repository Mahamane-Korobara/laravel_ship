<?php

namespace App\Services\Database;

interface DatabaseProvisioner
{
    /**
     * Provision the database and user for the deployment
     *
     * @param callable $logger Function to call for logging messages
     */
    public function provision(callable $logger): void;

    /**
     * Get the gateway/host to use for database connections
     */
    public function getGateway(): string;
}
