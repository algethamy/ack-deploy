# Laravel ACK Deploy

![Laravel ACK Deploy](https://img.shields.io/badge/Laravel-ACK%20Deploy-red)
![PHP Version](https://img.shields.io/badge/PHP-%5E8.1-blue)
![License](https://img.shields.io/badge/license-MIT-green)

A Laravel package that simplifies deployment to **Alibaba Cloud Container Service for Kubernetes (ACK)**. This package automatically generates Docker configurations, Kubernetes manifests, and deployment scripts optimized for ACK clusters.

## Features

- 🚀 **One-command setup** - Generate all ACK deployment files instantly
- 🐳 **Optimized Dockerfiles** - Production-ready PHP 8.3 + Apache containers
- ☸️ **Complete K8s Manifests** - Deployments, Services, Ingress, HPA, ConfigMaps
- 💰 **Cost-optimized scaling** - Smart auto-scaling with minimal resource usage
- 🔧 **Artisan commands** - Easy build, deploy, and management
- 🌍 **Multi-region support** - Support for all ACK regions
- ⚡ **Production ready** - Includes health checks, resource limits, and best practices

## Installation

Install via Composer:

```bash
composer require algethamy/laravel-ack-deploy
```

## Quick Start

### 1. Initialize ACK Configuration

```bash
php artisan ack:init
```

This will create:
- `Dockerfile.ack` - Optimized Docker configuration
- `k8s/` directory with Kubernetes manifests
- `deploy-ack.sh` - Deployment script
- `.env.ack` - Environment template

### 2. Build Docker Image

```bash
# Build only
php artisan ack:build

# Build and push to registry
php artisan ack:build --push
```

### 3. Deploy to ACK

```bash
# Deploy with existing image
php artisan ack:deploy

# Build, push, and deploy in one command
php artisan ack:deploy --build --wait
```

## Configuration

### Environment Variables

Add these to your `.env` file:

```env
# ACK Configuration
APP_NAME=my-laravel-app
DOCKER_REGISTRY=registry.me-central-1.aliyuncs.com
K8S_NAMESPACE=production
DOMAIN=myapp.com

# Resource Configuration (optional)
ACK_CPU_REQUEST=50m
ACK_MEMORY_REQUEST=64Mi
ACK_CPU_LIMIT=200m
ACK_MEMORY_LIMIT=256Mi
ACK_MIN_REPLICAS=1
ACK_MAX_REPLICAS=5
ACK_CPU_THRESHOLD=70
```

### Advanced Configuration

Customize the generated files in the `k8s/` directory:

- `deployment.yaml` - Container deployment configuration
- `service.yaml` - LoadBalancer service
- `ingress.yaml` - Ingress routing rules  
- `hpa.yaml` - Horizontal Pod Autoscaler
- `configmap.yaml` - Environment variables

## Commands

### `ack:init`

Initialize ACK deployment configuration:

```bash
php artisan ack:init --app-name=myapp --registry=registry.me-central-1.aliyuncs.com
```

**Options:**
- `--app-name` - Application name
- `--registry` - Docker registry URL
- `--namespace` - Kubernetes namespace
- `--domain` - Application domain

### `ack:build`

Build Docker image for ACK deployment:

```bash
php artisan ack:build --tag=v1.0.0 --push
```

**Options:**
- `--tag` - Docker image tag (default: latest)
- `--registry` - Override registry URL
- `--push` - Push image to registry after build

### `ack:deploy`

Deploy application to ACK cluster:

```bash
php artisan ack:deploy --namespace=production --build --wait
```

**Options:**
- `--namespace` - Kubernetes namespace
- `--build` - Build and push image before deploying
- `--wait` - Wait for deployment to be ready

## Prerequisites

### Local Environment

- Docker Desktop or Docker Engine
- kubectl configured for your ACK cluster
- PHP 8.1+ with Composer

### ACK Cluster Setup

1. **Create ACK Cluster** in Alibaba Cloud Console
2. **Configure kubectl** access:
   ```bash
   # Download kubeconfig from ACK console
   export KUBECONFIG=./kubeconfig.yaml
   kubectl get nodes
   ```

3. **Set up Container Registry** access:
   ```bash
   docker login registry.me-central-1.aliyuncs.com
   ```

## Generated Files

### Dockerfile.ack

Production-optimized Docker image with:
- PHP 8.3 + Apache
- Composer dependencies
- Laravel optimizations
- Security best practices
- Health check support

### Kubernetes Manifests

**deployment.yaml**
- Cost-optimized resource requests (50m CPU, 64Mi RAM)
- Health checks and probes
- Environment configuration
- Rolling update strategy

**service.yaml**
- LoadBalancer for external access
- Port 80/443 configuration

**hpa.yaml**
- Auto-scaling from 1-5 pods
- CPU-based scaling (70% threshold)
- Smart scale-down delays

**ingress.yaml**
- Domain-based routing
- SSL/TLS ready
- Nginx ingress controller

**configmap.yaml**
- Laravel environment variables
- Database configuration
- Cache settings

## Cost Optimization

This package is designed for **cost-efficient ACK deployments**:

### Resource Efficiency
- **Minimal baseline**: 50m CPU, 64Mi RAM per pod
- **Smart scaling**: Only scales up when CPU > 70%
- **Scale-to-minimum**: Returns to 1 pod during low traffic

### Estimated Costs
- **Small app (1 pod)**: ~$5-10/month
- **Medium load (2-3 pods)**: ~$15-25/month
- **High load (5 pods)**: ~$30-50/month

*Costs depend on ACK cluster configuration and region*

## Multi-Region Support

Supported ACK regions:

| Region | Location | Registry Endpoint |
|--------|----------|-------------------|
| `me-central-1` | Saudi Arabia (Riyadh) | `registry.me-central-1.aliyuncs.com` |
| `me-east-1` | UAE (Dubai) | `registry.me-east-1.aliyuncs.com` |
| `ap-southeast-1` | Singapore | `registry.ap-southeast-1.aliyuncs.com` |
| `us-west-1` | US West | `registry.us-west-1.aliyuncs.com` |
| `eu-west-1` | UK (London) | `registry.eu-west-1.aliyuncs.com` |

## Troubleshooting

### Common Issues

**Docker build fails:**
```bash
# Check Docker is running
docker version

# Clear Docker cache
docker system prune -f
```

**kubectl connection fails:**
```bash
# Check cluster connectivity
kubectl cluster-info

# Verify kubeconfig
kubectl config view
```

**Deployment not ready:**
```bash
# Check pod logs
kubectl logs deployment/myapp-app -n production

# Check pod status
kubectl describe pods -l app=myapp-app -n production
```

**No external IP assigned:**
```bash
# Check LoadBalancer service
kubectl get service myapp-service -n production

# Check service events
kubectl describe service myapp-service -n production
```

## Best Practices

### Security
- Use environment variables for sensitive data
- Enable RBAC in your ACK cluster
- Keep Docker images updated
- Use private container registries

### Performance
- Set appropriate resource limits
- Use health checks
- Enable horizontal pod autoscaling
- Monitor application metrics

### Cost Optimization
- Use cost-efficient resource requests
- Enable cluster autoscaling
- Monitor resource usage
- Schedule non-production workloads

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Support

- 📖 [Documentation](https://github.com/algethamy/laravel-ack-deploy)
- 🐛 [Issues](https://github.com/algethamy/laravel-ack-deploy/issues)
- 💬 [Discussions](https://github.com/algethamy/laravel-ack-deploy/discussions)

## Credits

- Built with ❤️ for the Laravel community
- Optimized for Alibaba Cloud ACK
- Inspired by Laravel Sail and Laravel Forge