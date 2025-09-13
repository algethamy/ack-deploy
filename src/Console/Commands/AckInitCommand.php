<?php

namespace Algethamy\LaravelAckDeploy\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

class AckInitCommand extends Command
{
    protected $signature = 'ack:init 
                           {--app-name= : Application name}
                           {--registry= : Docker registry URL}
                           {--namespace= : Kubernetes namespace}
                           {--domain= : Application domain}';

    protected $description = 'Initialize ACK deployment configuration for Laravel project';

    public function handle(): int
    {
        $this->info('🚀 Initializing ACK deployment for Laravel...');

        // Get configuration
        $config = $this->gatherConfiguration();

        // Create directories
        $this->createDirectories();

        // Generate files
        $this->generateDockerfile($config);
        $this->generateKubernetesManifests($config);
        $this->generateDeploymentScript($config);
        $this->generateEnvironmentFile($config);

        $this->info('✅ ACK deployment configuration initialized successfully!');
        $this->newLine();
        $this->info('Next steps:');
        $this->line('1. Review and customize the generated files in k8s/ directory');
        $this->line('2. Build your Docker image: php artisan ack:build');
        $this->line('3. Deploy to ACK: php artisan ack:deploy');

        return self::SUCCESS;
    }

    private function gatherConfiguration(): array
    {
        return [
            'app_name' => $this->option('app-name') ?: $this->ask('Application name', basename(base_path())),
            'registry' => $this->option('registry') ?: $this->ask('Docker registry', 'registry.me-central-1.aliyuncs.com'),
            'namespace' => $this->option('namespace') ?: $this->ask('Kubernetes namespace', 'default'),
            'domain' => $this->option('domain') ?: $this->ask('Application domain (optional)'),
        ];
    }

    private function createDirectories(): void
    {
        $directories = ['k8s', 'docker'];

        foreach ($directories as $dir) {
            if (!File::exists(base_path($dir))) {
                File::makeDirectory(base_path($dir), 0755, true);
                $this->info("Created directory: {$dir}/");
            }
        }
    }

    private function generateDockerfile(array $config): void
    {
        $dockerfile = $this->getDockerfileTemplate($config);
        File::put(base_path('Dockerfile.ack'), $dockerfile);
        $this->info('Generated: Dockerfile.ack');
    }

    private function generateKubernetesManifests(array $config): void
    {
        $manifests = [
            'deployment.yaml' => $this->getDeploymentTemplate($config),
            'service.yaml' => $this->getServiceTemplate($config),
            'ingress.yaml' => $this->getIngressTemplate($config),
            'hpa.yaml' => $this->getHpaTemplate($config),
            'configmap.yaml' => $this->getConfigMapTemplate($config),
        ];

        foreach ($manifests as $filename => $content) {
            File::put(base_path("k8s/{$filename}"), $content);
            $this->info("Generated: k8s/{$filename}");
        }
    }

    private function generateDeploymentScript(array $config): void
    {
        $script = $this->getDeploymentScriptTemplate($config);
        File::put(base_path('deploy-ack.sh'), $script);
        
        // Make script executable
        if (PHP_OS_FAMILY !== 'Windows') {
            chmod(base_path('deploy-ack.sh'), 0755);
        }
        
        $this->info('Generated: deploy-ack.sh');
    }

    private function generateEnvironmentFile(array $config): void
    {
        $env = $this->getEnvironmentTemplate($config);
        File::put(base_path('.env.ack'), $env);
        $this->info('Generated: .env.ack');
    }

    private function getDockerfileTemplate(array $config): string
    {
        return <<<DOCKERFILE
FROM php:8.3-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \\
    zip \\
    unzip \\
    curl \\
    sqlite3 \\
    libsqlite3-dev \\
    && docker-php-ext-install pdo pdo_sqlite \\
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install PHP dependencies
RUN composer install --optimize-autoloader --no-dev --ignore-platform-reqs

# Create storage directories and database
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views \\
    && touch database/database.sqlite \\
    && chown -R www-data:www-data /var/www/html \\
    && chmod -R 755 storage \\
    && chmod -R 755 bootstrap/cache

# Apache configuration
RUN echo '<VirtualHost *:80>\\n\\
    DocumentRoot /var/www/html/public\\n\\
    <Directory /var/www/html/public>\\n\\
        AllowOverride All\\n\\
        Require all granted\\n\\
    </Directory>\\n\\
    ErrorLog \${APACHE_LOG_DIR}/error.log\\n\\
    CustomLog \${APACHE_LOG_DIR}/access.log combined\\n\\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Generate application key on startup
RUN echo '#!/bin/bash\\n\\
if [ ! -f /var/www/html/.env ]; then\\n\\
    cp /var/www/html/.env.example /var/www/html/.env\\n\\
    php artisan key:generate --force\\n\\
fi\\n\\
exec apache2-foreground' > /usr/local/bin/start.sh \\
    && chmod +x /usr/local/bin/start.sh

# Expose port
EXPOSE 80

CMD ["/usr/local/bin/start.sh"]
DOCKERFILE;
    }

    private function getDeploymentTemplate(array $config): string
    {
        return <<<YAML
apiVersion: apps/v1
kind: Deployment
metadata:
  name: {$config['app_name']}-app
  namespace: {$config['namespace']}
  labels:
    app: {$config['app_name']}-app
spec:
  replicas: 1
  selector:
    matchLabels:
      app: {$config['app_name']}-app
  template:
    metadata:
      labels:
        app: {$config['app_name']}-app
    spec:
      containers:
      - name: {$config['app_name']}-app
        image: {$config['registry']}/{$config['app_name']}:latest
        ports:
        - containerPort: 80
        envFrom:
        - configMapRef:
            name: {$config['app_name']}-config
        env:
        - name: APP_URL
          value: "https://{$config['domain']}"
        resources:
          requests:
            memory: "64Mi"
            cpu: "50m"
          limits:
            memory: "256Mi"
            cpu: "200m"
        livenessProbe:
          httpGet:
            path: /
            port: 80
          initialDelaySeconds: 30
          periodSeconds: 10
        readinessProbe:
          httpGet:
            path: /
            port: 80
          initialDelaySeconds: 5
          periodSeconds: 5
YAML;
    }

    private function getServiceTemplate(array $config): string
    {
        return <<<YAML
apiVersion: v1
kind: Service
metadata:
  name: {$config['app_name']}-service
  namespace: {$config['namespace']}
  labels:
    app: {$config['app_name']}-app
spec:
  selector:
    app: {$config['app_name']}-app
  ports:
  - protocol: TCP
    port: 80
    targetPort: 80
  type: LoadBalancer
YAML;
    }

    private function getIngressTemplate(array $config): string
    {
        $host = $config['domain'] ?: 'example.com';
        
        return <<<YAML
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: {$config['app_name']}-ingress
  namespace: {$config['namespace']}
  annotations:
    kubernetes.io/ingress.class: nginx
    nginx.ingress.kubernetes.io/rewrite-target: /
spec:
  rules:
  - host: {$host}
    http:
      paths:
      - path: /
        pathType: Prefix
        backend:
          service:
            name: {$config['app_name']}-service
            port:
              number: 80
YAML;
    }

    private function getHpaTemplate(array $config): string
    {
        return <<<YAML
apiVersion: autoscaling/v2
kind: HorizontalPodAutoscaler
metadata:
  name: {$config['app_name']}-hpa
  namespace: {$config['namespace']}
spec:
  scaleTargetRef:
    apiVersion: apps/v1
    kind: Deployment
    name: {$config['app_name']}-app
  minReplicas: 1
  maxReplicas: 5
  behavior:
    scaleDown:
      stabilizationWindowSeconds: 900
      policies:
      - type: Pods
        value: 1
        periodSeconds: 300
    scaleUp:
      stabilizationWindowSeconds: 60
      policies:
      - type: Pods
        value: 2
        periodSeconds: 30
  metrics:
  - type: Resource
    resource:
      name: cpu
      target:
        type: Utilization
        averageUtilization: 70
YAML;
    }

    private function getConfigMapTemplate(array $config): string
    {
        return <<<YAML
apiVersion: v1
kind: ConfigMap
metadata:
  name: {$config['app_name']}-config
  namespace: {$config['namespace']}
data:
  APP_ENV: "production"
  APP_DEBUG: "false"
  DB_CONNECTION: "sqlite"
  DB_DATABASE: "/var/www/html/database/database.sqlite"
  CACHE_DRIVER: "file"
  SESSION_DRIVER: "file"
  QUEUE_CONNECTION: "sync"
YAML;
    }

    private function getDeploymentScriptTemplate(array $config): string
    {
        return <<<BASH
#!/bin/bash

# ACK Deployment Script for {$config['app_name']}
# Generated by laravel-ack-deploy package

set -e

echo "🚀 Starting ACK deployment for {$config['app_name']}..."

# Configuration
APP_NAME="{$config['app_name']}"
REGISTRY="{$config['registry']}"
NAMESPACE="{$config['namespace']}"
IMAGE_TAG=\${IMAGE_TAG:-latest}

# Colors for output
RED='\\033[0;31m'
GREEN='\\033[0;32m'
YELLOW='\\033[1;33m'
NC='\\033[0m' # No Color

echo_info() {
    echo -e "\${GREEN}[INFO]\${NC} \$1"
}

echo_warn() {
    echo -e "\${YELLOW}[WARN]\${NC} \$1"
}

echo_error() {
    echo -e "\${RED}[ERROR]\${NC} \$1"
}

# Check dependencies
check_dependencies() {
    echo_info "Checking dependencies..."
    
    if ! command -v docker &> /dev/null; then
        echo_error "Docker is not installed"
        exit 1
    fi
    
    if ! command -v kubectl &> /dev/null; then
        echo_error "kubectl is not installed"
        exit 1
    fi
    
    echo_info "Dependencies check passed"
}

# Build Docker image
build_image() {
    echo_info "Building Docker image..."
    docker build --platform linux/amd64 -t \$REGISTRY/\$APP_NAME:\$IMAGE_TAG -f Dockerfile.ack .
    echo_info "Docker image built successfully"
}

# Push Docker image
push_image() {
    echo_info "Pushing Docker image to registry..."
    docker push \$REGISTRY/\$APP_NAME:\$IMAGE_TAG
    echo_info "Docker image pushed successfully"
}

# Deploy to Kubernetes
deploy_k8s() {
    echo_info "Deploying to Kubernetes..."
    
    # Create namespace if it doesn't exist
    kubectl create namespace \$NAMESPACE --dry-run=client -o yaml | kubectl apply -f -
    
    # Apply Kubernetes manifests
    kubectl apply -f k8s/ -n \$NAMESPACE
    
    echo_info "Kubernetes deployment completed"
}

# Wait for deployment
wait_for_deployment() {
    echo_info "Waiting for deployment to be ready..."
    kubectl wait --for=condition=available --timeout=300s deployment/\$APP_NAME-app -n \$NAMESPACE
    echo_info "Deployment is ready"
}

# Get service URL
get_service_url() {
    echo_info "Getting service URL..."
    EXTERNAL_IP=\$(kubectl get service \$APP_NAME-service -n \$NAMESPACE -o jsonpath='{.status.loadBalancer.ingress[0].ip}')
    
    if [ -z "\$EXTERNAL_IP" ]; then
        echo_warn "External IP not yet assigned. Please run: kubectl get service \$APP_NAME-service -n \$NAMESPACE"
    else
        echo_info "Application is available at: http://\$EXTERNAL_IP"
    fi
}

# Main execution
main() {
    check_dependencies
    build_image
    push_image
    deploy_k8s
    wait_for_deployment
    get_service_url
    
    echo_info "✅ Deployment completed successfully!"
}

# Run main function
main "\$@"
BASH;
    }

    private function getEnvironmentTemplate(array $config): string
    {
        return <<<ENV
# ACK Deployment Environment Variables
# Copy this to .env and customize as needed

APP_NAME={$config['app_name']}
DOCKER_REGISTRY={$config['registry']}
K8S_NAMESPACE={$config['namespace']}
DOMAIN={$config['domain']}

# Laravel Environment (for production deployment)
APP_ENV=production
APP_DEBUG=false
APP_KEY=

# Database Configuration
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/html/database/database.sqlite

# Cache Configuration
CACHE_DRIVER=file
SESSION_DRIVER=file
QUEUE_CONNECTION=sync

# Mail Configuration (customize as needed)
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=hello@{$config['domain']}
MAIL_FROM_NAME="{$config['app_name']}"
ENV;
    }
}