<?php

namespace Algethamy\LaravelAckDeploy\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AckInitCommand extends Command
{
    protected $signature = 'ack:init 
                           {--app-name= : Application name}
                           {--registry= : Docker registry URL}
                           {--namespace= : Kubernetes namespace}
                           {--domain= : Application domain}
                           {--force : Overwrite existing files}';

    protected $description = 'Initialize ACK deployment configuration for Laravel project';

    public function handle(): int
    {
        $this->info('🚀 Initializing ACK deployment for Laravel...');

        // Get configuration
        $config = $this->gatherConfiguration();

        // Create directories
        $this->createDirectories();

        // Copy and process stub files
        $this->processStubFiles($config);

        $this->info('✅ ACK deployment configuration initialized successfully!');
        $this->newLine();
        $this->info('Generated files:');
        $this->line('  • Dockerfile.ack - Production Docker configuration');
        $this->line('  • k8s/ - Complete Kubernetes manifests');
        $this->line('  • deploy-ack.sh - Automated deployment script');
        $this->line('  • .env.ack - Environment template');
        $this->newLine();
        $this->info('Next steps:');
        $this->line('1. Review and customize the generated files');
        $this->line('2. Build your Docker image: php artisan ack:build --push');
        $this->line('3. Deploy to ACK: php artisan ack:deploy --build --wait');

        return self::SUCCESS;
    }

    private function gatherConfiguration(): array
    {
        $appName = $this->option('app-name') ?: $this->ask('Application name', basename(base_path()));
        $registry = $this->option('registry') ?: $this->ask('Docker registry', 'registry.me-central-1.aliyuncs.com');
        
        // Normalize registry formats
        $registry = $this->normalizeRegistry($registry);
        
        return [
            'app_name' => strtolower($appName), // Ensure lowercase for Docker compatibility
            'registry' => $registry,
            'namespace' => $this->option('namespace') ?: $this->ask('Kubernetes namespace', 'default'),
            'domain' => $this->option('domain') ?: $this->ask('Application domain (optional)', ''),
        ];
    }

    private function createDirectories(): void
    {
        $directories = ['k8s'];

        foreach ($directories as $dir) {
            if (!File::exists(base_path($dir))) {
                File::makeDirectory(base_path($dir), 0755, true);
                $this->info("Created directory: {$dir}/");
            }
        }
    }

    private function processStubFiles(array $config): void
    {
        $stubsPath = __DIR__ . '/../../../stubs';
        
        if (!File::exists($stubsPath)) {
            $this->error('Stub files not found. Please reinstall the package.');
            return;
        }

        // File mappings: stub => destination
        $fileMappings = [
            'Dockerfile.ack' => 'Dockerfile.ack',
            'deploy.sh' => 'deploy-ack.sh', 
            '.env.ack' => '.env.ack',
            'docker-compose.ack.yml' => 'docker-compose.ack.yml',
        ];

        // Process individual files
        foreach ($fileMappings as $stub => $destination) {
            $this->processStubFile($stubsPath, $stub, $destination, $config);
        }

        // Process k8s directory
        $this->processKubernetesStubs($stubsPath, $config);
    }

    private function processStubFile(string $stubsPath, string $stubFile, string $destinationFile, array $config): void
    {
        $stubPath = $stubsPath . '/' . $stubFile;
        $destinationPath = base_path($destinationFile);

        if (!File::exists($stubPath)) {
            $this->warn("Stub file not found: {$stubFile}");
            return;
        }

        // Check if file exists and not forcing
        if (File::exists($destinationPath) && !$this->option('force')) {
            if (!$this->confirm("File {$destinationFile} already exists. Overwrite?")) {
                $this->info("Skipped: {$destinationFile}");
                return;
            }
        }

        // Read stub content and replace placeholders
        $content = File::get($stubPath);
        $content = $this->replacePlaceholders($content, $config);

        // Write to destination
        File::put($destinationPath, $content);

        // Make shell scripts executable
        if (str_ends_with($destinationFile, '.sh') && PHP_OS_FAMILY !== 'Windows') {
            chmod($destinationPath, 0755);
        }

        $this->info("Generated: {$destinationFile}");
    }

    private function processKubernetesStubs(string $stubsPath, array $config): void
    {
        $k8sStubPath = $stubsPath . '/k8s';
        
        if (!File::exists($k8sStubPath)) {
            $this->warn('Kubernetes stub files not found');
            return;
        }

        $k8sFiles = File::files($k8sStubPath);

        foreach ($k8sFiles as $file) {
            $filename = $file->getFilename();
            $destinationPath = base_path("k8s/{$filename}");

            // Check if file exists and not forcing
            if (File::exists($destinationPath) && !$this->option('force')) {
                if (!$this->confirm("File k8s/{$filename} already exists. Overwrite?")) {
                    $this->info("Skipped: k8s/{$filename}");
                    continue;
                }
            }

            // Read stub content and replace placeholders
            $content = File::get($file->getPathname());
            $content = $this->replacePlaceholders($content, $config);

            // Write to destination
            File::put($destinationPath, $content);
            $this->info("Generated: k8s/{$filename}");
        }
    }

    private function replacePlaceholders(string $content, array $config): string
    {
        $placeholders = [
            '{{ APP_NAME }}' => $config['app_name'],
            '{{ REGISTRY }}' => $config['registry'],
            '{{ NAMESPACE }}' => $config['namespace'],
            '{{ DOMAIN }}' => $config['domain'] ?: 'example.com',
        ];

        return str_replace(array_keys($placeholders), array_values($placeholders), $content);
    }

    private function normalizeRegistry(string $registry): string
    {
        // Handle Docker Hub variations
        if (in_array(strtolower($registry), ['docker.io', 'hub.docker.com', 'docker.com', 'dockerhub'])) {
            return 'docker.io'; // Docker Hub official registry
        }

        // Remove any protocol prefixes
        $registry = preg_replace('/^https?:\/\//', '', $registry);
        
        return $registry;
    }
}