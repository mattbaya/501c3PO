<?php
/**
 * GitHub Update Checker for 501c3PO
 * Enables automatic updates through WordPress update system
 */

class FiveOhOnecThreePO_Update_Checker {
    private $slug;
    private $plugin_data;
    private $username = 'mattbaya';
    private $repo = '501c3PO';
    private $plugin_file;
    private $github_response;

    public function __construct($plugin_file) {
        $this->plugin_file = $plugin_file;
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        add_filter('upgrader_source_selection', array($this, 'fix_folder_name'), 10, 3);
    }

    public function check_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $this->get_plugin_data();
        $this->get_github_release();

        // Check if we have a valid GitHub response with required fields
        if (empty($this->github_response) || !isset($this->github_response['tag_name'])) {
            return $transient;
        }

        $update = version_compare($this->github_response['tag_name'], $this->plugin_data['Version']);

        if ($update === 1) {
            $plugin = array(
                'slug' => $this->slug,
                'plugin' => $this->plugin_file,
                'new_version' => $this->github_response['tag_name'],
                'url' => $this->plugin_data['PluginURI'],
                'package' => $this->github_response['zipball_url'] ?? '',
                'icons' => array(
                    '1x' => 'https://raw.githubusercontent.com/' . $this->username . '/' . $this->repo . '/main/assets/icon-128x128.png',
                    '2x' => 'https://raw.githubusercontent.com/' . $this->username . '/' . $this->repo . '/main/assets/icon-256x256.png'
                ),
                'tested' => '6.4',
                'requires' => '5.0',
                'requires_php' => '7.4'
            );

            $transient->response[$this->plugin_file] = (object) $plugin;
        }

        return $transient;
    }

    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return false;
        }

        if ($args->slug !== $this->slug) {
            return false;
        }

        $this->get_plugin_data();
        $this->get_github_release();

        // Check if we have a valid GitHub response
        if (empty($this->github_response) || !isset($this->github_response['tag_name'])) {
            return false;
        }

        $plugin = array(
            'name' => $this->plugin_data['Name'],
            'slug' => $this->slug,
            'version' => $this->github_response['tag_name'],
            'author' => $this->plugin_data['Author'],
            'author_profile' => $this->plugin_data['AuthorURI'] ?? '',
            'last_updated' => $this->github_response['published_at'] ?? '',
            'homepage' => $this->plugin_data['PluginURI'],
            'short_description' => $this->plugin_data['Description'],
            'sections' => array(
                'description' => $this->plugin_data['Description'],
                'updates' => $this->github_response['body'] ?? '',
            ),
            'download_link' => $this->github_response['zipball_url'] ?? ''
        );

        return (object) $plugin;
    }

    public function fix_folder_name($source, $remote_source, $upgrader) {
        global $wp_filesystem;
        
        if (strpos($source, $this->repo) === false) {
            return $source;
        }

        $corrected_source = trailingslashit($remote_source) . trailingslashit($this->repo);
        
        if ($wp_filesystem->move($source, $corrected_source, true)) {
            return $corrected_source;
        }

        return $source;
    }

    private function get_plugin_data() {
        $this->slug = plugin_basename(dirname($this->plugin_file));
        $this->plugin_data = get_plugin_data($this->plugin_file);
    }

    private function get_github_release() {
        $request_uri = 'https://api.github.com/repos/' . $this->username . '/' . $this->repo . '/releases/latest';
        
        $response = wp_remote_get($request_uri, array(
            'timeout' => 10,
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json'
            )
        ));

        if (is_wp_error($response)) {
            return false;
        }

        $this->github_response = json_decode(wp_remote_retrieve_body($response), true);
    }
}