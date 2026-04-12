<?php

namespace App\Services\Database;

use App\Services\RemoteRunner;
use Illuminate\Support\Facades\Log;

class MysqlProvisioner implements DatabaseProvisioner
{
    private RemoteRunner $ssh;
    private array $config;

    public function __construct(array $config, RemoteRunner $ssh)
    {
        $this->config = $config;
        $this->ssh = $ssh;
    }

    public function provision(callable $logger): void
    {
        // Extract database configuration
        $dbUser = $this->config['DB_USERNAME'] ?? 'root';
        $dbPass = $this->config['DB_PASSWORD'] ?? '';
        $dbName = $this->config['DB_DATABASE'] ?? 'laravel_ship';
        $adminUser = $this->config['DB_ADMIN_USERNAME'] ?? null;
        $adminPass = $this->config['DB_ADMIN_PASSWORD'] ?? null;

        call_user_func($logger, '  Extraction des credentials MySQL: user=' . $dbUser . ', database=' . $dbName);
        call_user_func($logger, '  DB_PASSWORD length: ' . strlen($dbPass) . ' characters');

        // Detect Docker gateway IP dynamically
        $gateway = $this->detectDockerGateway($logger);
        $this->config['DB_GATEWAY'] = $gateway;
        call_user_func($logger, '  Gateway Docker detecté: ' . $gateway);

        // Build MySQL commands to create user and grant permissions
        $sqlCommands = [
            "CREATE DATABASE IF NOT EXISTS \`{$dbName}\`;",
            "CREATE USER IF NOT EXISTS '{$dbUser}'@'%' IDENTIFIED BY '{$dbPass}';",
            "GRANT ALL PRIVILEGES ON \`{$dbName}\`.* TO '{$dbUser}'@'%' WITH GRANT OPTION;",
            "FLUSH PRIVILEGES;",
        ];

        $sqlScript = implode("\n", $sqlCommands);
        call_user_func($logger, '  SQL script prepared: ' . strlen($sqlScript) . ' bytes');

        // Try provisioning with multiple strategies
        $this->provisionMySQL($logger, $sqlScript, $dbUser, $dbPass);
        
        // Verify that the user was created successfully
        $this->verifyUserCreation($logger, $dbUser);
    }

    private function provisionMySQL(callable $logger, string $sqlScript, string $dbUser, string $dbPass): void
    {
        $tmpSqlFile = '/tmp/provision_mysql_' . uniqid() . '.sql';

        try {
            call_user_func($logger, '  Provisionnement de l\'utilisateur MySQL...');
            $this->ssh->uploadContent($sqlScript, $tmpSqlFile);
            call_user_func($logger, '  SQL script uploaded to ' . $tmpSqlFile);

            $executed = false;

            // Strategy 1: Use sudo mysql directly (PRIORITY)
            if (!$executed) {
                call_user_func($logger, '  [Stratégie 1] Using sudo mysql (host system)...');
                $cmd = "sudo -n mysql < {$tmpSqlFile} 2>&1";
                $result = $this->execWithOutput($cmd);
                
                if ($result['success']) {
                    call_user_func($logger, '  ✓ Sudo mysql succeeded');
                    $executed = true;
                } else {
                    $errorMsg = substr($result['output'], 0, 80);
                    call_user_func($logger, '  ✗ Sudo mysql failed: ' . $errorMsg);
                }
            }

            // Strategy 2: Docker exec on MySQL container (no password - uses socket)
            if (!$executed) {
                call_user_func($logger, '  [Stratégie 2] Using docker exec on MySQL container...');
                $mysqlContainer = $this->findMysqlContainer($logger);
                
                if ($mysqlContainer) {
                    $cmd = "docker exec {$mysqlContainer} mysql -u root < {$tmpSqlFile} 2>&1";
                    $result = $this->execWithOutput($cmd);
                    
                    if ($result['success']) {
                        call_user_func($logger, '  ✓ Docker exec succeeded');
                        $executed = true;
                    } else {
                        $errorMsg = substr($result['output'], 0, 80);
                        call_user_func($logger, '  ✗ Docker exec failed: ' . $errorMsg);
                    }
                } else {
                    call_user_func($logger, '  ⚠️  MySQL container not found');
                }
            }

            // Strategy 3: Docker run via 127.0.0.1 (no password)
            if (!$executed) {
                call_user_func($logger, '  [Stratégie 3] Docker run via 127.0.0.1 (no password)...');
                $cmd = "docker run --rm --network host -v {$tmpSqlFile}:{$tmpSqlFile}:ro mysql:8.0 " . 
                       "mysql -h 127.0.0.1 -u root --protocol=TCP < {$tmpSqlFile} 2>&1";
                $result = $this->execWithOutput($cmd);
                
                if ($result['success']) {
                    call_user_func($logger, '  ✓ Docker run 127.0.0.1 succeeded');
                    $executed = true;
                } else {
                    $errorMsg = substr($result['output'], 0, 80);
                    call_user_func($logger, '  ✗ Docker run 127.0.0.1 failed: ' . $errorMsg);
                }
            }

            // Strategy 4: Docker run via gateway 172.17.0.1
            if (!$executed) {
                call_user_func($logger, '  [Stratégie 4] Docker run via gateway 172.17.0.1...');
                $cmd = "docker run --rm --network host -v {$tmpSqlFile}:{$tmpSqlFile}:ro mysql:8.0 " . 
                       "mysql -h 172.17.0.1 -u root --protocol=TCP < {$tmpSqlFile} 2>&1";
                $result = $this->execWithOutput($cmd);
                
                if ($result['success']) {
                    call_user_func($logger, '  ✓ Docker run 172.17.0.1 succeeded');
                    $executed = true;
                } else {
                    $errorMsg = substr($result['output'], 0, 80);
                    call_user_func($logger, '  ✗ Docker run 172.17.0.1 failed: ' . $errorMsg);
                }
            }

            if (!$executed) {
                throw new \RuntimeException('Unable to provision MySQL - all strategies failed.');
            }

            call_user_func($logger, '  Utilisateur MySQL provisionné ✓');
            $this->ssh->exec("rm -f {$tmpSqlFile}");
        } catch (\Exception $e) {
            $this->ssh->exec("rm -f {$tmpSqlFile}");
            call_user_func($logger, '  ⚠️  Erreur lors du provisionnement MySQL: ' . $e->getMessage());
            throw $e;
        }
    }

    private function provisionViaDocker(callable $logger, string $sqlScript): void
    {
        $tmpSqlFile = '/tmp/provision_mysql_' . uniqid() . '.sql';

        try {
            call_user_func($logger, '  Provisionnement de l\'utilisateur MySQL...');

            // Upload SQL script to server
            $this->ssh->uploadContent($sqlScript, $tmpSqlFile);
            call_user_func($logger, '  SQL script uploaded to ' . $tmpSqlFile);

            // Get MySQL root password from container environment
            $rootPassword = $this->getMysqlRootPassword($logger);

            $executed = false;
            $passwords = [$rootPassword];
            
            // Add fallback password (empty password)
            if ($rootPassword !== '') {
                $passwords[] = '';
            }

            // Try different password combinations with different strategies
            foreach ($passwords as $pwd) {
                if ($executed) break;
                
                $passwordArg = !empty($pwd) ? ' -p' . escapeshellarg($pwd) : '';
                $pwdDesc = !empty($pwd) ? 'with password' : 'without password';
                
                // Try method 1: docker run with localhost (via host network)
                call_user_func($logger, "  [Tentative docker run 127.0.0.1 {$pwdDesc}]");
                $cmd = "docker run --rm --network host -v {$tmpSqlFile}:{$tmpSqlFile}:ro mysql:8.0 " . 
                       "mysql -h 127.0.0.1 -u root{$passwordArg} < {$tmpSqlFile}";
                $result = $this->execWithOutput($cmd);
                
                if ($result['success']) {
                    call_user_func($logger, '  ✓ Successfully provisioned via 127.0.0.1');
                    $executed = true;
                    break;
                } else {
                    call_user_func($logger, '  ✗ Failed: ' . substr($result['output'], 0, 80));
                }

                // Try method 2: Connect via Docker gateway IP
                call_user_func($logger, "  [Tentative docker run 172.17.0.1 {$pwdDesc}]");
                $cmd = "docker run --rm --network host -v {$tmpSqlFile}:{$tmpSqlFile}:ro mysql:8.0 " . 
                       "mysql -h 172.17.0.1 -u root{$passwordArg} < {$tmpSqlFile}";
                $result = $this->execWithOutput($cmd);
                
                if ($result['success']) {
                    call_user_func($logger, '  ✓ Successfully provisioned via 172.17.0.1');
                    $executed = true;
                    break;
                } else {
                    call_user_func($logger, '  ✗ Failed: ' . substr($result['output'], 0, 80));
                }

                // Try method 3: docker exec on MySQL container directly
                $mysqlContainer = $this->findMysqlContainer($logger);
                
                if ($mysqlContainer) {
                    call_user_func($logger, "  [Tentative docker exec on {$mysqlContainer} {$pwdDesc}]");
                    $cmd = "docker exec {$mysqlContainer} mysql -u root{$passwordArg} < {$tmpSqlFile}";
                    $result = $this->execWithOutput($cmd);
                    
                    if ($result['success']) {
                        call_user_func($logger, '  ✓ Successfully provisioned via docker exec');
                        $executed = true;
                        break;
                    } else {
                        call_user_func($logger, '  ✗ Failed: ' . substr($result['output'], 0, 80));
                    }
                }
            }

            if (!$executed) {
                throw new \RuntimeException('Unable to provision MySQL via Docker - all strategies failed.');
            }

            call_user_func($logger, '  Utilisateur MySQL provisionné ✓');

            // Cleanup temporary file
            $this->ssh->exec("rm -f {$tmpSqlFile}");
        } catch (\Exception $e) {
            $this->ssh->exec("rm -f {$tmpSqlFile}");
            call_user_func($logger, '  ⚠️  Erreur lors du provisionnement MySQL: ' . $e->getMessage());
            throw $e;
        }
    }

    public function getGateway(): string
    {
        return $this->config['DB_GATEWAY'] ?? '172.17.0.1';
    }

    private function detectDockerGateway(callable $logger): string
    {
        $gatewayDetectCmd = "docker network inspect bridge --format '{{(index .IPAM.Config 0).Gateway}}'";
        $gateway = trim((string) $this->ssh->exec($gatewayDetectCmd));

        if (empty($gateway)) {
            // Fallback to common default
            $gateway = '172.17.0.1';
            call_user_func($logger, '  ⚠️  Gateway detection failed, using default: ' . $gateway);
        }

        return $gateway;
    }

    private function getMysqlRootPassword(callable $logger): string
    {
        // Try to find MySQL container and get MYSQL_ROOT_PASSWORD env var
        try {
            // List all containers with mysql image
            $cmd = "docker ps --format 'table {{.Names}}\t{{.Image}}' | grep -i mysql | head -1 | awk '{print $1}'";
            $container = trim((string) $this->ssh->exec($cmd));
            call_user_func($logger, '  Looking for MySQL container: ' . ($container ?: 'not found'));
            
            if (!empty($container)) {
                // Try to get environment variables
                $cmd = "docker inspect {$container} --format='{{json .Config.Env}}'";
                $rawEnv = trim((string) $this->ssh->exec($cmd));
                call_user_func($logger, '  Raw environment retrieved, parsing...');
                
                // Parse JSON array to find MYSQL_ROOT_PASSWORD
                $env = json_decode($rawEnv, true);
                if (is_array($env)) {
                    foreach ($env as $var) {
                        if (strpos($var, 'MYSQL_ROOT_PASSWORD=') === 0) {
                            $password = substr($var, strlen('MYSQL_ROOT_PASSWORD='));
                            if (!empty($password)) {
                                call_user_func($logger, '  ✓ MySQL root password retrieved from container env');
                                return $password;
                            }
                        }
                    }
                }
                call_user_func($logger, '  ⚠️  MYSQL_ROOT_PASSWORD not found in env vars, trying port 3306...');
            }
        } catch (\Exception $e) {
            call_user_func($logger, '  ⚠️  Error retrieving password: ' . $e->getMessage());
        }
        
        // Fallback 1: Try with 'root' as password (common in Docker)
        call_user_func($logger, '  Fallback: trying with "root" as password');
        
        // Or try connecting without password first
        return 'root';
    }

    private function findMysqlContainer(callable $logger): ?string
    {
        try {
            // Find any running MySQL container
            $cmd = "docker ps --filter 'ancestor=mysql:8.0' --format '{{.Names}}' | head -1";
            $container = trim((string) $this->ssh->exec($cmd));
            
            if (!empty($container)) {
                call_user_func($logger, '  Found MySQL container: ' . $container);
                return $container;
            }
            
            // Fallback: try to find by name pattern
            $cmd = "docker ps --format 'table {{.Names}}\t{{.Image}}' | grep -E '(mysql|db)' | head -1 | awk '{print $1}'";
            $container = trim((string) $this->ssh->exec($cmd));
            
            if (!empty($container)) {
                call_user_func($logger, '  Found MySQL container by pattern: ' . $container);
                return $container;
            }
        } catch (\Exception $e) {
            call_user_func($logger, '  ⚠️  Could not find MySQL container: ' . $e->getMessage());
        }
        
        return null;
    }

    private function buildMysqlCommand(string $user, ?string $pass, string $tmpSqlFile): string
    {
        $userArg = escapeshellarg($user);
        $passArg = $pass !== null && $pass !== '' ? ' -p' . escapeshellarg($pass) : '';

        return "mysql -u {$userArg}{$passArg} < {$tmpSqlFile}";
    }

    private function execWithOutput(string $command): array
    {
        // Execute and capture both stdout and stderr
        $wrapped = "sh -lc " . escapeshellarg("({$command}) 2>&1; echo '|||EXIT_CODE:'$?");
        $fullOutput = trim((string) $this->ssh->exec($wrapped));
        
        // Extract exit code from the end
        if (preg_match('/\|\|\|EXIT_CODE:(\d+)$/', $fullOutput, $matches)) {
            $exitCode = (int) $matches[1];
            $output = substr($fullOutput, 0, -strlen($matches[0]));
        } else {
            $exitCode = 0;
            $output = $fullOutput;
        }
        
        \Log::debug('MysqlProvisioner::execWithOutput', [
            'command' => $command,
            'exit_code' => $exitCode,
            'output' => trim($output),
        ]);
        
        return [
            'success' => $exitCode === 0,
            'exit_code' => $exitCode,
            'output' => trim($output),
        ];
    }

    private function execStatus(string $command): bool
    {
        $result = $this->execWithOutput($command);
        return $result['success'];
    }

    private function verifyUserCreation(callable $logger, string $dbUser): void
    {
        call_user_func($logger, '  Vérification de la création de l\'utilisateur...');
        
        // Try to get MySQL root password again
        $rootPassword = $this->getMysqlRootPassword($logger);
        $passwords = [$rootPassword];
        if ($rootPassword !== '') {
            $passwords[] = '';
        }

        $verifySql = "SELECT user, host FROM mysql.user WHERE user='{$dbUser}';";
        $tmpVerifyFile = '/tmp/verify_mysql_' . uniqid() . '.sql';
        
        try {
            $this->ssh->uploadContent($verifySql, $tmpVerifyFile);
            
            foreach ($passwords as $pwd) {
                $passwordArg = !empty($pwd) ? ' -p' . escapeshellarg($pwd) : '';
                
                // Try to verify via docker run
                $cmd = "docker run --rm --network host -v {$tmpVerifyFile}:{$tmpVerifyFile}:ro mysql:8.0 " . 
                       "mysql -h 172.17.0.1 -u root{$passwordArg} < {$tmpVerifyFile}";
                $result = $this->execWithOutput($cmd);
                
                if ($result['success'] && strpos($result['output'], $dbUser) !== false) {
                    call_user_func($logger, '  ✓ Utilisateur ' . $dbUser . ' exists: ' . trim($result['output']));
                    $this->ssh->exec("rm -f {$tmpVerifyFile}");
                    return;
                }
            }
            
            call_user_func($logger, '  ⚠️  User verification inconclusive, proceeding anyway');
            $this->ssh->exec("rm -f {$tmpVerifyFile}");
        } catch (\Exception $e) {
            call_user_func($logger, '  ⚠️  User verification failed: ' . $e->getMessage());
        }
    }
}
