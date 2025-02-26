<?php
/**
 * Plugin Name: Author Profile Updater
 * Plugin URI: https://example.com/author-profile-updater
 * Description: Automatically map guest authors to WordPress user accounts based on emails
 * Version: 1.1.1
 * Author: PublishPress
 * Author URI: https://example.com
 * Text Domain: author-profile-updater
 * Domain Path: /languages
 * License: GPL2
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('APU_VERSION', '1.1.1');
define('APU_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('APU_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class
 */
class Author_Profile_Updater {
    /**
     * Instance of the class
     *
     * @var Author_Profile_Updater
     */
    private static $instance = null;

    /**
     * Get instance of the class
     *
     * @return Author_Profile_Updater
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Initialize the plugin
        add_action('plugins_loaded', array($this, 'init'));
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Load text domain for translations
        load_plugin_textdomain('author-profile-updater', false, dirname(plugin_basename(__FILE__)) . '/languages');

        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));

        // Register admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'register_admin_scripts'));

        // Register AJAX handlers
        add_action('wp_ajax_apu_update_authors', array($this, 'ajax_update_authors'));
        add_action('wp_ajax_apu_get_authors_count', array($this, 'ajax_get_authors_count'));
        add_action('wp_ajax_apu_update_specific_user', array($this, 'ajax_update_specific_user'));
        
        // Add bulk actions to the authors list
        add_filter('bulk_actions-edit-author', array($this, 'addBulkActions'));
        add_filter('handle_bulk_actions-edit-author', array($this, 'handleBulkActions'), 10, 3);
        
        // Add admin notices for bulk actions
        add_action('admin_notices', array($this, 'displayBulkActionNotices'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_management_page(
            __('Author Profile Updater', 'author-profile-updater'),
            __('Author Profile Updater', 'author-profile-updater'),
            'manage_options',
            'author-profile-updater',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Register admin scripts and styles
     */
    public function register_admin_scripts($hook) {
        if ($hook !== 'tools_page_author-profile-updater') {
            return;
        }

        wp_enqueue_style(
            'author-profile-updater-admin',
            APU_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            APU_VERSION
        );

        wp_enqueue_script(
            'author-profile-updater-admin',
            APU_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            APU_VERSION,
            true
        );

        wp_localize_script(
            'author-profile-updater-admin',
            'apuData',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('apu_nonce'),
                'updating' => __('Updating...', 'author-profile-updater'),
                'updated' => __('Updated', 'author-profile-updater'),
                'error' => __('Error', 'author-profile-updater'),
                'complete' => __('Update Complete!', 'author-profile-updater'),
                'confirmMessage' => __('Are you sure you want to update author profiles? This process cannot be undone.', 'author-profile-updater')
            )
        );
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        include APU_PLUGIN_DIR . 'views/admin-page.php';
    }

    /**
     * AJAX handler for updating authors
     */
    public function ajax_update_authors() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'apu_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'author-profile-updater')));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'author-profile-updater')));
        }

        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 50;
        $force_update = isset($_POST['force_update']) && $_POST['force_update'] === 'true';

        $result = $this->update_authors_batch($offset, $limit, $force_update);

        wp_send_json_success($result);
    }

    /**
     * AJAX handler for getting authors count
     */
    public function ajax_get_authors_count() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'apu_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'author-profile-updater')));
        }

        // Check capabilities
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'author-profile-updater')));
        }

        $count = $this->get_authors_count();

        wp_send_json_success(array('count' => $count));
    }

    /**
     * AJAX handler for updating a specific user
     */
    public function ajax_update_specific_user() {
        check_ajax_referer('apu_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('You do not have permission to perform this action.', 'author-profile-updater')]);
        }
        
        $specific_user = isset($_POST['specific_user']) ? sanitize_text_field($_POST['specific_user']) : '';
        $match_type = isset($_POST['match_type']) ? sanitize_text_field($_POST['match_type']) : 'email';
        $force_update = isset($_POST['force_update']) && $_POST['force_update'] === 'true';
        
        if (empty($specific_user)) {
            wp_send_json_error(['message' => __('No user specified.', 'author-profile-updater')]);
        }
        
        $debug_info = [];
        $debug_info['match_type'] = $match_type;
        $debug_info['force_update'] = $force_update ? 'Yes' : 'No';
        $debug_info['specific_user'] = $specific_user;
        
        // Get WP user based on match type
        $wp_user = null;
        switch ($match_type) {
            case 'email':
                $wp_user = get_user_by('email', $specific_user);
                $debug_info['wp_user_lookup'] = 'By email: ' . $specific_user;
                break;
            case 'username':
                $wp_user = get_user_by('login', $specific_user);
                $debug_info['wp_user_lookup'] = 'By username: ' . $specific_user;
                break;
            case 'display_name':
                // This requires a custom query as WP doesn't have a direct get_user_by display_name
                $users = get_users([
                    'search' => $specific_user,
                    'search_columns' => ['display_name'],
                    'number' => 1
                ]);
                if (!empty($users)) {
                    $wp_user = $users[0];
                    $debug_info['wp_user_lookup'] = 'By display name: ' . $specific_user;
                }
                break;
        }
        
        if (!$wp_user) {
            $debug_info['wp_user_found'] = 'No';
            wp_send_json_error([
                'message' => sprintf(__('No WordPress user found with %s "%s".', 'author-profile-updater'), $match_type, $specific_user),
                'debug' => $debug_info
            ]);
        }
        
        $debug_info['wp_user_found'] = 'Yes';
        $debug_info['wp_user_id'] = $wp_user->ID;
        $debug_info['wp_user_email'] = $wp_user->user_email;
        $debug_info['wp_user_login'] = $wp_user->user_login;
        $debug_info['wp_user_display_name'] = $wp_user->display_name;
        
        // Find authors to update
        $authors = $this->get_authors_to_update($wp_user, $match_type, $specific_user, $debug_info, $force_update);
        
        if (empty($authors)) {
            wp_send_json_error([
                'message' => sprintf(__('No matching authors found for WordPress user "%s".', 'author-profile-updater'), $wp_user->display_name),
                'debug' => $debug_info
            ]);
        }
        
        $updated_count = 0;
        $skipped_count = 0;
        $error_count = 0;
        
        foreach ($authors as $author) {
            $author_id = $author->term_id;
            $current_user_id = $this->get_author_user_id($author_id);
            
            // Check if author is already mapped to a different user
            if ($current_user_id && $current_user_id != $wp_user->ID) {
                $debug_info['authors'][$author_id]['already_mapped'] = 'Yes, to user ID: ' . $current_user_id;
                
                // Skip if force update is not enabled
                if (!$force_update) {
                    $debug_info['authors'][$author_id]['action'] = 'Skipped (already mapped to different user)';
                    $skipped_count++;
                    continue;
                }
                
                $debug_info['authors'][$author_id]['action'] = 'Force updating (overriding existing mapping)';
            } else {
                $debug_info['authors'][$author_id]['already_mapped'] = $current_user_id ? 'Yes, to same user' : 'No';
                $debug_info['authors'][$author_id]['action'] = 'Updating';
            }
            
            // Update the author
            $result = $this->update_author_user_id($author_id, $wp_user->ID);
            
            if ($result) {
                $updated_count++;
                $debug_info['authors'][$author_id]['update_result'] = 'Success';
            } else {
                $error_count++;
                $debug_info['authors'][$author_id]['update_result'] = 'Failed';
            }
        }
        
        $debug_info['summary'] = [
            'total_authors_found' => count($authors),
            'updated' => $updated_count,
            'skipped' => $skipped_count,
            'errors' => $error_count
        ];
        
        $message = sprintf(
            __('Found %1$d matching authors. Updated: %2$d, Skipped: %3$d, Errors: %4$d', 'author-profile-updater'),
            count($authors),
            $updated_count,
            $skipped_count,
            $error_count
        );
        
        wp_send_json_success([
            'message' => $message,
            'debug' => $debug_info
        ]);
    }

    /**
     * Get authors count
     *
     * @return int
     */
    private function get_authors_count() {
        $terms = get_terms(array(
            'taxonomy' => 'author',
            'hide_empty' => false,
            'fields' => 'count',
        ));

        return is_wp_error($terms) ? 0 : $terms;
    }

    /**
     * Update authors batch
     *
     * @param int $offset
     * @param int $limit
     * @param bool $force_update
     * @return array
     */
    private function update_authors_batch($offset, $limit, $force_update) {
        $terms = get_terms(array(
            'taxonomy' => 'author',
            'hide_empty' => false,
            'number' => $limit,
            'offset' => $offset,
        ));

        if (is_wp_error($terms) || empty($terms)) {
            return array(
                'updated' => 0,
                'total' => 0,
                'remaining' => 0,
                'message' => __('No authors found or error occurred.', 'author-profile-updater')
            );
        }

        $updated = 0;
        $skipped = 0;
        $errors = 0;
        $total = $this->get_authors_count();
        $debug_info = array(
            'already_mapped_same_user' => 0,
            'already_mapped_different_user' => 0,
            'no_email' => 0,
            'no_matching_user' => 0,
            'updated' => 0,
            'email_sources' => array(
                'term_meta_user_email' => 0,
                'term_meta_email' => 0,
                'author_email_property' => 0,
                'author_description' => 0,
                'author_meta_email' => 0
            )
        );
        
        // Debug: Dump data structure of the first author
        $author_data_structure = '';
        if (!empty($terms) && isset($terms[0])) {
            $first_term = $terms[0];
            $first_author = $this->get_author_by_term_id($first_term->term_id);
            $author_data_structure = $this->get_author_data_structure($first_author, $first_term->term_id);
            
            // Log to error log for debugging
            error_log('Author Profile Updater - Author Data Structure: ' . $author_data_structure);
        }

        foreach ($terms as $term) {
            $author = $this->get_author_by_term_id($term->term_id);
            $author_id = $term->term_id;
            $current_user_id = $this->get_author_user_id($author_id);

            // Try multiple ways to get the email
            $email = $this->get_author_email($author, $term->term_id, $debug_info['email_sources']);
            
            if (empty($email)) {
                $debug_info['no_email']++;
                $skipped++;
                continue;
            }
            
            // Get user by email (same approach as specific user update)
            $wp_user = get_user_by('email', $email);
            
            if (!$wp_user) {
                $debug_info['no_matching_user']++;
                $skipped++;
                continue;
            }
            
            // Check if author is already mapped to a user
            if ($current_user_id) {
                if ($current_user_id == $wp_user->ID) {
                    // Already mapped to the same user - update anyway
                    $debug_info['already_mapped_same_user']++;
                } else {
                    // Already mapped to a different user
                    $debug_info['already_mapped_different_user']++;
                }
            }
            
            // Always update the author regardless of current mapping
            $result = $this->update_author_user_id($author_id, $wp_user->ID);
            
            if ($result) {
                $updated++;
                $debug_info['updated']++;
            } else {
                $errors++;
            }
        }

        $remaining = $total - ($offset + count($terms));
        $remaining = max(0, $remaining);

        // Create detailed debug message
        $debug_message = sprintf(
            __('Debug info: Already mapped (same user): %d, Already mapped (different user): %d, No email: %d, No matching user: %d, Updated: %d, Errors: %d | Email sources: term_meta_user_email: %d, term_meta_email: %d, author_email_property: %d, author_description: %d, author_meta_email: %d', 'author-profile-updater'),
            $debug_info['already_mapped_same_user'],
            $debug_info['already_mapped_different_user'],
            $debug_info['no_email'],
            $debug_info['no_matching_user'],
            $debug_info['updated'],
            $errors,
            $debug_info['email_sources']['term_meta_user_email'],
            $debug_info['email_sources']['term_meta_email'],
            $debug_info['email_sources']['author_email_property'],
            $debug_info['email_sources']['author_description'],
            $debug_info['email_sources']['author_meta_email']
        );

        return array(
            'updated' => $updated,
            'total' => $total,
            'remaining' => $remaining,
            'message' => sprintf(__('Updated %d authors. %s', 'author-profile-updater'), $updated, $debug_message)
        );
    }

    /**
     * Get author email using multiple methods
     *
     * @param object $author
     * @param int $term_id
     * @param array &$email_sources Reference to count where emails were found
     * @return string
     */
    private function get_author_email($author, $term_id, &$email_sources) {
        // Method 1: Check term meta for 'user_email'
        $email = get_term_meta($term_id, 'user_email', true);
        if (!empty($email)) {
            $email_sources['term_meta_user_email']++;
            return $email;
        }
        
        // Method 2: Check term meta for 'email'
        $email = get_term_meta($term_id, 'email', true);
        if (!empty($email)) {
            $email_sources['term_meta_email']++;
            return $email;
        }
        
        // Method 3: Check author object for 'email' property
        if (isset($author->email) && !empty($author->email)) {
            $email_sources['author_email_property']++;
            return $author->email;
        }
        
        // Method 4: Check author description for email pattern
        if (isset($author->description)) {
            $matches = array();
            if (preg_match('/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', $author->description, $matches)) {
                $email = $matches[0];
                $email_sources['author_description']++;
                return $email;
            }
        }
        
        // Method 5: Check author meta for email
        if (isset($author->term_id)) {
            $email = get_term_meta($author->term_id, 'ma_email', true);
            if (!empty($email)) {
                $email_sources['author_meta_email']++;
                return $email;
            }
        }
        
        // No email found
        return '';
    }

    /**
     * Get user matching the email
     *
     * @param string $email
     * @return WP_User|false
     */
    private function get_user_matching_the_email($email) {
        $user = get_user_by('email', $email);

        if (empty($user) || is_wp_error($user)) {
            return false;
        }

        return $user;
    }

    /**
     * Get user matching the name
     *
     * @param object $author
     * @param object $term
     * @return WP_User|false
     */
    private function get_user_matching_the_name($author, $term) {
        // Try to get the display name
        $display_name = '';
        
        // Method 1: Get from term name
        if (!empty($term->name)) {
            $display_name = $term->name;
        }
        // Method 2: Get from author display_name property
        else if (isset($author->display_name) && !empty($author->display_name)) {
            $display_name = $author->display_name;
        }
        // Method 3: Get from author name property
        else if (isset($author->name) && !empty($author->name)) {
            $display_name = $author->name;
        }
        
        if (empty($display_name)) {
            return false;
        }
        
        // Search for users with matching display name
        $users = get_users(array(
            'search' => $display_name,
            'search_columns' => array('display_name'),
            'number' => 1
        ));
        
        if (!empty($users) && isset($users[0])) {
            return $users[0];
        }
        
        // Try with first and last name
        $name_parts = explode(' ', $display_name);
        
        if (count($name_parts) >= 2) {
            $first_name = $name_parts[0];
            $last_name = end($name_parts);
            
            // Search for users with matching first and last name
            $users = get_users(array(
                'meta_query' => array(
                    'relation' => 'AND',
                    array(
                        'key' => 'first_name',
                        'value' => $first_name,
                        'compare' => '='
                    ),
                    array(
                        'key' => 'last_name',
                        'value' => $last_name,
                        'compare' => '='
                    )
                ),
                'number' => 1
            ));
            
            if (!empty($users) && isset($users[0])) {
                return $users[0];
            }
        }
        
        return false;
    }

    /**
     * Check if author is mapped to user
     *
     * @param object $author
     * @return bool
     */
    private function author_is_mapped_to_user($author) {
        return isset($author->user_id) && $author->user_id > 0;
    }

    /**
     * Get author by term ID
     *
     * @param int $term_id
     * @return object
     */
    private function get_author_by_term_id($term_id) {
        // Check if PublishPress Authors is active and use its function
        if (class_exists('\MultipleAuthors\Classes\Objects\Author')) {
            return \MultipleAuthors\Classes\Objects\Author::get_by_term_id($term_id);
        }

        // Fallback implementation
        $term = get_term($term_id, 'author');
        
        if (is_wp_error($term) || empty($term)) {
            return (object) array('user_id' => 0, 'slug' => '');
        }
        
        $user_id = get_term_meta($term_id, 'user_id', true);
        
        return (object) array(
            'user_id' => intval($user_id),
            'slug' => $term->slug
        );
    }

    /**
     * Map author to user
     *
     * @param int $author_term_id
     * @param int $user_id
     */
    private function map_author_to_user($author_term_id, $user_id) {
        update_term_meta($author_term_id, 'user_id', $user_id);
    }

    /**
     * Find authors to update for a specific WordPress user
     * 
     * @param WP_User $wp_user WordPress user object
     * @param string $match_type Type of match (email, username, display_name)
     * @param string $specific_user The specific user value used for search
     * @param array &$debug_info Reference to debug info array
     * @param bool $force_update Whether to force update authors already mapped to different users
     * @return array Array of author term objects
     */
    private function get_authors_to_update($wp_user, $match_type, $specific_user, &$debug_info, $force_update = false) {
        // Get all authors
        $authors = get_terms([
            'taxonomy' => 'author',
            'hide_empty' => false,
        ]);
        
        if (is_wp_error($authors) || empty($authors)) {
            $debug_info['authors_found'] = 'No';
            return [];
        }
        
        $debug_info['authors_found'] = 'Yes';
        $debug_info['total_authors'] = count($authors);
        
        $matching_authors = [];
        $email_match_count = 0;
        $name_match_count = 0;
        
        // Initialize email sources tracking
        $email_sources = [
            'term_meta_user_email' => 0,
            'term_meta_email' => 0,
            'author_email_property' => 0,
            'author_description' => 0,
            'author_meta_email' => 0
        ];
        
        foreach ($authors as $author) {
            $author_id = $author->term_id;
            $debug_info['authors'][$author_id] = [
                'term_id' => $author_id,
                'term_name' => $author->name,
                'match_found' => 'No',
                'match_reason' => '',
            ];
            
            // Get author object from PublishPress Authors
            $author_obj = $this->get_author_by_term_id($author_id);
            if (!$author_obj) {
                $debug_info['authors'][$author_id]['author_obj_found'] = 'No';
                continue;
            }
            
            $debug_info['authors'][$author_id]['author_obj_found'] = 'Yes';
            
            // Check for email match
            if (!empty($wp_user->user_email)) {
                $author_email = $this->get_author_email($author_obj, $author_id, $email_sources);
                $debug_info['authors'][$author_id]['author_email'] = $author_email;
                
                if (!empty($author_email) && strtolower($author_email) === strtolower($wp_user->user_email)) {
                    $matching_authors[] = $author;
                    $email_match_count++;
                    $debug_info['authors'][$author_id]['match_found'] = 'Yes';
                    $debug_info['authors'][$author_id]['match_reason'] = 'Email match';
                    continue;
                }
            }
            
            // Check for name match if match type is username or display_name
            if ($match_type === 'username' || $match_type === 'display_name') {
                // Get author display name
                $author_name = '';
                if (!empty($author->name)) {
                    $author_name = $author->name;
                } else if (isset($author_obj->display_name) && !empty($author_obj->display_name)) {
                    $author_name = $author_obj->display_name;
                } else if (isset($author_obj->name) && !empty($author_obj->name)) {
                    $author_name = $author_obj->name;
                }
                
                $debug_info['authors'][$author_id]['author_name'] = $author_name;
                
                // Compare with user's display name or username
                $user_compare = ($match_type === 'display_name') ? $wp_user->display_name : $wp_user->user_login;
                
                if (!empty($author_name) && strtolower($author_name) === strtolower($user_compare)) {
                    $matching_authors[] = $author;
                    $name_match_count++;
                    $debug_info['authors'][$author_id]['match_found'] = 'Yes';
                    $debug_info['authors'][$author_id]['match_reason'] = ($match_type === 'display_name') ? 'Display name match' : 'Username match';
                }
            }
        }
        
        $debug_info['match_summary'] = [
            'email_matches' => $email_match_count,
            'name_matches' => $name_match_count,
            'total_matches' => count($matching_authors),
            'email_sources' => $email_sources
        ];
        
        return $matching_authors;
    }

    /**
     * Get the user ID associated with an author
     * 
     * @param int $author_id The author term ID
     * @return int|false The user ID or false if not found
     */
    private function get_author_user_id($author_id) {
        // First try to get from term meta
        $user_id = get_term_meta($author_id, 'user_id', true);
        
        if (!empty($user_id)) {
            return (int) $user_id;
        }
        
        // If not found in term meta, try to get from author object
        $author = $this->get_author_by_term_id($author_id);
        
        if ($author && isset($author->user_id) && $author->user_id > 0) {
            return (int) $author->user_id;
        }
        
        return false;
    }
    
    /**
     * Update the user ID associated with an author
     * 
     * @param int $author_id The author term ID
     * @param int $user_id The user ID to associate with the author
     * @return bool True on success, false on failure
     */
    private function update_author_user_id($author_id, $user_id) {
        // Update term meta
        $result = update_term_meta($author_id, 'user_id', $user_id);
        
        if (is_wp_error($result)) {
            return false;
        }
        
        // Also update in the author object if possible
        $author = $this->get_author_by_term_id($author_id);
        
        if ($author && isset($author->user_id)) {
            $author->user_id = $user_id;
            // If there's a method to save the author object, call it here
            // This depends on how PublishPress Authors implements this
            if (method_exists($author, 'save')) {
                $author->save();
            }
        }
        
        return true;
    }

    /**
     * Add bulk actions to the list of authors.
     *
     * @param $bulk_actions
     *
     * @return array
     */
    public function addBulkActions($bulk_actions)
    {
        $bulk_actions['auto_map_to_user'] = __(
            'Auto map guest authors to users by email',
            'author-profile-updater'
        );

        return $bulk_actions;
    }

    public function handleBulkActions($redirect_to, $doaction, $post_ids)
    {
        if ($doaction !== 'auto_map_to_user') {
            return $redirect_to;
        }

        $count = 0;

        foreach ($post_ids as $post_id) {
            $term = get_term($post_id, 'author');

            if (is_wp_error($term) || empty($term)) {
                continue;
            }

            $author = $this->get_author_by_term_id($term->term_id);

            if ($this->author_is_mapped_to_user($author)) {
                continue;
            }

            // Get author email from term meta
            $email = get_term_meta($term->term_id, 'user_email', true);
            
            if (empty($email)) {
                continue; // Skip if no email is set
            }

            $user = $this->get_user_matching_the_email($email);

            if (is_object($user)) {
                $this->map_author_to_user($term->term_id, $user->ID);
                $count++;
            }
        }

        $redirect_to = add_query_arg(
            array(
                'bulk_action_result' => $count,
                'bulk_action_message' => sprintf(__('Updated %d authors.', 'author-profile-updater'), $count)
            ),
            $redirect_to
        );

        return $redirect_to;
    }

    /**
     * Display admin notices for bulk actions
     */
    public function displayBulkActionNotices() {
        if (!isset($_REQUEST['bulk_action_result']) || !isset($_REQUEST['bulk_action_message'])) {
            return;
        }
        
        $count = intval($_REQUEST['bulk_action_result']);
        $message = sanitize_text_field($_REQUEST['bulk_action_message']);
        
        if ($count > 0) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
        } else {
            echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__('No authors were updated. Either they were already mapped or no matching users were found.', 'author-profile-updater') . '</p></div>';
        }
    }

    /**
     * Get author data structure for debugging
     *
     * @param object $author
     * @param int $term_id
     * @return string
     */
    private function get_author_data_structure($author, $term_id) {
        $data_structure = '';

        // Get term meta
        $term_meta = get_term_meta($term_id);

        // Get author object properties
        $author_properties = get_object_vars($author);

        // Create debug string
        $data_structure .= 'Term Meta: ' . print_r($term_meta, true) . "\n";
        $data_structure .= 'Author Object Properties: ' . print_r($author_properties, true) . "\n";

        return $data_structure;
    }
}

// Initialize the plugin
Author_Profile_Updater::get_instance();
