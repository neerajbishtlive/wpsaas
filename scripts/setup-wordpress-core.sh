#!/bin/bash

# WordPress Core Setup Script
# This script downloads and prepares WordPress core files for the SaaS platform

set -e

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

# Configuration
WP_CORE_PATH="/home/wp-saas-platform/wordpress-core"
TEMP_DIR="/tmp/wordpress-setup"

echo -e "${GREEN}WordPress Core Setup for SaaS Platform${NC}"
echo "======================================="

# Check if running as appropriate user
if [ "$EUID" -eq 0 ]; then 
   echo -e "${RED}Please don't run this script as root!${NC}"
   exit 1
fi

# Check if WP-CLI is installed
if ! command -v wp &> /dev/null; then
    echo -e "${YELLOW}Installing WP-CLI...${NC}"
    curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
    chmod +x wp-cli.phar
    sudo mv wp-cli.phar /usr/local/bin/wp
fi

# Create directories
echo -e "${YELLOW}Creating directory structure...${NC}"
mkdir -p "$WP_CORE_PATH"
mkdir -p "$TEMP_DIR"

# Download WordPress
echo -e "${YELLOW}Downloading latest WordPress...${NC}"
cd "$TEMP_DIR"
wp core download --locale=en_US

# Move core files
echo -e "${YELLOW}Setting up WordPress core files...${NC}"
mv wordpress/* "$WP_CORE_PATH/"
rmdir wordpress

# Download popular themes
echo -e "${YELLOW}Downloading themes...${NC}"
cd "$WP_CORE_PATH"

# Free themes for templates
wp theme install astra neve oceanwp storefront twentytwentyfour \
    --path="$WP_CORE_PATH" \
    --activate-network

# Download essential plugins
echo -e "${YELLOW}Downloading essential plugins...${NC}"

# Security & Performance
wp plugin install wordfence wp-optimize wp-super-cache \
    --path="$WP_CORE_PATH"

# SEO & Marketing
wp plugin install wordpress-seo google-analytics-for-wordpress \
    --path="$WP_CORE_PATH"

# Functionality
wp plugin install contact-form-7 updraftplus classic-editor \
    --path="$WP_CORE_PATH"

# Ecommerce
wp plugin install woocommerce \
    --path="$WP_CORE_PATH"

# Portfolio
wp plugin install portfolio-post-type \
    --path="$WP_CORE_PATH"

# Create optimized .htaccess
echo -e "${YELLOW}Creating optimized .htaccess...${NC}"
cat > "$WP_CORE_PATH/.htaccess" << 'EOF'
# BEGIN WordPress
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /
RewriteRule ^index\.php$ - [L]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule . /index.php [L]
</IfModule>
# END WordPress

# Security Headers
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
    Header set Referrer-Policy "no-referrer-when-downgrade"
</IfModule>

# Compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json
</IfModule>

# Browser Caching
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType text/javascript "access plus 1 month"
</IfModule>

# Disable directory browsing
Options -Indexes

# Protect important files
<FilesMatch "^(wp-config\.php|\.htaccess|\.git.*|\.env|composer\.(json|lock))$">
    Require all denied
</FilesMatch>
EOF

# Create mu-plugins directory for platform-specific code
echo -e "${YELLOW}Creating mu-plugins directory...${NC}"
mkdir -p "$WP_CORE_PATH/wp-content/mu-plugins"

# Create platform integration plugin
cat > "$WP_CORE_PATH/wp-content/mu-plugins/saas-platform.php" << 'EOF'
<?php
/**
 * SaaS Platform Integration
 * Must-use plugin for WordPress SaaS sites
 */

// Disable automatic updates (handled by platform)
add_filter('automatic_updater_disabled', '__return_true');

// Custom admin footer
add_filter('admin_footer_text', function() {
    return 'Powered by WP SaaS Platform | <a href="/support">Get Support</a>';
});

// Resource usage widget
add_action('wp_dashboard_setup', function() {
    wp_add_dashboard_widget(
        'saas_resource_usage',
        'Resource Usage',
        function() {
            $site_id = defined('SAAS_SITE_ID') ? SAAS_SITE_ID : 'unknown';
            echo '<p>View detailed usage statistics in your <a href="https://platform.example.com/sites/' . $site_id . '/manage" target="_blank">control panel</a>.</p>';
        }
    );
});

// Disable file editing
define('DISALLOW_FILE_EDIT', true);
define('DISALLOW_FILE_MODS', true);

// Performance optimizations
add_filter('wp_revisions_to_keep', function() { return 5; });
add_filter('big_image_size_threshold', function() { return 2560; });

// Security headers
add_action('send_headers', function() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
});
EOF

# Set permissions
echo -e "${YELLOW}Setting permissions...${NC}"
find "$WP_CORE_PATH" -type d -exec chmod 755 {} \;
find "$WP_CORE_PATH" -type f -exec chmod 644 {} \;

# Create version file
echo -e "${YELLOW}Creating version file...${NC}"
wp core version --path="$WP_CORE_PATH" > "$WP_CORE_PATH/version.txt"
date >> "$WP_CORE_PATH/version.txt"

# Cleanup
rm -rf "$TEMP_DIR"

echo -e "${GREEN}✓ WordPress core setup completed!${NC}"
echo -e "Core path: $WP_CORE_PATH"
echo -e "WordPress version: $(wp core version --path="$WP_CORE_PATH")"
echo -e ""
echo -e "${YELLOW}Next steps:${NC}"
echo -e "1. Configure your .env file"
echo -e "2. Run database migrations"
echo -e "3. Set up nginx configuration"
echo -e "4. Start queue workers"

exit 0