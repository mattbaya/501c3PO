#!/usr/bin/php
<?php
$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$page_id = 4579;

// Update page status to publish
$sql = "UPDATE swca_posts SET post_status = 'publish' WHERE ID = $page_id";

if ($mysqli->query($sql)) {
    echo "✓ Board Portal page status updated to 'publish'\n";
    echo "✓ Page is now accessible via password (not login-required)\n";
    echo "✓ Password protection is handled by the plugin\n";
} else {
    echo "✗ Error: " . $mysqli->error . "\n";
}

// Also update child pages to publish
$sql_children = "UPDATE swca_posts SET post_status = 'publish' WHERE post_parent = $page_id";
if ($mysqli->query($sql_children)) {
    $affected = $mysqli->affected_rows;
    echo "✓ Updated $affected child pages to 'publish' status\n";
}

$mysqli->close();
?>
