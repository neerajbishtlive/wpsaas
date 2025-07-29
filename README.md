# Sites Directory

This directory contains individual WordPress site installations. Each subdomain gets its own folder here.

## Structure
```
sites/
└── [subdomain]/
    ├── wp-config.php      # Site-specific WordPress configuration
    └── wp-content/        # Site-specific content
        ├── themes/        # Custom themes
        ├── plugins/       # Custom plugins
        └── uploads/       # Media uploads
```

## Important Notes
- This directory is ignored by Git (see .gitignore)
- Each site directory is created automatically when a new site is provisioned
- Sites are accessed via subdomains (e.g., `mysite.wpsaas.in`)
- The WordPress core files are shared and located in `/wordpress-core`

## Local Development
When cloning this repository, the sites directory will be empty. Sites are created through the platform's admin panel or via the provisioning service.