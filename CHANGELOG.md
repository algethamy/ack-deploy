# Changelog

All notable changes to `laravel-ack-deploy` will be documented in this file.

## [1.0.0] - 2025-09-13

### Added
- Initial release of Laravel ACK Deploy package
- `ack:init` command to generate ACK deployment configuration
- `ack:build` command to build Docker images for ACK
- `ack:deploy` command to deploy applications to ACK clusters
- Cost-optimized Kubernetes manifests with smart auto-scaling
- Multi-region support for Alibaba Cloud ACK
- Production-ready Dockerfile with PHP 8.3 + Apache
- Comprehensive documentation and examples

### Features
- **One-command setup** - Generate all deployment files instantly
- **Cost optimization** - Minimal resource requests with smart scaling
- **Production ready** - Health checks, resource limits, and best practices
- **Multi-region** - Support for all ACK regions worldwide
- **Easy management** - Simple Artisan commands for build and deploy

### Kubernetes Resources
- Deployment with cost-optimized resource allocation
- LoadBalancer service for external access
- Horizontal Pod Autoscaler (HPA) for smart scaling
- ConfigMap for environment configuration
- Ingress for domain-based routing
- All resources follow ACK best practices

### Docker Configuration
- Multi-stage build for optimized image size
- PHP 8.3 with Apache web server
- Laravel-specific optimizations
- SQLite database support
- Composer dependency management
- Production security configurations

### Documentation
- Complete README with usage examples
- Troubleshooting guide
- Best practices recommendations
- Cost optimization strategies
- Multi-region deployment guide