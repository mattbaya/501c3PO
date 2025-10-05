#!/usr/bin/php
<?php
$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$page_id = 4579;
$new_slug = 'board-portal';

// Update the page slug
$sql = "UPDATE swca_posts SET post_name = '$new_slug' WHERE ID = $page_id";

if ($mysqli->query($sql)) {
    echo "✓ Page slug updated successfully\n";
    echo "✓ Old slug: financial-reports\n";
    echo "✓ New slug: $new_slug\n";
    echo "✓ New URL: https://southwilliamstown.org/$new_slug/\n";
} else {
    echo "✗ Error: " . $mysqli->error . "\n";
}

// Set initial option value for the slug
$check_option = $mysqli->query("SELECT option_value FROM swca_options WHERE option_name = 'swca_board_portal_slug'");

if ($check_option->num_rows == 0) {
    // Insert initial value
    $mysqli->query("INSERT INTO swca_options (option_name, option_value, autoload) VALUES ('swca_board_portal_slug', '$new_slug', 'yes')");
    echo "✓ Created option: swca_board_portal_slug = $new_slug\n";
} else {
    // Update existing value
    $mysqli->query("UPDATE swca_options SET option_value = '$new_slug' WHERE option_name = 'swca_board_portal_slug'");
    echo "✓ Updated option: swca_board_portal_slug = $new_slug\n";
}

$mysqli->close();
?>
