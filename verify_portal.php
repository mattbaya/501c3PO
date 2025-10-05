#!/usr/bin/php
<?php
$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$result = $mysqli->query("SELECT post_title, post_name, post_status FROM swca_posts WHERE ID = 4579");
$page = $result->fetch_assoc();

echo "✓ Board Portal Page Status:\n";
echo "  Title: " . $page['post_title'] . "\n";
echo "  Slug: " . $page['post_name'] . "\n";
echo "  Status: " . $page['post_status'] . "\n";
echo "  URL: https://southwilliamstown.org/" . $page['post_name'] . "/\n\n";

// Check options
$options_result = $mysqli->query("SELECT option_name, option_value FROM swca_options WHERE option_name LIKE 'swca_board_portal%'");

echo "✓ Board Portal Settings:\n";
while ($opt = $options_result->fetch_assoc()) {
    $value = $opt['option_value'];
    if ($opt['option_name'] == 'swca_board_portal_password' && !empty($value)) {
        $value = '********';
    }
    echo "  " . $opt['option_name'] . ": " . $value . "\n";
}

$mysqli->close();
?>
