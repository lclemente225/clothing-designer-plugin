<?php
/**
 * Assets class
 *
 * @package Clothing_Designer
 */

if (!defined('ABSPATH')) {
    exit;
}

class CD_Assets {
    
    /**
     * Instance.
     *
     * @var CD_Assets
     */
    private static $instance = null;
    
    /**
     * Get instance.
     *
     * @return CD_Assets
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
        // Check required constants
        if (!defined('CD_PLUGIN_URL') || !defined('CD_VERSION') || !defined('CD_AJAX_NONCE')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>' . __('Clothing Designer: Required constants are not defined. Please check the plugin installation.', 'clothing-designer') . '</p></div>';
            });
            return;
        }
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Register scripts for admin
        add_action('admin_enqueue_scripts', array($this, 'register_scripts'));
    }
    
    /**
     * Enqueue scripts and styles.
     */
    public function enqueue_scripts() {
        $this->register_scripts();
    
        // Check if our assets should be enqueued
        $should_enqueue = false;
        
        // Check post content for shortcode
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'clothing_designer')) {
            $should_enqueue = true;
        }
        
        // Check for the shortcode in widgets
        if (!$should_enqueue && is_active_widget(false, false, 'text', true)) {
            $widgets = get_option('widget_text');
            foreach ($widgets as $widget) {
                if (isset($widget['text']) && has_shortcode($widget['text'], 'clothing_designer')) {
                    $should_enqueue = true;
                    break;
                }
            }
        }
        
        // Check for custom cases specific to your theme
        if (!$should_enqueue) {
            $should_enqueue = apply_filters('cd_should_enqueue_assets', $should_enqueue);
        }
        
        // Enqueue if needed
        if ($should_enqueue) {
            wp_enqueue_style('cd-style');
            wp_enqueue_script('fabric-js');
            if (wp_script_is('svg-js', 'registered')) {
                wp_enqueue_script('svg-js');
            }
            wp_enqueue_script('cd-script');
        }
    }
    
  /**
     * Register scripts for admin without enqueuing.
     * This makes them available for other admin scripts to depend on.
     */
    public function register_scripts() {
        // Register styles
        wp_register_style('cd-style', CD_PLUGIN_URL . 'assets/css/clothing-designer.css', array(), CD_VERSION);
        
        // Register scripts
        wp_register_script('fabric-js', CD_PLUGIN_URL . 'assets/js/fabric.min.js', array(), '5.3.1', true);
        wp_register_script('svg-js', CD_PLUGIN_URL . 'assets/js/svg.min.js', array(), '3.2.0', true);
        wp_register_script('cd-script', CD_PLUGIN_URL . 'assets/js/clothing-designer.js', array('jquery', 'fabric-js', 'svg-js'), CD_VERSION, true);
        
        // Localize script
        wp_localize_script('cd-script', 'cd_vars', $this->get_localization_data());
    }

    /**
     * Conditionally enqueues scripts and styles when needed.
     * Use this in shortcode callbacks or template functions.
     */
    public function enqueue_designer_assets() {
        wp_enqueue_style('cd-style');
        wp_enqueue_script('fabric-js');
        wp_enqueue_script('svg-js');
        wp_enqueue_script('cd-script');
    }
    
    /**
     * Get localization data for scripts.
     *
     * @return array
     */
    private function get_localization_data() {
        return array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(CD_AJAX_NONCE),
            'upload_max_size' => wp_max_upload_size(),
            'messages' => array(
                'save_success' => __('Design saved successfully', 'clothing-designer'),
                'save_error' => __('Failed to save design', 'clothing-designer'),
                'upload_error' => __('Failed to upload file', 'clothing-designer'),
                'invalid_file_type' => __('Invalid file type. Allowed file types: ', 'clothing-designer'),
                'file_too_large' => __('File is too large. Maximum file size: ', 'clothing-designer'),
                'confirm_reset' => __('Are you sure you want to reset your design? All changes will be lost.', 'clothing-designer'),
                'edit_text' => __('Edit Text', 'clothing-designer'),
                'save' => __('Save', 'clothing-designer'),
                'cancel' => __('Cancel', 'clothing-designer'),
                'delete' => __('Delete', 'clothing-designer'),
                'confirm_delete' => __('Are you sure you want to delete this element?', 'clothing-designer'),
                'loading' => __('Loading...', 'clothing-designer'),
                'processing' => __('Processing...', 'clothing-designer'),
                'upload_design' => __('Upload Design', 'clothing-designer'),
                'add_text' => __('Add Text', 'clothing-designer'),
                'adjust_properties' => __('Adjust Properties', 'clothing-designer'),
                'color' => __('Color', 'clothing-designer'),
                'size' => __('Size', 'clothing-designer'),
                'rotation' => __('Rotation', 'clothing-designer'),
                'layers' => __('Layers', 'clothing-designer'),
                'move_up' => __('Move Up', 'clothing-designer'),
                'move_down' => __('Move Down', 'clothing-designer'),
                'no_template' => __('No template found.', 'clothing-designer'),
                'error_loading_template' => __('Error loading template.', 'clothing-designer'),
                'edit_svg_text' => __('Edit Text in SVG', 'clothing-designer'),
                'update' => __('Update', 'clothing-designer'),
                'svg_text_updated' => __('SVG text updated successfully', 'clothing-designer'),
            ),
            'allowed_file_types' => $this->get_allowed_file_types()
        );
    }

    /**
     * Get allowed file types from settings.
     *
     * @return array
     */
    private function get_allowed_file_types() {
        $options = get_option('cd_options', array());
        $allowed_file_types = isset($options['allowed_file_types']) ? $options['allowed_file_types'] : array('svg', 'png', 'jpg', 'jpeg', 'ai');
        
        // Map to MIME types
        $mime_types = array();
        
        foreach ($allowed_file_types as $type) {
            switch ($type) {
                case 'svg':
                    $mime_types[] = 'image/svg+xml';
                    break;
                case 'png':
                    $mime_types[] = 'image/png';
                    break;
                case 'jpg':
                case 'jpeg':
                    $mime_types[] = 'image/jpeg';
                    break;
                case 'ai':
                    $mime_types[] = 'application/postscript';
                    $mime_types[] = 'application/illustrator';
                    break;
            }
        }
        
        return array(
            'extensions' => $allowed_file_types,
            'mime_types' => $mime_types
        );
    }
}