<?php

namespace Algethamy\LaravelAckDeploy\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class AckBuildCommand extends Command
{
    protected $signature = 'ack:build 
                           {--tag=latest : Docker image tag}
                           {--registry= : Docker registry URL}
                           {--push : Push image to registry after build}';

    protected $description = 'Build Docker image for ACK deployment';

    public function handle(): int
    {
        $this->info('🔨 Building Docker image for ACK deployment...');

        $config = $this->getConfiguration();

        // Build image
        if (!$this->buildImage($config)) {
            return self::FAILURE;
        }

        // Push if requested
        if ($this->option('push')) {
            if (!$this->pushImage($config)) {
                return self::FAILURE;
            }
        }

        $this->info('✅ Docker image build completed successfully!');
        $this->newLine();
        $this->info("Image: {$config['image']}");
        
        if (!$this->option('push')) {
            $this->info('To push the image, run: php artisan ack:build --push');
        }

        return self::SUCCESS;
    }

    private function getConfiguration(): array
    {
        $registry = $this->option('registry') ?: $this->getRegistryFromEnv();
        $appName = strtolower($this->getAppNameFromEnv()); // Ensure lowercase for Docker
        $tag = $this->option('tag');

        return [
            'registry' => $registry,
            'app_name' => $appName,
            'tag' => $tag,
            'image' => "{$registry}/{$appName}:{$tag}",
        ];
    }

    private function buildImage(array $config): bool
    {
        if (!file_exists(base_path('Dockerfile.ack'))) {
            $this->error('Dockerfile.ack not found. Run: php artisan ack:init');
            return false;
        }

        $this->info("Building image: {$config['image']}");

        $process = new Process([
            'docker', 'build',
            '--platform', 'linux/amd64',
            '-t', $config['image'],
            '-f', 'Dockerfile.ack',
            '.'
        ], base_path());

        $process->setTimeout(600); // 10 minutes

        $process->run(function ($type, $buffer) {
            if (Process::ERR === $type) {
                $this->error($buffer);
            } else {
                $this->line($buffer);
            }
        });

        if (!$process->isSuccessful()) {
            $this->error('Docker build failed');
            return false;
        }

        $this->info('Docker image built successfully');
        return true;
    }

    private function pushImage(array $config): bool
    {
        $this->info("Pushing image: {$config['image']}");

        $process = new Process([
            'docker', 'push', $config['image']
        ]);

        $process->setTimeout(600); // 10 minutes

        $process->run(function ($type, $buffer) {
            if (Process::ERR === $type) {
                $this->error($buffer);
            } else {
                $this->line($buffer);
            }
        });

        if (!$process->isSuccessful()) {
            $this->error('Docker push failed');
            return false;
        }

        $this->info('Docker image pushed successfully');
        return true;
    }

    private function getRegistryFromEnv(): string
    {
        // Check multiple sources for registry configuration
        $registry = env('DOCKER_REGISTRY');
        
        if (!$registry && file_exists(base_path('.env.ack'))) {
            // Try to read from .env.ack file
            $envContent = file_get_contents(base_path('.env.ack'));
            if (preg_match('/DOCKER_REGISTRY=(.+)/', $envContent, $matches)) {
                $registry = trim($matches[1]);
            }
        }
        
        return $registry ?: 'registry.me-central-1.aliyuncs.com';
    }

    private function getAppNameFromEnv(): string
    {
        // Check multiple sources for app name
        $appName = env('APP_NAME');
        
        if (!$appName && file_exists(base_path('.env.ack'))) {
            // Try to read from .env.ack file
            $envContent = file_get_contents(base_path('.env.ack'));
            if (preg_match('/APP_NAME=(.+)/', $envContent, $matches)) {
                $appName = trim($matches[1]);
            }
        }
        
        return $appName ?: basename(base_path());
    }
}