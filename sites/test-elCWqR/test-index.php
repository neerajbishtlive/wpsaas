<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>WordPress Index Debug</h1>";

try {
    echo "<p>1. Loading wp-blog-header.php...</p>";
    require( dirname( __FILE__ ) . '/wp-blog-header.php' );
    echo "<p style='color: green;'>✅ wp-blog-header.php loaded successfully!</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error loading wp-blog-header.php: " . $e->getMessage() . "</p>";
    echo "<p>Error trace: " . $e->getTraceAsString() . "</p>";
} catch (Error $e) {
    echo "<p style='color: red;'>❌ Fatal error in wp-blog-header.php: " . $e->getMessage() . "</p>";
    echo "<p>Error trace: " . $e->getTraceAsString() . "</p>";
}
?>
EOF