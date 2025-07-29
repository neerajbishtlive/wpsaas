# Subdomain Setup Instructions

## Step 1: Update Your Hosts File

### On Mac/Linux:
```bash
sudo nano /etc/hosts
```

### On Windows (Run as Administrator):
```
notepad C:\Windows\System32\drivers\etc\hosts
```

### Add these lines:
```
# WordPress SaaS Platform
127.0.0.1   wptest.local
127.0.0.1   control.wptest.local
127.0.0.1   test-5nCJo7.wptest.local
127.0.0.1   demo-site.wptest.local
127.0.0.1   my-blog.wptest.local
# Add more as you create new sites
```

## Step 2: Place the Files

1. Copy the updated `.env` to `control-panel/.env`
2. Create `control-panel/config/saas.php`
3. Update `control-panel/app/Models/Site.php`
4. Replace `control-panel/public/wp-config-template.php`
5. Replace `control-panel/public/wp.php`
6. Create `control-panel/server.php`

## Step 3: Update Database

Run this SQL to update existing sites with proper URLs:

```sql
USE wp_saas_control;

-- Update your test site
UPDATE sites 
SET site_url = 'http://test-5nCJo7.wptest.local:8000',
    wp_admin_url = 'http://test-5nCJo7.wptest.local:8000/wp-admin'
WHERE subdomain = 'test-5nCJo7';

-- Update WordPress options
UPDATE wp_test5nCJo7_options 
SET option_value = 'http://test-5nCJo7.wptest.local:8000'
WHERE option_name IN ('home', 'siteurl');
```

## Step 4: Start the Development Server

```bash
cd ~/Desktop/Diploy/GIT/wp-saas-platform/control-panel
php -S 0.0.0.0:8000 server.php
```

## Step 5: Access Your Sites

### Control Panel:
- http://control.wptest.local:8000
- http://localhost:8000 (also works)

### WordPress Sites:
- http://test-5nCJo7.wptest.local:8000
- http://test-5nCJo7.wptest.local:8000/wp-admin

### Creating New Sites:
When you create a site with subdomain "my-blog":
1. Add `127.0.0.1   my-blog.wptest.local` to hosts file
2. Access at http://my-blog.wptest.local:8000

## Step 6: Update Your Views

In all your Blade templates, replace:
```html
<!-- OLD -->
<a href="/wp.php?site={{ $site->subdomain }}">Visit</a>

<!-- NEW -->
<a href="{{ $site->url }}">Visit</a>
<a href="{{ $site->admin_url }}">Admin</a>
```

## Production Deployment

For production on wpsaas.in:

1. Set DNS wildcard: `*.wpsaas.in` → Your Server IP
2. Update `.env`:
   ```
   APP_ENV=production
   APP_DOMAIN=wpsaas.in
   APP_PROTOCOL=https
   ```
3. Configure Nginx/Apache for wildcard subdomains
4. Install SSL certificate for `*.wpsaas.in`

The system will automatically use the production domain!