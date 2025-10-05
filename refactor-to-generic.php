<?php
/**
 * Automated Refactoring Script
 * Converts SWCA-specific plugin to organization-neutral plugin
 *
 * Usage: php refactor-to-generic.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

class PluginRefactorer {
    private $changes = array();
    private $files_processed = 0;
    private $backup_dir = '';

    public function __construct() {
        $this->backup_dir = __DIR__ . '/backups-' . date('Y-m-d-H-i-s');
        if (!file_exists($this->backup_dir)) {
            mkdir($this->backup_dir, 0755, true);
        }
    }

    /**
     * Main refactoring process
     */
    public function refactor() {
        echo "🔧 Starting Plugin Refactoring...\n\n";

        // Files to refactor
        $files = array(
            'deployment-packages/swca-membership-management/swca-membership-management.php',
            'deployment-packages/swca-membership-management.php',
            'swca-membership-export-corrected.php',
            'wordpress-membership-management/includes/features/email-management.php',
        );

        foreach ($files as $file) {
            $full_path = __DIR__ . '/' . $file;
            if (file_exists($full_path)) {
                $this->refactorFile($full_path);
            } else {
                echo "⚠️  File not found: $file\n";
            }
        }

        echo "\n✅ Refactoring Complete!\n";
        echo "📊 Files processed: {$this->files_processed}\n";
        echo "📝 Changes made: " . count($this->changes) . "\n";
        echo "💾 Backups saved to: {$this->backup_dir}\n\n";

        $this->printChangesSummary();
    }

    /**
     * Refactor a single file
     */
    private function refactorFile($file_path) {
        echo "Processing: " . basename($file_path) . "\n";

        // Backup original
        $backup_path = $this->backup_dir . '/' . basename($file_path);
        copy($file_path, $backup_path);

        $content = file_get_contents($file_path);
        $original_content = $content;

        // Apply refactorings
        $content = $this->refactorPluginHeader($content);
        $content = $this->refactorTableNames($content);
        $content = $this->refactorRoleNames($content);
        $content = $this->refactorDisplayText($content);
        $content = $this->refactorShortcodes($content);
        $content = $this->refactorDashboardPassword($content);

        // Save if changed
        if ($content !== $original_content) {
            file_put_contents($file_path, $content);
            $this->files_processed++;
            echo "  ✓ Updated\n";
        } else {
            echo "  - No changes needed\n";
        }
    }

    /**
     * Refactor plugin header
     */
    private function refactorPluginHeader($content) {
        $patterns = array(
            '/Plugin Name: SWCA Membership Export/' => 'Plugin Name: Non-Profit Membership Management',
            '/Description: SWCA membership display/' => 'Description: Complete membership management system for non-profit organizations/',
        );

        foreach ($patterns as $pattern => $replacement) {
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $replacement, $content);
                $this->logChange('Plugin Header', $pattern, $replacement);
            }
        }

        return $content;
    }

    /**
     * Refactor table names to use dynamic prefix
     */
    private function refactorTableNames($content) {
        // Replace hardcoded table names with npo_get_table_name() calls
        $patterns = array(
            '/\$wpdb->prefix\s*\.\s*[\'"]swca_(\w+)[\'"]/' => 'npo_get_table_name(\'$1\')',
            '/wp_swca_(\w+)/' => '\' . npo_get_table_name(\'$1\') . \'',
        );

        foreach ($patterns as $pattern => $replacement) {
            $matches_count = preg_match_all($pattern, $content);
            if ($matches_count) {
                $content = preg_replace($pattern, $replacement, $content);
                $this->logChange('Table Names', "Pattern: $pattern", "$matches_count occurrences updated");
            }
        }

        return $content;
    }

    /**
     * Refactor role names to use dynamic org name
     */
    private function refactorRoleNames($content) {
        $role_replacements = array(
            '"SWCA Member"' => 'npo_get_role_name(\'member\')',
            '"SWCA Officer"' => 'npo_get_role_name(\'officer\')',
            '"SWCA Treasurer"' => 'npo_get_role_name(\'treasurer\')',
            '"SWCA Committee Chair"' => 'npo_get_role_name(\'committee_chair\')',
            '\'swca_member\'' => 'npo_get_role_slug(\'member\')',
            '\'swca_officer\'' => 'npo_get_role_slug(\'officer\')',
            '\'swca_treasurer\'' => 'npo_get_role_slug(\'treasurer\')',
            '\'swca_committee_chair\'' => 'npo_get_role_slug(\'committee_chair\')',
        );

        foreach ($role_replacements as $find => $replace) {
            if (strpos($content, $find) !== false) {
                $content = str_replace($find, $replace, $content);
                $this->logChange('Role Names', $find, $replace);
            }
        }

        return $content;
    }

    /**
     * Refactor user-facing display text
     */
    private function refactorDisplayText($content) {
        $text_replacements = array(
            'SWCA Member Dashboard' => '\' . npo_get_org_short_name() . \' Member Dashboard',
            'SWCA Dashboard Access' => '\' . npo_get_org_short_name() . \' Dashboard Access',
            'Welcome to the SWCA member dashboard' => 'Welcome to the \' . npo_get_org_name() . \' member dashboard',
            'SWCA Membership Statistics' => '\' . npo_get_org_short_name() . \' Membership Statistics',
            'SWCA Fiscal Year' => '\' . npo_get_org_short_name() . \' Fiscal Year',
            'SWCA Settings & Configuration' => '\' . npo_get_org_short_name() . \' Settings & Configuration',
            'SWCA Member Contact Directory' => '\' . npo_get_org_short_name() . \' Member Contact Directory',
        );

        foreach ($text_replacements as $find => $replace) {
            // Need to handle both single and double quoted strings
            $patterns = array(
                '/"' . preg_quote($find, '/') . '"/' => '"' . $replace . '"',
                '/\'' . preg_quote($find, '/') . '\'/' => '\'' . $replace . '\'',
            );

            foreach ($patterns as $pattern => $replacement) {
                if (preg_match($pattern, $content)) {
                    $content = preg_replace($pattern, $replacement, $content);
                    $this->logChange('Display Text', $find, $replace);
                    break;
                }
            }
        }

        return $content;
    }

    /**
     * Refactor shortcodes (if needed)
     */
    private function refactorShortcodes($content) {
        // Shortcodes can stay as swca_ for backward compatibility
        // Or we can make them generic - let's keep them for now
        // Future: could add shortcode aliases
        return $content;
    }

    /**
     * Refactor dashboard password
     */
    private function refactorDashboardPassword($content) {
        $pattern = '/[\'"]F1v3C0rn3rs[\'"]/';
        $replacement = 'npo_get_dashboard_password()';

        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $replacement, $content);
            $this->logChange('Dashboard Password', 'F1v3C0rn3rs', 'npo_get_dashboard_password()');
        }

        return $content;
    }

    /**
     * Log a change
     */
    private function logChange($category, $from, $to) {
        $this->changes[] = array(
            'category' => $category,
            'from' => $from,
            'to' => $to,
        );
    }

    /**
     * Print changes summary
     */
    private function printChangesSummary() {
        $categories = array();
        foreach ($this->changes as $change) {
            $cat = $change['category'];
            if (!isset($categories[$cat])) {
                $categories[$cat] = 0;
            }
            $categories[$cat]++;
        }

        echo "📋 Changes by Category:\n";
        foreach ($categories as $category => $count) {
            echo "  • $category: $count changes\n";
        }
    }
}

// Run refactoring
$refactorer = new PluginRefactorer();
$refactorer->refactor();

echo "\n🎯 Next Steps:\n";
echo "1. Review the changes in the updated files\n";
echo "2. Add 'require_once' statements for config-helpers.php and setup-wizard.php to main plugin file\n";
echo "3. Test the plugin in WordPress\n";
echo "4. Run migration script for existing SWCA data (if needed)\n";
echo "\n";
