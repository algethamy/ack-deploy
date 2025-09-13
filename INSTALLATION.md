# Installation Guide

## Quick Installation

```bash
# In your Laravel project directory
composer require algethamy/laravel-ack-deploy
```

## Step-by-Step Setup

### 1. Install the Package

```bash
composer require algethamy/laravel-ack-deploy
```

### 2. Initialize ACK Configuration

```bash
php artisan ack:init
```

You'll be prompted for:
- **Application name** (defaults to directory name)
- **Docker registry** (defaults to ME-Central-1 region)
- **Kubernetes namespace** (defaults to 'default')
- **Domain** (optional)

### 3. Configure Environment

Update your `.env` file with ACK settings:

```env
# Add these variables
APP_NAME=my-laravel-app
DOCKER_REGISTRY=registry.me-central-1.aliyuncs.com
K8S_NAMESPACE=production
DOMAIN=myapp.com
```

### 4. Set Up ACK Prerequisites

#### A. Install kubectl
```bash
# macOS
brew install kubectl

# Linux
curl -LO "https://dl.k8s.io/release/$(curl -L -s https://dl.k8s.io/release/stable.txt)/bin/linux/amd64/kubectl"
chmod +x kubectl && sudo mv kubectl /usr/local/bin/

# Windows
choco install kubernetes-cli
```

#### B. Configure ACK Access
1. Download kubeconfig from ACK Console
2. Set environment variable:
   ```bash
   export KUBECONFIG=./kubeconfig.yaml
   kubectl get nodes
   ```

#### C. Set Up Container Registry Access
```bash
# Login to Alibaba Cloud Registry
docker login registry.me-central-1.aliyuncs.com
```

### 5. Deploy Your Application

#### Option 1: Step by Step
```bash
# 1. Build Docker image
php artisan ack:build --push

# 2. Deploy to cluster
php artisan ack:deploy --wait
```

#### Option 2: All in One
```bash
php artisan ack:deploy --build --wait
```

### 6. Verify Deployment

```bash
# Check deployment status
kubectl get pods -n your-namespace

# Get external URL
kubectl get service your-app-service -n your-namespace
```

## Customization

### Generated Files Structure
```
your-laravel-project/
├── Dockerfile.ack              # Docker configuration
├── deploy-ack.sh              # Deployment script
├── .env.ack                   # Environment template
└── k8s/                       # Kubernetes manifests
    ├── deployment.yaml
    ├── service.yaml
    ├── ingress.yaml
    ├── hpa.yaml
    └── configmap.yaml
```

### Customizing Resources

Edit the generated files to match your needs:

**For more CPU/Memory:**
```yaml
# k8s/deployment.yaml
resources:
  requests:
    memory: "128Mi"  # Increase from 64Mi
    cpu: "100m"      # Increase from 50m
  limits:
    memory: "512Mi"  # Increase from 256Mi
    cpu: "500m"      # Increase from 200m
```

**For different scaling:**
```yaml
# k8s/hpa.yaml
spec:
  minReplicas: 2     # Change from 1
  maxReplicas: 10    # Change from 5
```

## Regional Configuration

### Middle East (Riyadh) - Default
```env
DOCKER_REGISTRY=registry.me-central-1.aliyuncs.com
```

### Middle East (Dubai)
```env
DOCKER_REGISTRY=registry.me-east-1.aliyuncs.com
```

### Asia Pacific (Singapore)
```env
DOCKER_REGISTRY=registry.ap-southeast-1.aliyuncs.com
```

### US West (Silicon Valley)
```env
DOCKER_REGISTRY=registry.us-west-1.aliyuncs.com
```

### Europe (London)
```env
DOCKER_REGISTRY=registry.eu-west-1.aliyuncs.com
```

## Troubleshooting Installation

### Common Issues

#### Package not found
```bash
# Clear Composer cache
composer clear-cache
composer update
```

#### Commands not available
```bash
# Clear Laravel cache
php artisan config:clear
php artisan cache:clear
```

#### Docker issues
```bash
# Check Docker is running
docker version

# Test Docker registry access
docker login registry.me-central-1.aliyuncs.com
```

#### kubectl issues
```bash
# Check kubectl installation
kubectl version --client

# Test cluster connection
kubectl cluster-info
```

## Support

If you encounter issues during installation:

1. Check the [README](README.md) for detailed documentation
2. Review [troubleshooting section](README.md#troubleshooting)
3. Open an issue on [GitHub](https://github.com/algethamy/laravel-ack-deploy/issues)

## Next Steps

After successful installation:

1. **Customize** the generated Kubernetes manifests
2. **Test** deployment in development environment
3. **Set up** monitoring and logging
4. **Configure** domain and SSL certificates
5. **Implement** CI/CD pipeline