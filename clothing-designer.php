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
        load_plugin_textdomain('clothing-designer', false, dirname(CD_PLUGIN_BASENAME) . '/languages');
        
        // Initialize classes
        CD_Admin::get_instance();
        CD_Assets::get_instance();
        CD_Template::get_instance();
        CD_Shortcode::get_instance();
        CD_Ajax::get_instance();
    }
    
    
    /**
     * Activate plugin.
     */
    public function activate_plugin() {
        // Create necessary directories
        if (!file_exists(CD_UPLOADS_DIR)) {
            wp_mkdir_p(CD_UPLOADS_DIR);
            
            // Create index.php to prevent directory listing
            file_put_contents(CD_UPLOADS_DIR . 'index.php', '<?php // Silence is golden');
            
            // Create .htaccess to restrict access
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
        
        // Create database tables
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $designs_table = $wpdb->prefix . 'cd_designs';
        
        $designs_sql = "CREATE TABLE $designs_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            template_id bigint(20) NOT NULL,
            design_data longtext NOT NULL,
            preview_url varchar(255) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY template_id (template_id)
            CONSTRAINT fk_user FOREIGN KEY (user_id) REFERENCES {$wpdb->users} (ID) ON DELETE CASCADE,
            CONSTRAINT fk_template FOREIGN KEY (template_id) REFERENCES {$wpdb->prefix}cd_templates (id) ON DELETE CASCADE
        ) $charset_collate;";
        
        $templates_table = $wpdb->prefix . 'cd_templates';
        
        $templates_sql = "CREATE TABLE IF NOT EXISTS $templates_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text NOT NULL,
         /*    file_url varchar(255) NOT NULL,
            file_type varchar(20) NOT NULL, */
            thumbnail_url varchar(255) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'publish',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        // Update templates table to support multiple views
        $templates_views_sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cd_template_views (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            template_id bigint(20) NOT NULL,
            view_type varchar(50) NOT NULL,
            file_url varchar(255) NOT NULL,
            file_type varchar(20) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY template_id (template_id)
            CONSTRAINT fk_template_view FOREIGN KEY (template_id) REFERENCES {$wpdb->prefix}cd_templates (id) ON DELETE CASCADE
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($designs_sql);
        dbDelta($templates_sql);
        dbDelta($templates_views_sql);
        
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
}

// Initialize the plugin
function cd_plugin() {
    return Clothing_Designer::get_instance();
}

// Start the plugin
cd_plugin();