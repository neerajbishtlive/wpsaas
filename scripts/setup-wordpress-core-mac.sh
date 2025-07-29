#!/bin/bash

# WordPress Core Setup Script - macOS Version
set -e

# Get project root
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

# Configuration
WP_CORE_PATH="$PROJECT_ROOT/wordpress-core"
TEMP_DIR="/tmp/wordpress-setup"

echo "WordPress Core Setup for SaaS Platform (macOS)"
echo "=============================================="
echo "Project root: $PROJECT_ROOT"

# Check if WP-CLI is installed
if ! command -v wp &> /dev/null; then
    echo "Installing WP-CLI..."
    brew install wp-cli || {
        curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
        chmod +x wp-cli.phar
        sudo mv wp-cli.phar /usr/local/bin/wp
    }
fi

# Create directories
echo "Creating directory structure..."
mkdir -p "$WP_CORE_PATH"
mkdir -p "$PROJECT_ROOT/sites"
mkdir -p "$PROJECT_ROOT/storage/backups"
mkdir -p "$PROJECT_ROOT/nginx"
mkdir -p "$TEMP_DIR"

# Download WordPress
echo "Downloading latest WordPress..."
cd "$TEMP_DIR"
wp core download --locale=en_US --force

# Move core files
echo "Setting up WordPress core files..."
if [ -d "$TEMP_DIR/wordpress" ]; then
    cp -R "$TEMP_DIR/wordpress/"* "$WP_CORE_PATH/"
    rm -rf "$TEMP_DIR/wordpress"
else
    # WP-CLI might extract directly to current directory
    cp -R "$TEMP_DIR/"*.php "$WP_CORE_PATH/" 2>/dev/null || true
    cp -R "$TEMP_DIR/wp-"* "$WP_CORE_PATH/" 2>/dev/null || true
fi

# Download themes and plugins
cd "$WP_CORE_PATH"

echo "Downloading themes..."
mkdir -p "$WP_CORE_PATH/wp-content/themes"
cd "$WP_CORE_PATH/wp-content/themes"
for theme in astra neve oceanwp storefront twentytwentyfour; do
    echo "  - Downloading $theme..."
    curl -s -L "https://downloads.wordpress.org/theme/${theme}.zip" -o "${theme}.zip"
    unzip -qo "${theme}.zip"
    rm "${theme}.zip"
done

echo "Downloading plugins..."
mkdir -p "$WP_CORE_PATH/wp-content/plugins"
cd "$WP_CORE_PATH/wp-content/plugins"
for plugin in wordfence wp-optimize contact-form-7 wordpress-seo updraftplus classic-editor woocommerce; do
    echo "  - Downloading $plugin..."
    curl -s -L "https://downloads.wordpress.org/plugin/${plugin}.zip" -o "${plugin}.zip"
    unzip -qo "${plugin}.zip"
    rm "${plugin}.zip"
done

# Create mu-plugins
mkdir -p "$WP_CORE_PATH/wp-content/mu-plugins"

# Create platform integration plugin
cat > "$WP_CORE_PATH/wp-content/mu-plugins/saas-platform.php" << 'EOF'
<?php
/**
 * SaaS Platform Integration
 */

// Disable automatic updates
add_filter('automatic_updater_disabled', '__return_true');

// Custom admin footer
add_filter('admin_footer_text', function() {
    return 'Powered by WP SaaS Platform';
});

// Security headers
add_action('send_headers', function() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
});
EOF

# Update .env configuration
echo "Updating .env configuration..."
cd "$PROJECT_ROOT/control-panel"

# Copy .env.example to .env if it doesn't exist
if [ ! -f .env ]; then
    cp .env.example .env
    echo "Created .env from .env.example"
fi

# Update paths in .env for macOS
sed -i '' "s|WP_CORE_PATH=.*|WP_CORE_PATH=$WP_CORE_PATH|" .env
sed -i '' "s|SITES_PATH=.*|SITES_PATH=$PROJECT_ROOT/sites|" .env
sed -i '' "s|STORAGE_PATH=.*|STORAGE_PATH=$PROJECT_ROOT/storage|" .env
sed -i '' "s|SCRIPTS_PATH=.*|SCRIPTS_PATH=$PROJECT_ROOT/scripts|" .env

# Make install script executable
chmod +x "$PROJECT_ROOT/scripts/install-wordpress.sh"

# Cleanup
rm -rf "$TEMP_DIR"

echo ""
echo "✓ WordPress core setup completed!"
echo "Core path: $WP_CORE_PATH"
echo ""
echo "Next steps:"
echo "1. Edit .env file with your database credentials"
echo "2. Run: cd $PROJECT_ROOT/control-panel && composer install"
echo "3. Run: php artisan key:generate"
echo "4. Run: php artisan migrate"
echo "5. Test with: php artisan test:site-creation"

exit 0