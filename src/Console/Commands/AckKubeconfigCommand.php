<?php

namespace Algethamy\LaravelAckDeploy\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class AckKubeconfigCommand extends Command
{
    protected $signature = 'ack:kubeconfig
                           {--cluster-id= : ACK Cluster ID}
                           {--region= : ACK Region}
                           {--save : Save cluster config to .env.ack}
                           {--local : Save kubeconfig to project directory instead of global}';

    protected $description = 'Setup kubectl configuration for ACK cluster';

    public function handle(): int
    {
        $this->info('🔧 Setting up kubectl configuration for ACK...');

        // Check if aliyun CLI is installed
        if (!$this->checkAliyunCli()) {
            return self::FAILURE;
        }

        // Get cluster configuration
        $config = $this->gatherClusterConfig();

        // Get kubeconfig from ACK
        if (!$this->fetchKubeconfig($config)) {
            return self::FAILURE;
        }

        // Save cluster config if requested
        if ($this->option('save') || $this->confirm('Save cluster configuration to .env.ack?')) {
            $this->saveClusterConfig($config);
        }

        // Verify connectivity
        if ($this->verifyConnection()) {
            $this->info('✅ kubectl configuration completed successfully!');
            $this->newLine();
            $this->info('You can now run: php artisan ack:deploy');
        }

        return self::SUCCESS;
    }

    private function checkAliyunCli(): bool
    {
        $process = new Process(['aliyun', 'version']);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->error('Alibaba Cloud CLI not found.');
            $this->newLine();
            $this->info('Install Alibaba Cloud CLI:');
            $this->line('• Python: pip install aliyun-cli');
            $this->line('• macOS: brew install aliyun-cli');
            $this->line('• Or download from: https://github.com/aliyun/aliyun-cli');
            $this->newLine();
            $this->info('After installation, configure with: aliyun configure');
            return false;
        }

        return true;
    }

    private function gatherClusterConfig(): array
    {
        $clusterId = $this->option('cluster-id') ?: $this->getClusterIdFromEnv();
        $region = $this->option('region') ?: $this->getRegionFromEnv();

        if (!$clusterId) {
            $this->newLine();
            $this->info('🔍 Find your Cluster ID in ACK Console:');
            $this->line('• Go to Container Service → Clusters');
            $this->line('• Copy the Cluster ID from the list');
            $clusterId = $this->ask('Enter your ACK Cluster ID');
        }

        if (!$region) {
            $region = $this->ask('Enter your ACK Region', 'me-central-1');
        }

        return [
            'cluster_id' => $clusterId,
            'region' => $region,
        ];
    }

    private function fetchKubeconfig(array $config): bool
    {
        $this->info("📥 Fetching kubeconfig from ACK cluster: {$config['cluster_id']}");

        $process = new Process([
            'aliyun', 'cs', 'GET', "/k8s/{$config['cluster_id']}/user_config", 
            '--region', $config['region']
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            $this->error('Failed to get kubeconfig from Alibaba Cloud.');
            $this->newLine();
            $this->info('💡 Troubleshooting:');
            $this->line('1. Check if aliyun CLI is configured: aliyun configure list');
            $this->line('2. Verify cluster ID and region are correct');
            $this->line('3. Ensure your account has access to the cluster');
            $this->line('4. Try: aliyun cs GET /clusters --region ' . $config['region']);
            return false;
        }

        $kubeconfig = $process->getOutput();

        // Determine kubeconfig path (local vs global)
        $useLocal = $this->option('local') || $this->confirm('Save kubeconfig to project directory? (Recommended for project isolation)', true);

        if ($useLocal) {
            $kubeconfigPath = base_path('kubeconfig.yaml');
            $this->info("💡 Using project-specific kubeconfig");
        } else {
            $kubeconfigPath = $_SERVER['HOME'] . '/.kube/config';
            $kubeconfigDir = dirname($kubeconfigPath);

            if (!is_dir($kubeconfigDir)) {
                mkdir($kubeconfigDir, 0700, true);
                $this->info("Created directory: {$kubeconfigDir}");
            }

            // Backup existing global kubeconfig
            if (file_exists($kubeconfigPath)) {
                $backupPath = $kubeconfigPath . '.backup.' . date('Y-m-d-H-i-s');
                copy($kubeconfigPath, $backupPath);
                $this->info("📁 Backed up existing kubeconfig to: {$backupPath}");
            }
        }

        // Save kubeconfig
        file_put_contents($kubeconfigPath, $kubeconfig);
        chmod($kubeconfigPath, 0600);

        $this->info("✅ Kubeconfig saved to: {$kubeconfigPath}");

        // Add to .gitignore if local
        if ($useLocal) {
            $this->addToGitignore('kubeconfig.yaml');
        }

        return true;
    }

    private function verifyConnection(): bool
    {
        $this->info('🔍 Verifying kubectl connectivity...');

        // Use local kubeconfig if it exists
        $kubeconfigPath = base_path('kubeconfig.yaml');
        $command = ['kubectl', 'cluster-info'];

        if (file_exists($kubeconfigPath)) {
            $command = ['kubectl', '--kubeconfig', $kubeconfigPath, 'cluster-info'];
            $this->info("Using local kubeconfig: {$kubeconfigPath}");
        }

        $process = new Process($command);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->warn('kubectl connectivity test failed');
            $this->line($process->getErrorOutput());
            return false;
        }

        $this->info('✅ kubectl connected successfully');
        $this->newLine();
        $this->info('Cluster Info:');
        $this->line($process->getOutput());
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

    private function saveClusterConfig(array $config): void
    {
        $envAckPath = base_path('.env.ack');
        $content = '';

        if (file_exists($envAckPath)) {
            $content = file_get_contents($envAckPath);
        }

        // Update or add cluster configuration
        if (preg_match('/^ACK_CLUSTER_ID=.*$/m', $content)) {
            $content = preg_replace('/^ACK_CLUSTER_ID=.*$/m', "ACK_CLUSTER_ID={$config['cluster_id']}", $content);
        } else {
            if (!str_contains($content, 'ACK_CLUSTER_ID=')) {
                $content .= "\n# ACK Cluster Configuration\nACK_CLUSTER_ID={$config['cluster_id']}\n";
            }
        }

        if (preg_match('/^ACK_REGION=.*$/m', $content)) {
            $content = preg_replace('/^ACK_REGION=.*$/m', "ACK_REGION={$config['region']}", $content);
        } else {
            if (!str_contains($content, 'ACK_REGION=')) {
                $content .= "ACK_REGION={$config['region']}\n";
            }
        }

        file_put_contents($envAckPath, $content);
        $this->info('💾 Cluster configuration saved to .env.ack');
    }

    private function addToGitignore(string $filename): void
    {
        $gitignorePath = base_path('.gitignore');
        $pattern = $filename;

        // Check if .gitignore exists and if pattern is already there
        if (file_exists($gitignorePath)) {
            $gitignoreContent = file_get_contents($gitignorePath);
            if (str_contains($gitignoreContent, $pattern)) {
                return; // Already exists
            }
        } else {
            $gitignoreContent = '';
        }

        // Add pattern to .gitignore
        $gitignoreContent .= "\n# ACK kubeconfig\n{$pattern}\n";
        file_put_contents($gitignorePath, $gitignoreContent);
        $this->info("📝 Added {$pattern} to .gitignore");
    }
}