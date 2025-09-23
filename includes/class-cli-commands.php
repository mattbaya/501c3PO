<?php
/**
 * WP-CLI Commands for 501c3PO
 */

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

class FiveOhOnecThreePO_CLI_Commands {
    
    /**
     * Update the plugin from GitHub
     * 
     * ## EXAMPLES
     * 
     *     wp 501c3po update
     *
     * @when after_wp_load
     */
    public function update() {
        $plugin_dir = WP_PLUGIN_DIR . '/501c3PO';
        
        if (!file_exists($plugin_dir . '/.git')) {
            WP_CLI::error('This plugin was not installed via git. Use WordPress updates instead.');
            return;
        }
        
        // Save current directory
        $original_dir = getcwd();
        
        // Change to plugin directory
        chdir($plugin_dir);
        
        // Fetch latest changes
        WP_CLI::log('Fetching latest changes from GitHub...');
        $fetch_result = shell_exec('git fetch origin main 2>&1');
        WP_CLI::log($fetch_result);
        
        // Check if there are updates
        $status = shell_exec('git status -uno 2>&1');
        
        if (strpos($status, 'Your branch is up to date') !== false) {
            WP_CLI::success('Plugin is already up to date!');
            chdir($original_dir);
            return;
        }
        
        // Pull latest changes
        WP_CLI::log('Pulling latest changes...');
        $pull_result = shell_exec('git pull origin main 2>&1');
        WP_CLI::log($pull_result);
        
        // Return to original directory
        chdir($original_dir);
        
        // Clear caches
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
            WP_CLI::log('Cache cleared.');
        }
        
        WP_CLI::success('Plugin updated successfully!');
    }
    
    /**
     * Check for available updates
     * 
     * ## EXAMPLES
     * 
     *     wp 501c3po check-update
     *
     * @when after_wp_load
     */
    public function check_update() {
        $plugin_dir = WP_PLUGIN_DIR . '/501c3PO';
        
        if (!file_exists($plugin_dir . '/.git')) {
            WP_CLI::error('This plugin was not installed via git.');
            return;
        }
        
        $original_dir = getcwd();
        chdir($plugin_dir);
        
        // Fetch without merging
        shell_exec('git fetch origin main 2>&1');
        
        // Check difference
        $diff = shell_exec('git rev-list HEAD...origin/main --count 2>&1');
        $commits_behind = intval(trim($diff));
        
        chdir($original_dir);
        
        if ($commits_behind > 0) {
            WP_CLI::warning("Plugin is $commits_behind commits behind. Run 'wp 501c3po update' to update.");
        } else {
            WP_CLI::success('Plugin is up to date!');
        }
    }
}

// Register commands
WP_CLI::add_command('501c3po', 'FiveOhOnecThreePO_CLI_Commands');