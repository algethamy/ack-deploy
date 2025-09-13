<?php

namespace Algethamy\LaravelAckDeploy\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class AckDeployCommand extends Command
{
    protected $signature = 'ack:deploy 
                           {--namespace= : Kubernetes namespace}
                           {--build : Build and push image before deploying}
                           {--wait : Wait for deployment to be ready}';

    protected $description = 'Deploy application to ACK cluster';

    public function handle(): int
    {
        $this->info('🚀 Deploying application to ACK cluster...');

        $config = $this->getConfiguration();

        // Build and push if requested
        if ($this->option('build')) {
            if (!$this->buildAndPushImage()) {
                return self::FAILURE;
            }
        }

        // Check kubectl connectivity
        if (!$this->checkKubectl()) {
            return self::FAILURE;
        }

        // Create namespace
        if (!$this->createNamespace($config['namespace'])) {
            return self::FAILURE;
        }

        // Apply Kubernetes manifests
        if (!$this->applyManifests($config['namespace'])) {
            return self::FAILURE;
        }

        // Wait for deployment if requested
        if ($this->option('wait')) {
            if (!$this->waitForDeployment($config)) {
                return self::FAILURE;
            }
        }

        // Get service information
        $this->getServiceInfo($config);

        $this->info('✅ Deployment completed successfully!');

        return self::SUCCESS;
    }

    private function getConfiguration(): array
    {
        return [
            'namespace' => $this->option('namespace') ?: env('K8S_NAMESPACE', 'default'),
            'app_name' => env('APP_NAME', basename(base_path())),
        ];
    }

    private function buildAndPushImage(): bool
    {
        $this->info('Building and pushing Docker image...');

        $process = new Process([
            'php', 'artisan', 'ack:build', '--push'
        ], base_path());

        $process->setTimeout(1200); // 20 minutes

        $process->run(function ($type, $buffer) {
            $this->line($buffer);
        });

        return $process->isSuccessful();
    }

    private function checkKubectl(): bool
    {
        $process = new Process(['kubectl', 'version', '--client']);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->error('kubectl is not installed or not accessible');
            return false;
        }

        // Check cluster connectivity
        $process = new Process(['kubectl', 'cluster-info']);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->error('Cannot connect to Kubernetes cluster. Check your kubeconfig.');
            return false;
        }

        $this->info('kubectl connectivity confirmed');
        return true;
    }

    private function createNamespace(string $namespace): bool
    {
        if ($namespace === 'default') {
            return true; // Default namespace always exists
        }

        $this->info("Creating namespace: {$namespace}");

        $process = new Process([
            'kubectl', 'create', 'namespace', $namespace, 
            '--dry-run=client', '-o', 'yaml'
        ]);

        $process->run();
        $yaml = $process->getOutput();

        $process = new Process(['kubectl', 'apply', '-f', '-']);
        $process->setInput($yaml);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->warn("Could not create namespace {$namespace} (may already exist)");
        }

        return true;
    }

    private function applyManifests(string $namespace): bool
    {
        if (!is_dir(base_path('k8s'))) {
            $this->error('k8s directory not found. Run: php artisan ack:init');
            return false;
        }

        $this->info('Applying Kubernetes manifests...');

        $process = new Process([
            'kubectl', 'apply', '-f', 'k8s/', '-n', $namespace
        ], base_path());

        $process->run(function ($type, $buffer) {
            $this->line($buffer);
        });

        if (!$process->isSuccessful()) {
            $this->error('Failed to apply Kubernetes manifests');
            return false;
        }

        $this->info('Kubernetes manifests applied successfully');
        return true;
    }

    private function waitForDeployment(array $config): bool
    {
        $this->info('Waiting for deployment to be ready...');

        $process = new Process([
            'kubectl', 'wait', 
            '--for=condition=available', 
            '--timeout=300s', 
            "deployment/{$config['app_name']}-app",
            '-n', $config['namespace']
        ]);

        $process->run(function ($type, $buffer) {
            $this->line($buffer);
        });

        if (!$process->isSuccessful()) {
            $this->warn('Deployment readiness check timed out or failed');
            return false;
        }

        $this->info('Deployment is ready');
        return true;
    }

    private function getServiceInfo(array $config): void
    {
        $this->info('Getting service information...');

        // Get service details
        $process = new Process([
            'kubectl', 'get', 'service', "{$config['app_name']}-service",
            '-n', $config['namespace'], 
            '-o', 'jsonpath={.status.loadBalancer.ingress[0].ip}'
        ]);

        $process->run();

        $externalIp = trim($process->getOutput());

        if (empty($externalIp)) {
            $this->info('External IP not yet assigned. Run this command to check:');
            $this->line("kubectl get service {$config['app_name']}-service -n {$config['namespace']}");
        } else {
            $this->info("🌐 Application URL: http://{$externalIp}");
        }

        // Get pods status
        $process = new Process([
            'kubectl', 'get', 'pods', 
            '-l', "app={$config['app_name']}-app",
            '-n', $config['namespace']
        ]);

        $process->run();
        
        $this->newLine();
        $this->info('Pod status:');
        $this->line($process->getOutput());
    }
}