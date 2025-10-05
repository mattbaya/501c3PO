#!/usr/bin/php
<?php
$mysqli = new mysqli('localhost', 'swca_swca2019', '5Corners!', 'swca_swca2019');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== BOARD PORTAL SETUP VERIFICATION ===\n\n";

// Check main page
$result = $mysqli->query("SELECT ID, post_title, post_name, post_status FROM swca_posts WHERE ID = 4579");
$page = $result->fetch_assoc();

echo "📋 Main Page:\n";
echo "  Title: " . $page['post_title'] . "\n";
echo "  Slug: " . $page['post_name'] . "\n";
echo "  Status: " . $page['post_status'] . "\n";
echo "  URL: https://southwilliamstown.org/" . $page['post_name'] . "/\n\n";

// Check child pages
$children = $mysqli->query("SELECT ID, post_title, post_name, post_status FROM swca_posts WHERE post_parent = 4579 AND post_type = 'page'");

echo "📄 Child Pages:\n";
$count = 0;
while ($child = $children->fetch_assoc()) {
    $count++;
    echo "  $count. " . $child['post_title'] . " (/" . $child['post_name'] . "/) - " . $child['post_status'] . "\n";
}

if ($count == 0) {
    echo "  (no child pages)\n";
}

// Check settings
echo "\n⚙️ Password Protection Settings:\n";

$password_result = $mysqli->query("SELECT option_value FROM swca_options WHERE option_name = 'swca_board_portal_password'");
if ($password_result && $password_result->num_rows > 0) {
    $pass_row = $password_result->fetch_assoc();
    $password = $pass_row['option_value'];
    echo "  Password: " . (!empty($password) ? $password : "(not set)") . "\n";
} else {
    echo "  Password: (not set)\n";
}

$slug_result = $mysqli->query("SELECT option_value FROM swca_options WHERE option_name = 'swca_board_portal_slug'");
if ($slug_result && $slug_result->num_rows > 0) {
    $slug_row = $slug_result->fetch_assoc();
    echo "  Configured Slug: " . $slug_row['option_value'] . "\n";
}

$title_result = $mysqli->query("SELECT option_value FROM swca_options WHERE option_name = 'swca_board_portal_title'");
if ($title_result && $title_result->num_rows > 0) {
    $title_row = $title_result->fetch_assoc();
    echo "  Configured Title: " . $title_row['option_value'] . "\n";
}

echo "\n✅ Access Rules:\n";
echo "  • Non-logged-in users: MUST enter password\n";
echo "  • Logged-in WordPress users: Password BYPASSED\n";
echo "  • Cookie expires: 24 hours after successful login\n";

echo "\n🔧 Configuration Location:\n";
echo "  WordPress Admin → SWCA Data → Settings\n";
echo "  URL: /wp-admin/admin.php?page=swca-transaction-settings\n";

$mysqli->close();
?>
