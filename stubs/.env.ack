# ACK Deployment Environment Variables
# Copy this to .env and customize as needed

APP_NAME={{ APP_NAME }}
DOCKER_REGISTRY={{ REGISTRY }}
K8S_NAMESPACE={{ NAMESPACE }}
DOMAIN={{ DOMAIN }}

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
MAIL_FROM_ADDRESS=hello@{{ DOMAIN }}
MAIL_FROM_NAME="{{ APP_NAME }}"