#!/bin/bash

# Academic Finder Backend Deployment Script

echo "🚀 Starting deployment..."

# Pull latest changes
echo "📥 Pulling latest changes from git..."
git pull origin main

# Install/update composer dependencies
echo "📦 Installing composer dependencies..."
composer install --no-dev --optimize-autoloader

# Clear and cache config
echo "⚙️ Clearing and caching configuration..."
php artisan config:clear
php artisan config:cache

# Clear and cache routes
echo "🛣️ Clearing and caching routes..."
php artisan route:clear
php artisan route:cache

# Clear and cache views
echo "👁️ Clearing and caching views..."
php artisan view:clear
php artisan view:cache

# Run migrations
echo "🗄️ Running database migrations..."
php artisan migrate --force

# Generate Swagger documentation
echo "📚 Generating API documentation..."
php artisan l5-swagger:generate

# Set proper permissions
echo "🔐 Setting proper permissions..."
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Restart queue workers (if using)
# echo "🔄 Restarting queue workers..."
# php artisan queue:restart

echo "✅ Deployment completed successfully!"

