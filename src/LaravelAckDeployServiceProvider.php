<?php

namespace Algethamy\LaravelAckDeploy;

use Algethamy\LaravelAckDeploy\Console\Commands\AckInitCommand;
use Algethamy\LaravelAckDeploy\Console\Commands\AckDeployCommand;
use Algethamy\LaravelAckDeploy\Console\Commands\AckBuildCommand;
use Algethamy\LaravelAckDeploy\Console\Commands\AckKubeconfigCommand;
use Illuminate\Support\ServiceProvider;

class LaravelAckDeployServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AckInitCommand::class,
                AckDeployCommand::class,
                AckBuildCommand::class,
                AckKubeconfigCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../stubs' => base_path('k8s'),
            ], 'ack-k8s');

            $this->publishes([
                __DIR__.'/../stubs/Dockerfile.ack' => base_path('Dockerfile.ack'),
                __DIR__.'/../stubs/docker-compose.ack.yml' => base_path('docker-compose.ack.yml'),
            ], 'ack-docker');

            $this->publishes([
                __DIR__.'/../stubs/deploy.sh' => base_path('deploy-ack.sh'),
                __DIR__.'/../stubs/.env.ack' => base_path('.env.ack'),
            ], 'ack-scripts');
        }
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        $this->app->singleton('ack-deploy', function ($app) {
            return new Services\AckDeployService();
        });
    }
}