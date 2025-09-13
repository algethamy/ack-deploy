<?php

namespace Algethamy\LaravelAckDeploy\Services;

class AckDeployService
{
    /**
     * Get default ACK configuration.
     */
    public function getDefaultConfig(): array
    {
        return [
            'registry' => env('DOCKER_REGISTRY', 'registry.me-central-1.aliyuncs.com'),
            'namespace' => env('K8S_NAMESPACE', 'default'),
            'app_name' => env('APP_NAME', basename(base_path())),
            'domain' => env('DOMAIN', ''),
            'cpu_request' => env('ACK_CPU_REQUEST', '50m'),
            'memory_request' => env('ACK_MEMORY_REQUEST', '64Mi'),
            'cpu_limit' => env('ACK_CPU_LIMIT', '200m'),
            'memory_limit' => env('ACK_MEMORY_LIMIT', '256Mi'),
            'min_replicas' => env('ACK_MIN_REPLICAS', 1),
            'max_replicas' => env('ACK_MAX_REPLICAS', 5),
            'cpu_threshold' => env('ACK_CPU_THRESHOLD', 70),
        ];
    }

    /**
     * Validate ACK deployment requirements.
     */
    public function validateRequirements(): array
    {
        $errors = [];

        // Check if running in Laravel
        if (!function_exists('base_path')) {
            $errors[] = 'This package must be run in a Laravel application';
        }

        // Check for required files
        $requiredFiles = ['.env.example', 'composer.json'];
        foreach ($requiredFiles as $file) {
            if (!file_exists(base_path($file))) {
                $errors[] = "Required file missing: {$file}";
            }
        }

        // Check PHP version
        if (version_compare(PHP_VERSION, '8.1', '<')) {
            $errors[] = 'PHP 8.1 or higher is required';
        }

        return $errors;
    }

    /**
     * Get ACK regions and their endpoints.
     */
    public function getAckRegions(): array
    {
        return [
            'me-central-1' => [
                'name' => 'Saudi Arabia (Riyadh)',
                'registry' => 'registry.me-central-1.aliyuncs.com',
                'endpoint' => 'ack.me-central-1.aliyuncs.com',
            ],
            'me-east-1' => [
                'name' => 'UAE (Dubai)', 
                'registry' => 'registry.me-east-1.aliyuncs.com',
                'endpoint' => 'ack.me-east-1.aliyuncs.com',
            ],
            'ap-southeast-1' => [
                'name' => 'Singapore',
                'registry' => 'registry.ap-southeast-1.aliyuncs.com',
                'endpoint' => 'ack.ap-southeast-1.aliyuncs.com',
            ],
            'us-west-1' => [
                'name' => 'US West (Silicon Valley)',
                'registry' => 'registry.us-west-1.aliyuncs.com',
                'endpoint' => 'ack.us-west-1.aliyuncs.com',
            ],
            'eu-west-1' => [
                'name' => 'UK (London)',
                'registry' => 'registry.eu-west-1.aliyuncs.com',
                'endpoint' => 'ack.eu-west-1.aliyuncs.com',
            ],
        ];
    }

    /**
     * Generate secure app key for production.
     */
    public function generateAppKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }

    /**
     * Get recommended resource limits based on app size.
     */
    public function getRecommendedResources(string $appSize = 'small'): array
    {
        $resources = [
            'small' => [
                'cpu_request' => '50m',
                'memory_request' => '64Mi',
                'cpu_limit' => '200m',
                'memory_limit' => '256Mi',
                'max_replicas' => 5,
            ],
            'medium' => [
                'cpu_request' => '100m',
                'memory_request' => '128Mi', 
                'cpu_limit' => '500m',
                'memory_limit' => '512Mi',
                'max_replicas' => 10,
            ],
            'large' => [
                'cpu_request' => '250m',
                'memory_request' => '256Mi',
                'cpu_limit' => '1000m', 
                'memory_limit' => '1Gi',
                'max_replicas' => 20,
            ],
        ];

        return $resources[$appSize] ?? $resources['small'];
    }
}