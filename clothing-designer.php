<?php
/**
 * Plugin Name: Clothing Designer
 * Plugin URI: https://example.com/clothing-designer
 * Description: A clothing designer that supports Adobe Illustrator templates and allows users to upload SVG files and edit text elements.
 * Version: 1.0.0
 * Author: Lawrence Clemente
 * Author URI: https://example.com
 * Text Domain: clothing-designer
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class Clothing_Designer {
    
    /**
     * Plugin instance.
     *
     * @var Clothing_Designer
     */
    private static $instance = null;
    
    /**
     * Get plugin instance.
     *
     * @return Clothing_Designer
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor.
     */
    private function __construct() {
        $this->define_constants();
        $this->includes();
        $this->init_hooks();
    }
    
    /**
     * Define plugin constants.
     */
    private function define_constants() {
        define('CD_VERSION', '1.0.0');
        define('CD_PLUGIN_DIR', plugin_dir_path(__FILE__));
        define('CD_PLUGIN_URL', plugin_dir_url(__FILE__));
        define('CD_PLUGIN_BASENAME', plugin_basename(__FILE__));
        define('CD_UPLOADS_DIR', wp_upload_dir()['basedir'] . '/clothing-designs/');
        define('CD_UPLOADS_URL', wp_upload_dir()['baseurl'] . '/clothing-designs/');
        define('CD_AJAX_NONCE', 'cd-ajax-nonce');
        define('CD_ADMIN_NONCE', 'cd-admin-nonce');
    }
    
    /**
     * Include required files.
     */
    private function includes() {
        // Admin
        require_once CD_PLUGIN_DIR . 'includes/class-cd-admin.php';
        
        // Core classes
        require_once CD_PLUGIN_DIR . 'includes/class-cd-assets.php';
        require_once CD_PLUGIN_DIR . 'includes/class-cd-template.php';
        require_once CD_PLUGIN_DIR . 'includes/class-cd-shortcode.php';
        require_once CD_PLUGIN_DIR . 'includes/class-cd-ajax.php';
        require_once CD_PLUGIN_DIR . 'includes/class-cd-file-handler.php';
        // Optional environment loader for API keys
        $env_loader_path = CD_PLUGIN_DIR . 'includes/class-cd-env-loader.php';
        if (file_exists($env_loader_path)) {
            require_once $env_loader_path;
        }
    }
    
    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate_plugin'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate_plugin'));
        
        add_action('plugins_loaded', array($this, 'plugins_loaded'));
    }
    
    /**
     * Plugins loaded.
     */
    public function plugins_loaded() {
        try {
            load_plugin_textdomain('clothing-designer', false, dirname(CD_PLUGIN_BASENAME) . '/languages');
            // Initialize environment loader if available
            if (class_exists('CD_Env_Loader')) {
                CD_Env_Loader::get_instance();
            }
            // Check required directories
            $this->check_required_directories();
            // Initialize classes
            CD_Admin::get_instance();
            CD_Assets::get_instance();
            CD_Template::get_instance();
            CD_Shortcode::get_instance();
            CD_Ajax::get_instance();
        } catch (Exception $e) {
            // Log error
            error_log('Clothing Designer Plugin Error: ' . $e->getMessage());
            
            // Display admin notice
            add_action('admin_notices', function() use ($e) {
                echo '<div class="error"><p><strong>Clothing Designer Error:</strong> ' . 
                    esc_html($e->getMessage()) . '</p></div>';
            });
        }
    }
    /**
     * Check required directories exist and are writable
     */
    private function check_required_directories() {
        // Check uploads directory
        if (!file_exists(CD_UPLOADS_DIR)) {
            if (!wp_mkdir_p(CD_UPLOADS_DIR)) {
                throw new Exception(
                    sprintf(
                        __('Unable to create uploads directory. Please ensure %s is writable.', 'clothing-designer'),
                        dirname(CD_UPLOADS_DIR)
                    )
                );
            }
        }
        
        if (!is_writable(CD_UPLOADS_DIR)) {
            throw new Exception(
                sprintf(
                    __('Uploads directory is not writable. Please check permissions for %s.', 'clothing-designer'),
                    CD_UPLOADS_DIR
                )
            );
        }
        
        // Check subdirectories
        $required_subdirs = array('elements', 'designs');
        foreach ($required_subdirs as $subdir) {
            $dir_path = CD_UPLOADS_DIR . $subdir;
            if (!file_exists($dir_path)) {
                if (!wp_mkdir_p($dir_path)) {
                    throw new Exception(
                        sprintf(
                            __('Unable to create %s directory. Please check permissions.', 'clothing-designer'),
                            $subdir
                        )
                    );
                }
            }
            
            if (!is_writable($dir_path)) {
                throw new Exception(
                    sprintf(
                        __('%s directory is not writable. Please check permissions.', 'clothing-designer'),
                        $subdir
                    )
                );
            }
        }
    }
    
    /**
     * Activate plugin.
     */
    public function activate_plugin() {
        // Create necessary directories using the check_required_directories method
        try {
            $this->check_required_directories();
            
            // Create index.php to prevent directory listing
            if (!file_exists(CD_UPLOADS_DIR . 'index.php')) {
                file_put_contents(CD_UPLOADS_DIR . 'index.php', '<?php // Silence is golden');
            }
            
            // Create .htaccess to restrict access
            if (!file_exists(CD_UPLOADS_DIR . '.htaccess')) {
                file_put_contents(CD_UPLOADS_DIR . '.htaccess', 
                    "# Disable directory listing\n" .
                    "Options -Indexes\n\n" .
                    "# Allow access to image files\n" .
                    "<FilesMatch '\.(svg|png|jpe?g|gif|ai)$'>\n" .
                    "    Require all granted\n" .
                    "</FilesMatch>\n\n" .
                    "# Deny access to PHP files\n" .
                    "<FilesMatch '\.php$'>\n" .
                    "    Require all denied\n" .
                    "</FilesMatch>"
                );
            }
        } catch (Exception $e) {
            error_log('Clothing Designer activation error: ' . $e->getMessage());
            // No need to rethrow - we'll just log the error during activation
        }
        
        
        // Create database tables
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $designs_table = $wpdb->prefix . 'cd_designs';
        $templates_table = $wpdb->prefix . 'cd_templates';
        $templates_views_table = $wpdb->prefix . 'cd_template_views';
             
        $templates_sql = "CREATE TABLE IF NOT EXISTS `{$templates_table}` (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text NOT NULL,
            file_url varchar(255) NOT NULL,
            file_type varchar(20) NOT NULL,
            thumbnail_url varchar(255) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'publish',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        $designs_sql = "CREATE TABLE IF NOT EXISTS `{$designs_table}` (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            template_id bigint(20) NOT NULL,
            design_data longtext NOT NULL,
            preview_url varchar(255) NOT NULL,
            is_compressed tinyint(1) NOT NULL DEFAULT 0,
            is_chunked tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY template_id (template_id)
        ) $charset_collate;";
        
        $templates_views_sql = "CREATE TABLE IF NOT EXISTS `{$templates_views_table}` (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            template_id bigint(20) NOT NULL,
            view_type varchar(50) NOT NULL,
            file_url varchar(255) NOT NULL,
            file_type varchar(20) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY template_id (template_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($designs_sql);
        dbDelta($templates_sql);
        dbDelta($templates_views_sql);

        // Add foreign key constraints as separate ALTER TABLE commands
        // Only add if the tables exist and MySQL version supports them
        if ($this->check_mysql_version_for_foreign_keys()) {
            // First check if the constraint already exists
            $constraint_exists = $wpdb->get_var(
                "SELECT COUNT(*)
                 FROM information_schema.TABLE_CONSTRAINTS 
                 WHERE CONSTRAINT_SCHEMA = DATABASE() 
                 AND CONSTRAINT_NAME = 'fk_template'
                 AND TABLE_NAME = '{$designs_table}'"
            );
            
            if (!$constraint_exists) {
                $wpdb->query("ALTER TABLE `{$designs_table}` 
                    ADD CONSTRAINT fk_template_design
                    FOREIGN KEY (template_id) 
                    REFERENCES `{$templates_table}`(id) 
                    ON DELETE CASCADE");
            }
            
            $constraint_exists = $wpdb->get_var(
                "SELECT COUNT(*)
                 FROM information_schema.TABLE_CONSTRAINTS 
                 WHERE CONSTRAINT_SCHEMA = DATABASE() 
                 AND CONSTRAINT_NAME = 'fk_template_view'
                 AND TABLE_NAME = '{$templates_views_table}'"
            );
            
            if (!$constraint_exists) {
                $wpdb->query("ALTER TABLE `{$templates_views_table}` 
                    ADD CONSTRAINT fk_template_view
                    FOREIGN KEY (template_id) 
                    REFERENCES `{$templates_table}`(id) 
                    ON DELETE CASCADE");
            }
        }
        // Add automatic timestamp updates for MySQL 5.6+
        if ($this->check_mysql_version_for_timestamp_updates()) {
            $wpdb->query("ALTER TABLE `{$templates_table}`
                MODIFY updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
                
            $wpdb->query("ALTER TABLE `{$designs_table}` 
                MODIFY updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
        }
        
        // Add default options
        add_option('cd_options', array(
            'editor_width' => '100%',
            'editor_height' => '600px',
            'allow_guest_designs' => 'yes',
            'allowed_file_types' => array('svg', 'png', 'jpg', 'jpeg', 'ai')
        ));
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Deactivate plugin.
     */
    public function deactivate_plugin() {
        flush_rewrite_rules();
    }

    /**
     * Check if MySQL version supports foreign keys properly
     * 
     * @return boolean
     */
    private function check_mysql_version_for_foreign_keys() {
        global $wpdb;
        $mysql_version = $wpdb->db_version();
        return version_compare($mysql_version, '5.6.0', '>=');
    }

    /**
     * Check if MySQL version supports ON UPDATE CURRENT_TIMESTAMP
     * 
     * @return boolean
     */
    private function check_mysql_version_for_timestamp_updates() {
        global $wpdb;
        $mysql_version = $wpdb->db_version();
        return version_compare($mysql_version, '5.6.5', '>=');
    }
}

// Initialize the plugin
function cd_plugin() {
    return Clothing_Designer::get_instance();
}

// Start the plugin
cd_plugin();