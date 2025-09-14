<?php

namespace Algethamy\LaravelAckDeploy\Console\Commands;

use Algethamy\LaravelAckDeploy\Traits\UsesKubeconfig;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class AckDeployCommand extends Command
{
    use UsesKubeconfig;
    protected $signature = 'ack:deploy
                           {--namespace= : Kubernetes namespace}
                           {--build : Build and push image before deploying}
                           {--wait : Wait for deployment to be ready}
                           {--recreate : Force recreate deployment if it exists}';

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

        // Handle existing deployment recreation
        if ($this->option('recreate') || $this->shouldRecreateDeployment($config)) {
            $this->recreateDeployment($config);
        }

        // Apply Kubernetes manifests
        if (!$this->applyManifests($config)) {
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
        $appName = $this->getAppNameFromEnv();
        $namespace = $this->option('namespace') ?: $this->getNamespaceFromEnv();

        // Extract repository name from Docker image format (algethamy/test_ack -> test_ack)
        $kubernetesName = $this->extractRepositoryName($appName);

        return [
            'namespace' => $namespace,
            'app_name' => $this->sanitizeKubernetesName($kubernetesName),
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
        // Check if kubectl is installed
        $process = new Process($this->getKubectlCommand(['kubectl', 'version', '--client']));
        $process->run();

        if (!$process->isSuccessful()) {
            $this->error('kubectl is not installed or not accessible');
            $this->newLine();
            $this->info('Install kubectl:');
            $this->line('• macOS: brew install kubectl');
            $this->line('• Linux: curl -LO "https://dl.k8s.io/release/$(curl -L -s https://dl.k8s.io/release/stable.txt)/bin/linux/amd64/kubectl"');
            $this->line('• Windows: choco install kubernetes-cli');
            return false;
        }

        // Check cluster connectivity
        $process = new Process($this->getKubectlCommand(['kubectl', 'cluster-info']));
        $process->run();

        if (!$process->isSuccessful()) {
            $this->error('Cannot connect to Kubernetes cluster.');
            $this->newLine();
            $this->info('🔧 Kubeconfig Setup Options:');
            
            // Check if we can auto-detect ACK cluster
            if ($this->attemptAckKubeconfigSetup()) {
                // Try again after setup
                $process = new Process($this->getKubectlCommand(['kubectl', 'cluster-info']));
                $process->run();
                
                if ($process->isSuccessful()) {
                    $this->info('✅ Successfully connected to ACK cluster');
                    return true;
                }
            }
            
            $this->newLine();
            $this->info('Manual setup options:');
            $this->line('1. Download kubeconfig from ACK Console:');
            $this->line('   • Go to Container Service → Clusters → Your Cluster → Connection Information');
            $this->line('   • Download kubeconfig and save as ~/.kube/config');
            $this->newLine();
            $this->line('2. Or set KUBECONFIG environment variable:');
            $this->line('   export KUBECONFIG=/path/to/your/kubeconfig');
            $this->newLine();
            $this->line('3. Or use Alibaba Cloud CLI:');
            $this->line('   aliyun cs GET /k8s/{cluster-id}/user_config --region {region}');
            
            return false;
        }

        $this->info('✅ kubectl connectivity confirmed');
        return true;
    }

    private function attemptAckKubeconfigSetup(): bool
    {
        $this->info('🔍 Attempting automatic ACK kubeconfig setup...');
        
        // Check if aliyun CLI is available
        $process = new Process(['aliyun', 'version']);
        $process->run();
        
        if (!$process->isSuccessful()) {
            $this->warn('Alibaba Cloud CLI not found. Install with: pip install aliyun-cli');
            return false;
        }
        
        // Try to detect cluster ID from environment or ask user
        $clusterId = $this->getClusterIdFromEnv();
        $region = $this->getRegionFromEnv();
        
        if (!$clusterId || !$region) {
            if ($this->confirm('Would you like to configure ACK cluster connection now?')) {
                $clusterId = $this->ask('Enter your ACK Cluster ID');
                $region = $this->ask('Enter your ACK Region', 'me-central-1');
                
                if ($clusterId && $region) {
                    $this->saveClusterConfig($clusterId, $region);
                }
            } else {
                return false;
            }
        }
        
        if (!$clusterId || !$region) {
            return false;
        }
        
        // Get kubeconfig using aliyun CLI
        $this->info("Getting kubeconfig for cluster: {$clusterId}");
        
        $process = new Process([
            'aliyun', 'cs', 'GET', "/k8s/{$clusterId}/user_config", '--region', $region
        ]);
        
        $process->run();
        
        if (!$process->isSuccessful()) {
            $this->warn('Failed to get kubeconfig from Alibaba Cloud. Check your aliyun CLI configuration.');
            return false;
        }
        
        $kubeconfig = $process->getOutput();
        
        // Save kubeconfig
        $kubeconfigPath = $_SERVER['HOME'] . '/.kube/config';
        $kubeconfigDir = dirname($kubeconfigPath);
        
        if (!is_dir($kubeconfigDir)) {
            mkdir($kubeconfigDir, 0700, true);
        }
        
        file_put_contents($kubeconfigPath, $kubeconfig);
        chmod($kubeconfigPath, 0600);
        
        $this->info("✅ Kubeconfig saved to: {$kubeconfigPath}");
        return true;
    }
    
    private function getClusterIdFromEnv(): ?string
    {
        // Check .env.ack file first
        if (file_exists(base_path('.env.ack'))) {
            $envContent = file_get_contents(base_path('.env.ack'));
            if (preg_match('/^ACK_CLUSTER_ID=(.+)$/m', $envContent, $matches)) {
                $clusterId = trim($matches[1], '"\'');
                if ($clusterId) {
                    return $clusterId;
                }
            }
        }
        
        return env('ACK_CLUSTER_ID');
    }
    
    private function getRegionFromEnv(): ?string
    {
        // Check .env.ack file first
        if (file_exists(base_path('.env.ack'))) {
            $envContent = file_get_contents(base_path('.env.ack'));
            if (preg_match('/^ACK_REGION=(.+)$/m', $envContent, $matches)) {
                $region = trim($matches[1], '"\'');
                if ($region) {
                    return $region;
                }
            }
        }
        
        return env('ACK_REGION', 'me-central-1');
    }
    
    private function saveClusterConfig(string $clusterId, string $region): void
    {
        $envAckPath = base_path('.env.ack');
        $content = '';
        
        if (file_exists($envAckPath)) {
            $content = file_get_contents($envAckPath);
        }
        
        // Add or update cluster configuration
        if (!str_contains($content, 'ACK_CLUSTER_ID=')) {
            $content .= "\n# ACK Cluster Configuration\nACK_CLUSTER_ID={$clusterId}\n";
        }
        
        if (!str_contains($content, 'ACK_REGION=')) {
            $content .= "ACK_REGION={$region}\n";
        }
        
        file_put_contents($envAckPath, $content);
        $this->info('Cluster configuration saved to .env.ack');
    }

    private function createNamespace(string $namespace): bool
    {
        if ($namespace === 'default') {
            return true; // Default namespace always exists
        }

        $this->info("Creating namespace: {$namespace}");

        $process = new Process($this->getKubectlCommand([
            'kubectl', 'create', 'namespace', $namespace,
            '--dry-run=client', '-o', 'yaml'
        ]));

        $process->run();
        $yaml = $process->getOutput();

        $process = new Process($this->getKubectlCommand(['kubectl', 'apply', '-f', '-']));
        $process->setInput($yaml);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->warn("Could not create namespace {$namespace} (may already exist)");
        }

        return true;
    }

    private function applyManifests(array $config): bool
    {
        if (!is_dir(base_path('k8s'))) {
            $this->error('k8s directory not found. Run: php artisan ack:init');
            return false;
        }

        $this->info('Applying Kubernetes manifests...');

        $process = new Process([
            'kubectl', 'apply', '-f', 'k8s/', '-n', $config['namespace']
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

    private function shouldRecreateDeployment(array $config): bool
    {
        $deploymentName = "{$config['app_name']}-app";
        $namespace = $config['namespace'];

        $this->info("🔍 Checking for deployment issues: {$deploymentName}");

        // First check if deployment exists
        $process = new Process([
            'kubectl', 'get', 'deployment', $deploymentName, '-n', $namespace
        ]);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->info("ℹ️  No existing deployment found");
            return false;
        }

        // Check pod status for ImagePull errors
        $process = new Process([
            'kubectl', 'get', 'pods', '-l', "app={$deploymentName}", '-n', $namespace, '--no-headers'
        ]);

        $process->run();

        if ($process->isSuccessful()) {
            $output = trim($process->getOutput());
            $this->info("📋 Current pod status:\n" . $output);

            if (str_contains($output, 'ImagePullBackOff') || str_contains($output, 'ErrImagePull')) {
                $this->warn("🔍 Detected ImagePull errors. Will recreate deployment automatically.");
                return true;
            }

            if (str_contains($output, 'ContainerCreating') || str_contains($output, 'Pending')) {
                $this->info("⏳ Pods are still starting up, skipping recreation");
                return false;
            }
        }

        $this->info("✅ No deployment recreation needed");
        return false;
    }

    private function recreateDeployment(array $config): void
    {
        $deploymentName = "{$config['app_name']}-app";
        $namespace = $config['namespace'];

        $this->info("🔄 Checking for existing deployment: {$deploymentName}");

        // Check if deployment exists
        $process = new Process([
            'kubectl', 'get', 'deployment', $deploymentName, '-n', $namespace
        ]);

        $process->run();

        if ($process->isSuccessful()) {
            $this->warn("⚠️  Existing deployment found. Recreating...");

            // Delete existing deployment
            $process = new Process([
                'kubectl', 'delete', 'deployment', $deploymentName, '-n', $namespace, '--wait=true'
            ]);

            $process->run(function ($type, $buffer) {
                if (trim($buffer)) {
                    $this->line("  " . $buffer);
                }
            });

            if ($process->isSuccessful()) {
                $this->info("✅ Deployment deleted successfully");
            } else {
                $this->warn("⚠️  Failed to delete deployment (continuing anyway)");
            }
        } else {
            $this->info("ℹ️  No existing deployment found");
        }
    }

    private function sanitizeKubernetesName(string $name): string
    {
        // Convert to lowercase and replace underscores with hyphens
        $sanitized = strtolower($name);
        $sanitized = str_replace('_', '-', $sanitized);

        // Remove any characters that aren't alphanumeric or hyphens
        $sanitized = preg_replace('/[^a-z0-9-]/', '-', $sanitized);

        // Ensure it starts and ends with alphanumeric character
        $sanitized = preg_replace('/^-+|-+$/', '', $sanitized);
        $sanitized = preg_replace('/-+/', '-', $sanitized); // Remove consecutive hyphens

        // Ensure it starts with a letter (required for some K8s resources like services)
        if (preg_match('/^[0-9]/', $sanitized)) {
            $sanitized = 'app-' . $sanitized;
        }

        // If name becomes empty after sanitization, use a default
        if (empty($sanitized)) {
            $sanitized = 'laravel-app';
        }

        return $sanitized;
    }

    private function getAppNameFromEnv(): string
    {
        // Check .env.ack file first
        if (file_exists(base_path('.env.ack'))) {
            $envContent = file_get_contents(base_path('.env.ack'));
            if (preg_match('/^APP_NAME=(.+)$/m', $envContent, $matches)) {
                $appName = trim($matches[1], '"\'');
                if ($appName) {
                    return $appName;
                }
            }
        }

        return env('APP_NAME', basename(base_path()));
    }

    private function getNamespaceFromEnv(): string
    {
        // Check .env.ack file first
        if (file_exists(base_path('.env.ack'))) {
            $envContent = file_get_contents(base_path('.env.ack'));
            if (preg_match('/^K8S_NAMESPACE=(.+)$/m', $envContent, $matches)) {
                $namespace = trim($matches[1], '"\'');
                if ($namespace) {
                    return $namespace;
                }
            }
        }

        return env('K8S_NAMESPACE', 'default');
    }

    private function extractRepositoryName(string $appName): string
    {
        // Handle Docker Hub format like "algethamy/test_ack" -> "test_ack"
        if (str_contains($appName, '/')) {
            $parts = explode('/', $appName);
            return end($parts); // Get the last part (repository name)
        }

        // Return as-is if no username prefix
        return $appName;
    }
}