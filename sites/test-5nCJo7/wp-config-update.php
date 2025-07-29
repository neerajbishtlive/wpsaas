<?php
// Read current config
$config = file_get_contents('wp-config.php');

// Update the URLs to match our routing
$config = preg_replace(
    "/define\('WP_HOME', '.*?'\);/",
    "define('WP_HOME', 'http://localhost:8000/sites/test-5nCJo7');",
    $config
);

$config = preg_replace(
    "/define\('WP_SITEURL', '.*?'\);/",
    "define('WP_SITEURL', 'http://localhost:8000/sites/test-5nCJo7');",
    $config
);

// Write back
file_put_contents('wp-config.php', $config);
echo "Updated wp-config.php\n";
