<?php
/**
 * Admin class
 *
 * @package Clothing_Designer
 */

if (!defined('ABSPATH')) {
    exit;
}

class CD_Admin {
    
    /**
     * Instance.
     *
     * @var CD_Admin
     */
    private static $instance = null;
    
    /**
     * Get instance.
     *
     * @return CD_Admin
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
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add media button for inserting the designer
        add_action('media_buttons', array($this, 'add_designer_button'));
        
        // Ajax handlers
        add_action('wp_ajax_cd_save_template', array($this, 'ajax_save_template'));
        add_action('wp_ajax_cd_delete_template', array($this, 'ajax_delete_template'));
        add_action('wp_ajax_cd_delete_design', array($this, 'ajax_delete_design'));
        add_action('wp_ajax_cd_get_templates', array($this, 'ajax_get_templates'));
        add_action('wp_ajax_cd_get_template_views', array($this, 'ajax_get_template_views'));
        add_action('wp_ajax_nopriv_cd_get_template_views', array($this, 'ajax_get_template_views'));
        add_action('wp_ajax_cd_bulk_delete_templates', array($this, 'ajax_bulk_delete_templates'));
        add_action('wp_ajax_cd_bulk_delete_designs', array($this, 'ajax_bulk_delete_designs'));
    }
    
    /**
     * Add admin menu.
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Clothing Designer', 'clothing-designer'),
            __('Clothing Designer', 'clothing-designer'),
            'manage_options',
            'clothing-designer',
            array($this, 'render_admin_page'),
            'dashicons-art',
            25
        );
        
        add_submenu_page(
            'clothing-designer',
            __('Templates', 'clothing-designer'),
            __('Templates', 'clothing-designer'),
            'manage_options',
            'clothing-designer',
            array($this, 'render_admin_page')
        );
        
        add_submenu_page(
            'clothing-designer',
            __('Add New Template', 'clothing-designer'),
            __('Add New Template', 'clothing-designer'),
            'manage_options',
            'clothing-designer-add-template',
            array($this, 'render_add_template_page')
        );
        
        add_submenu_page(
            'clothing-designer',
            __('Settings', 'clothing-designer'),
            __('Settings', 'clothing-designer'),
            'manage_options',
            'clothing-designer-settings',
            array($this, 'render_settings_page')
        );
        
        add_submenu_page(
            'clothing-designer',
            __('User Designs', 'clothing-designer'),
            __('User Designs', 'clothing-designer'),
            'manage_options',
            'clothing-designer-designs',
            array($this, 'render_designs_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles.
     */
    public function enqueue_admin_scripts($hook) {
        // Only enqueue on plugin pages
        if (strpos($hook, 'clothing-designer') === false) {
            return;
        }

        wp_enqueue_style('cd-admin-style', CD_PLUGIN_URL . 'assets/css/admin.css', array(), CD_VERSION);
        
        // Add jQuery UI dependencies
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_style('wp-jquery-ui-dialog');
        
        wp_enqueue_script('cd-admin-script', CD_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'jquery-ui-dialog'), CD_VERSION, true);
        
        // Add media uploader
        wp_enqueue_media();
        
        // Add ajax nonce
        wp_localize_script('cd-admin-script', 'cd_admin_vars', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(CD_ADMIN_NONCE),
            'template_added' => __('Template added successfully', 'clothing-designer'),
            'template_updated' => __('Template updated successfully', 'clothing-designer'),
            'template_deleted' => __('Template deleted successfully', 'clothing-designer'),
            'design_deleted' => __('Design deleted successfully', 'clothing-designer'),
            'confirm_delete_template' => __('Are you sure you want to delete this template?', 'clothing-designer'),
            'confirm_delete_design' => __('Are you sure you want to delete this design?', 'clothing-designer'),
            'upload_template_title' => __('Select or Upload Template File', 'clothing-designer'),
            'upload_template_button' => __('Use this file', 'clothing-designer'),
            'upload_thumbnail_title' => __('Select or Upload Thumbnail', 'clothing-designer'),
            'upload_thumbnail_button' => __('Use this image', 'clothing-designer')
        ));
    }
    
    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting('cd_options_group', 'cd_options', array($this, 'sanitize_options'));
        
        add_settings_section(
            'cd_general_section',
            __('General Settings', 'clothing-designer'),
            array($this, 'render_general_section'),
            'cd_options_page'
        );
        
        add_settings_field(
            'editor_width',
            __('Editor Width', 'clothing-designer'),
            array($this, 'render_editor_width_field'),
            'cd_options_page',
            'cd_general_section'
        );
        
        add_settings_field(
            'editor_height',
            __('Editor Height', 'clothing-designer'),
            array($this, 'render_editor_height_field'),
            'cd_options_page',
            'cd_general_section'
        );
        
        add_settings_field(
            'allow_guest_designs',
            __('Allow Guest Designs', 'clothing-designer'),
            array($this, 'render_allow_guest_designs_field'),
            'cd_options_page',
            'cd_general_section'
        );
        
        add_settings_field(
            'allowed_file_types',
            __('Allowed File Types', 'clothing-designer'),
            array($this, 'render_allowed_file_types_field'),
            'cd_options_page',
            'cd_general_section'
        ); 
        
        // Add Cloudmersive API settings section
        add_settings_section(
            'cd_api_section',
            __('API Integrations', 'clothing-designer'),
            array($this, 'render_api_section'),
            'cd_options_page'
        );
        
        add_settings_field(
            'cloudmersive_api_key',
            __('Cloudmersive API Key', 'clothing-designer'),
            array($this, 'render_cloudmersive_api_key_field'),
            'cd_options_page',
            'cd_api_section'
        );
        
        add_settings_field(
            'use_cloudmersive',
            __('Use Cloudmersive for AI Conversion', 'clothing-designer'),
            array($this, 'render_use_cloudmersive_field'),
            'cd_options_page',
            'cd_api_section'
        );

        add_settings_field(
            'override_env_api_key',
            __('Override Environment API Key', 'clothing-designer'),
            array($this, 'render_override_env_api_key_field'),
            'cd_options_page',
            'cd_api_section'
        );
    }
    
    /**
     * Sanitize options.
     */
    public function sanitize_options($input) {
        $output = array();
        
        // Save override_env_api_key setting
        $output['override_env_api_key'] = isset($input['override_env_api_key']) ? 'yes' : 'no';

        // Validate editor width
        if (isset($input['editor_width'])) {
            $width = sanitize_text_field($input['editor_width']);
            // Ensure valid CSS dimension format
            if (preg_match('/^\d+(%|px|em|rem|vh|vw)?$/', $width)) {
                $output['editor_width'] = $width;
            } else {
                $output['editor_width'] = '100%';
                add_settings_error('cd_options', 'invalid_width', __('Invalid width format. Using default 100%.', 'clothing-designer'));
            }
        } else {
            $output['editor_width'] = '100%';
        }
        
        // Validate editor height
        if (isset($input['editor_height'])) {
            $height = sanitize_text_field($input['editor_height']);
            // Ensure valid CSS dimension format
            if (preg_match('/^\d+(%|px|em|rem|vh|vw)?$/', $height)) {
                $output['editor_height'] = $height;
            } else {
                $output['editor_height'] = '600px';
                add_settings_error('cd_options', 'invalid_height', __('Invalid height format. Using default 600px.', 'clothing-designer'));
            }
        } else {
            $output['editor_height'] = '600px';
        }
        
        // Guest designs setting
        $output['allow_guest_designs'] = isset($input['allow_guest_designs']) ? 'yes' : 'no';
        
        // Allowed file types
        $valid_types = array('svg', 'png', 'jpg', 'jpeg', 'ai');
        $allowed_file_types = isset($input['allowed_file_types']) && is_array($input['allowed_file_types']) 
            ? $input['allowed_file_types'] 
            : array();
        
        $output['allowed_file_types'] = array_filter(
            array_map('sanitize_text_field', $allowed_file_types),
            function($type) use ($valid_types) {
                return in_array($type, $valid_types);
            }
        );
        
        // If no file types are selected, use defaults
        if (empty($output['allowed_file_types'])) {
            $output['allowed_file_types'] = array('svg', 'png', 'jpg', 'jpeg');
            add_settings_error('cd_options', 'invalid_file_types', __('No valid file types selected. Using defaults.', 'clothing-designer'));
        }

        // Sanitize Cloudmersive API key
        if (isset($input['cloudmersive_api_key'])) {
            $output['cloudmersive_api_key'] = sanitize_text_field($input['cloudmersive_api_key']);
        } else {
            $output['cloudmersive_api_key'] = '';
        }
    
        // Use Cloudmersive setting
        $output['use_cloudmersive'] = isset($input['use_cloudmersive']) ? 'yes' : 'no';
        return $output;
    }
    
    /**
     * Render general section.
     */
    public function render_general_section() {
        echo '<p>' . __('Configure general settings for the clothing designer.', 'clothing-designer') . '</p>';
    }
    
    /**
     * Render editor width field.
     */
    public function render_editor_width_field() {
        $options = get_option('cd_options');
        $width = isset($options['editor_width']) ? $options['editor_width'] : '100%';
        
        echo '<input type="text" name="cd_options[editor_width]" value="' . esc_attr($width) . '" class="regular-text" />';
        echo '<p class="description">' . __('Width of the clothing designer (e.g. 100%, 800px).', 'clothing-designer') . '</p>';
    }
    
    /**
     * Render editor height field.
     */
    public function render_editor_height_field() {
        $options = get_option('cd_options');
        $height = isset($options['editor_height']) ? $options['editor_height'] : '600px';
        
        echo '<input type="text" name="cd_options[editor_height]" value="' . esc_attr($height) . '" class="regular-text" />';
        echo '<p class="description">' . __('Height of the clothing designer (e.g. 600px).', 'clothing-designer') . '</p>';
    }
    
    /**
     * Render allow guest designs field.
     */
    public function render_allow_guest_designs_field() {
        $options = get_option('cd_options');
        $allow_guest_designs = isset($options['allow_guest_designs']) ? $options['allow_guest_designs'] : 'yes';
        
        echo '<label>';
        echo '<input type="checkbox" name="cd_options[allow_guest_designs]" value="yes" ' . checked($allow_guest_designs, 'yes', false) . ' />';
        echo __('Allow non-logged in users to create designs', 'clothing-designer');
        echo '</label>';
    }
    
    /**
     * Render allowed file types field.
     */
    public function render_allowed_file_types_field() {
        $options = get_option('cd_options');
        $allowed_file_types = isset($options['allowed_file_types']) ? $options['allowed_file_types'] : array('svg', 'png', 'jpg', 'jpeg', 'ai');
        
        $available_types = array(
            'svg' => __('SVG', 'clothing-designer'),
            'png' => __('PNG', 'clothing-designer'),
            'jpg' => __('JPG', 'clothing-designer'),
            'jpeg' => __('JPEG', 'clothing-designer'),
            'ai' => __('Adobe Illustrator', 'clothing-designer'),
        );
        
        foreach ($available_types as $type => $label) {
            echo '<label style="display: block; margin-bottom: 5px;">';
            echo '<input type="checkbox" name="cd_options[allowed_file_types][]" value="' . esc_attr($type) . '" ';
            checked(in_array($type, $allowed_file_types), true);
            echo ' /> ' . esc_html($label);
            echo '</label>';
        }
    }
    
    /**
     * Add designer button to editor.
     */
    public function add_designer_button() {
        echo '<button type="button" class="button cd-insert-designer" data-editor="content">';
        echo '<span class="dashicons dashicons-art" style="vertical-align: text-top;"></span> ';
        echo __('Add Clothing Designer', 'clothing-designer');
        echo '</button>';
    }
    
    /**
     * Render admin page.
     */
    public function render_admin_page() {
        // Get templates
        global $wpdb;
        $templates_table = $wpdb->prefix . 'cd_templates';
        $templates = $wpdb->get_results("SELECT * FROM $templates_table ORDER BY created_at DESC");
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php echo __('Clothing Designer Templates', 'clothing-designer'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=clothing-designer-add-template'); ?>" class="page-title-action"><?php echo __('Add New', 'clothing-designer'); ?></a>
            
            <div class="cd-admin-content">
                <?php if (empty($templates)) : ?>
                    <div class="cd-no-templates">
                        <p><?php echo __('No templates found. Click "Add New" to create your first template.', 'clothing-designer'); ?></p>
                    </div>
                <?php else : ?>   
                    <!-- Add bulk actions here -->
                    <div class="tablenav top">
                        <div class="alignleft actions bulkactions">
                            <label for="bulk-action-selector-top" class="screen-reader-text"><?php _e('Select bulk action', 'clothing-designer'); ?></label>
                            <select name="action" id="bulk-action-selector-top">
                                <option value="-1"><?php _e('Bulk Actions', 'clothing-designer'); ?></option>
                                <option value="delete"><?php _e('Delete', 'clothing-designer'); ?></option>
                            </select>
                            <input type="button" id="doaction" class="button action cd-bulk-action-templates" value="<?php esc_attr_e('Apply', 'clothing-designer'); ?>">
                        </div>
                        <br class="clear">
                    </div>
                    <div class="cd-templates-grid">
                        <!-- Add "Select All" checkbox in a header row -->
                        <div class="cd-template-header">
                            <div class="cd-template-checkbox-header">
                                <input type="checkbox" id="cd-select-all-templates">
                            </div>
                            <div class="cd-template-name-header"><?php _e('Template', 'clothing-designer'); ?></div>
                        </div>
                        <?php foreach ($templates as $template) : ?>
                            <div class="cd-template-card" data-id="<?php echo esc_attr($template->id); ?>">
                                <!-- Add checkbox for each template -->
                                <div class="cd-template-checkbox-container">
                                    <input type="checkbox" class="cd-template-checkbox" value="<?php echo esc_attr($template->id); ?>">
                                </div>
                                <div class="cd-template-thumbnail">
                                    <?php if (!empty($template->thumbnail_url)) : ?>
                                        <img src="<?php echo esc_url($template->thumbnail_url); ?>" alt="<?php echo esc_attr($template->title); ?>">
                                    <?php else : ?>
                                        <div class="cd-no-thumbnail">
                                            <span class="dashicons dashicons-format-image"></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="cd-template-details">
                                    <h3><?php echo esc_html($template->title); ?></h3>
                                    <p><?php echo esc_html($template->description); ?></p>
                                    <div class="cd-template-actions">
                                        <a href="<?php echo admin_url('admin.php?page=clothing-designer-add-template&id=' . $template->id); ?>" class="button button-secondary"><?php echo __('Edit', 'clothing-designer'); ?></a>
                                        <button class="button button-link-delete cd-delete-template" data-id="<?php echo esc_attr($template->id); ?>"><?php echo __('Delete', 'clothing-designer'); ?></button>
                                        <button class="button button-secondary cd-shortcode-template" data-id="<?php echo esc_attr($template->id); ?>"><?php echo __('Get Shortcode', 'clothing-designer'); ?></button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render add template page.
     */
    public function render_add_template_page() {
        // Check if editing existing template
        $template_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $template = null;
        $template_views = array();
        
        if ($template_id > 0) {
            global $wpdb;
            $templates_table = $wpdb->prefix . 'cd_templates';
            $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM $templates_table WHERE id = %d", $template_id));
            if($template){
                $template_views_table = $wpdb->prefix . 'cd_template_views';
                $template_views_results = $wpdb->get_results($wpdb->prepare("SELECT * FROM $template_views_table WHERE template_id = %d", $template_id));
                foreach ($template_views_results as $view) {
                    $template_views[$view->view_type] = $view;
                }
            }
        }
        
        $title = $template ? __('Edit Template', 'clothing-designer') : __('Add New Template', 'clothing-designer');
        ?>

        <div class="wrap">
            <h1><?php echo esc_html($title); ?></h1>
            
            <form class="cd-template-form"  method="post" action="<?php echo esc_url(admin_url('admin-ajax.php'));?>">
                <div class="cd-form-field">
                    <label for="template-title"><?php echo __('Title', 'clothing-designer'); ?></label>
                    <input type="text" id="template-title" name="title" value="<?php echo $template ? esc_attr($template->title) : ''; ?>" required>
                </div>
                
                <div class="cd-form-field">
                    <label for="template-description"><?php echo __('Description', 'clothing-designer'); ?></label>
                    <textarea id="template-description" name="description" rows="4"><?php echo $template ? esc_textarea($template->description) : ''; ?></textarea>
                </div>
                
                <div class="cd-form-field">
                    <label><?php echo __('Template Views', 'clothing-designer'); ?></label>
                    <div class="cd-template-views">
                        
                        <div class="cd-view-content">
                            <?php
                                foreach (['front', 'back', 'left', 'right'] as $view_type) : 
                                    $view_id = isset($template_views[$view_type]) ? $template_views[$view_type]->id : 0;
                                    $view_file_url = isset($template_views[$view_type]) ? $template_views[$view_type]->file_url : '';
                                    $view_file_type = isset($template_views[$view_type]) ? $template_views[$view_type]->file_type : '';
                                    $is_active = $view_type === 'front' ? 
                                    'active' : '';
                            ?>
                            <div class="cd-view-panel <?php echo $is_active; ?>" data-view="<?php echo $view_type; ?>">
                                <input type="hidden" 
                                    name="template_views[<?php echo $view_type; ?>][id]" 
                                    value="<?php echo $view_id; ?>"
                                >
                                <div class="cd-file-upload-container">
                                    <input type="text" 
                                        name="template_views[<?php echo $view_type; ?>][file_url]" 
                                        value="<?php echo esc_attr($view_file_url); ?>" readonly 
                                        <?php echo $view_type === 'front' ? 'required' : ''; ?>>
                                    
                                    <div class="cd-upload-buttons">
                                        <!-- Direct upload button -->
                                        <button type="button" 
                                            class="button button-primary cd-upload-view-direct" 
                                            data-view="<?php echo $view_type; ?>">
                                            <?php echo __('Direct Upload', 'clothing-designer'); ?>
                                        </button>
                                        
                                        <!-- Media Library button -->
                                        <button type="button" 
                                            class="button cd-upload-view" 
                                            data-view="<?php echo $view_type; ?>">
                                            <?php echo __('Media Library', 'clothing-designer'); ?>
                                        </button>
                                    </div>
                                    
                                    <!-- Hidden file input for direct upload -->
                                    <input type="file" 
                                        class="cd-view-file-input" 
                                        data-view="<?php echo $view_type; ?>"
                                        style="display: none;"
                                        accept=".svg,.png,.jpg,.jpeg,.ai">
                                    
                                    <!-- Progress indicator -->
                                    <div class="cd-upload-progress" style="display: none;">
                                        <div class="cd-upload-progress-bar"></div>
                                    </div>
                                    
                                    <p class="description">
                                        <?php echo $view_type === 'front' 
                                            ? __('Front view (required)', 'clothing-designer') 
                                            : sprintf(__('%s view (optional)', 'clothing-designer'), ucfirst($view_type)); 
                                        ?>
                                    </p>
                                    
                                    <?php if (!empty($view_file_url)) : ?>
                                    <div class="cd-view-preview">
                                        <img 
                                            src="<?php echo esc_url($view_file_url); ?>" 
                                            alt="<?php echo sprintf(__('%s view', 'clothing-designer'), ucfirst($view_type)); ?>"
                                        >
                                    </div>
                                    <?php endif; ?>
                                </div> 
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="cd-form-field">
                    <label for="template-thumbnail"><?php echo __('Thumbnail', 'clothing-designer'); ?></label>
                    <div class="cd-thumbnail-container">
                        <div class="cd-thumbnail-preview">
                            <?php if ($template && !empty($template->thumbnail_url)) : ?>
                                <img src="<?php echo esc_url($template->thumbnail_url); ?>" 
                                    alt="<?php echo __('Thumbnail Preview', 'clothing-designer'); ?>"
                                >
                            <?php else : ?>
                                <span class="cd-no-thumbnail">
                                    <?php echo __('No thumbnail selected', 'clothing-designer'); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" 
                            id="template-thumbnail" 
                            name="thumbnail_url" 
                            value="<?php echo $template ? esc_attr($template->thumbnail_url) : ''; ?>"
                        >
                        <button type="button" 
                            class="button cd-upload-thumbnail">
                            <?php echo __('Upload Thumbnail', 'clothing-designer'); ?>
                        </button>
                    </div>
                </div>
                
                <div class="cd-form-field">
                    <label for="template-status">
                        <?php echo __('Status', 'clothing-designer'); ?>
                    </label>
                    <select id="template-status" name="status">
                        <option value="publish" <?php selected($template ? $template->status : 'publish', 'publish'); ?>><?php echo __('Published', 'clothing-designer'); ?></option>
                        <option value="draft" <?php selected($template ? $template->status : 'publish', 'draft'); ?>><?php echo __('Draft', 'clothing-designer'); ?></option>
                    </select>
                </div>
                
                <div class="cd-form-actions">
                    <input type="hidden" name="template_id" value="<?php echo $template ? esc_attr($template->id) : '0'; ?>">
                    <button type="submit" class="button button-primary cd-save-template"><?php echo __('Save Template', 'clothing-designer'); ?></button>
                    <a href="<?php echo admin_url('admin.php?page=clothing-designer'); ?>" class="button button-secondary"><?php echo __('Cancel', 'clothing-designer'); ?></a>
                </div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render settings page.
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo __('Clothing Designer Settings', 'clothing-designer'); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('cd_options_group');
                do_settings_sections('cd_options_page');
                submit_button();
                ?>
            </form>
            
            <div class="cd-shortcode-info">
                <h2><?php echo __('Shortcode Usage', 'clothing-designer'); ?></h2>
                <p><?php echo __('Use the following shortcode to display the clothing designer in your posts or pages:', 'clothing-designer'); ?></p>
                <pre>[clothing_designer template_id="1" width="100%" height="600px"]</pre>
                
                <h3><?php echo __('Parameters', 'clothing-designer'); ?></h3>
                <ul>
                    <li><code>template_id</code> - <?php echo __('The ID of the template to use (required)', 'clothing-designer'); ?></li>
                    <li><code>width</code> - <?php echo __('Width of the designer (optional)', 'clothing-designer'); ?></li>
                    <li><code>height</code> - <?php echo __('Height of the designer (optional)', 'clothing-designer'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render designs page.
     */
    public function render_designs_page() {
        global $wpdb;
        $designs_table = $wpdb->prefix . 'cd_designs';
        $templates_table = $wpdb->prefix . 'cd_templates';
        
        // Pagination settings
        $per_page = 10;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        // Get total count for pagination
        $total_designs = $wpdb->get_var("SELECT COUNT(*) FROM $designs_table");
        $total_pages = ceil($total_designs / $per_page);
        
        // Get designs with pagination
        $designs = $wpdb->get_results($wpdb->prepare("
            SELECT d.*, t.title as template_title, u.display_name as user_name 
            FROM $designs_table d
            LEFT JOIN $templates_table t ON d.template_id = t.id
            LEFT JOIN {$wpdb->users} u ON d.user_id = u.ID
            ORDER BY d.created_at DESC
            LIMIT %d OFFSET %d
        ", $per_page, $offset));
        
        ?>
        <div class="wrap">
            <h1><?php echo __('User Designs', 'clothing-designer'); ?></h1>
            
            <div class="cd-admin-content">
                <?php if (empty($designs)) : ?>
                    <div class="cd-no-designs">
                        <p><?php echo __('No designs found yet.', 'clothing-designer'); ?></p>
                    </div>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <!-- Table content -->
                        <thead>
                            <tr>
                                <th class="check-column">
                                    <input type="checkbox" id="cd-select-all-designs">
                                </th>
                                <th><?php echo __('Preview', 'clothing-designer'); ?></th>
                                <th><?php echo __('Template', 'clothing-designer'); ?></th>
                                <th><?php echo __('User', 'clothing-designer'); ?></th>
                                <th><?php echo __('Created', 'clothing-designer'); ?></th>
                                <th><?php echo __('Actions', 'clothing-designer'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($designs as $design) : ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="cd-design-checkbox" value="<?php echo esc_attr($design->id); ?>">
                                    </td>
                                    <td>
                                        <?php if (!empty($design->preview_url)) : ?>
                                            <img src="<?php echo esc_url($design->preview_url); ?>" alt="<?php echo __('Design Preview', 'clothing-designer'); ?>" style="max-width: 100px; max-height: 100px;">
                                        <?php else : ?>
                                            <span class="cd-no-preview"><?php echo __('No preview', 'clothing-designer'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($design->template_title); ?></td>
                                    <td><?php echo esc_html($design->user_name ?: __('Guest', 'clothing-designer')); ?></td>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($design->created_at))); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url(admin_url('admin-ajax.php?action=cd_view_design&id=' . $design->id . '&nonce=' . wp_create_nonce('cd-view-design'))); ?>" class="button button-small" target="_blank">
                                            <?php echo __('View', 'clothing-designer'); ?>
                                        </a>
                                        <button class="button button-small button-link-delete cd-delete-design" data-id="<?php echo esc_attr($design->id); ?>"><?php echo __('Delete', 'clothing-designer'); ?></button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if ($total_pages > 1) : ?>
                        <div class="tablenav">
                            <div class="tablenav-pages">
                                <?php
                                echo paginate_links(array(
                                    'base' => add_query_arg('paged', '%#%'),
                                    'format' => '',
                                    'prev_text' => '&laquo;',
                                    'next_text' => '&raquo;',
                                    'total' => $total_pages,
                                    'current' => $current_page,
                                ));
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Verify file type is allowed
     *
     * @param string $file_path Path to the file
     * @param array $allowed_extensions Array of allowed extensions
     * @return bool|string True if valid or error message
     */
    private function verify_file_type($file_path, $allowed_extensions) {
        // First check the file extension
        $file_info = pathinfo($file_path);
        $extension = strtolower($file_info['extension']);
        
        if (!in_array($extension, $allowed_extensions)) {
            return sprintf(__('File extension "%s" is not allowed.', 'clothing-designer'), $extension);
        }
        
        // For SVG files, do additional validation
        if ($extension === 'svg') {
            $svg_content = file_get_contents($file_path);
            
            // Check for malicious content
            $dangerous_content = array(
                '<script', 'javascript:', 'onload=', 'onerror=', 'onclick=',
                'onmouseover=', 'onmouseout=', 'onmousedown=', 'onmouseup='
            );
            
            foreach ($dangerous_content as $content) {
                if (stripos($svg_content, $content) !== false) {
                    return __('SVG file contains potentially malicious content.', 'clothing-designer');
                }
            }
            
            // Check that it's a valid SVG file
            if (stripos($svg_content, '<svg') === false) {
                return __('File does not appear to be a valid SVG.', 'clothing-designer');
            }
        }
        
        return true;
    }

    /**
     * Ajax get templates.
     */
    public function ajax_get_templates() {
        // Check nonce
        check_ajax_referer(CD_AJAX_NONCE, 'nonce');
        
        global $wpdb;
        $templates_table = $wpdb->prefix . 'cd_templates';
        
        $templates = $wpdb->get_results("
            SELECT id, title, thumbnail_url 
            FROM $templates_table 
            WHERE status = 'publish' 
            ORDER BY title ASC
        ");
        
        wp_send_json_success(array('templates' => $templates));
    }
    /**
     * Ajax get template views.
     */
    public function ajax_get_template_views() {
        // Check nonce
        check_ajax_referer(CD_AJAX_NONCE, 'nonce');
        
        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        
        if ($template_id <= 0) {            
            wp_send_json_error(array('message' => __('Invalid template ID', 'clothing-designer')));
            return;
        }
        
        global $wpdb;
        $templates_table = $wpdb->prefix . 'cd_templates';
        $template_views_table = $wpdb->prefix . 'cd_template_views';
        
        // Get the template
        $template = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $templates_table WHERE id = %d",
            $template_id
        ));
        
        if (!$template) {
            wp_send_json_error(array('message' => __('Template not found', 'clothing-designer')));
            return;
        }
        
        // Get template views
        $views = array();
        $template_views_results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $template_views_table WHERE template_id = %d",
            $template_id
        ));
        
        foreach ($template_views_results as $view) {
            $views[$view->view_type] = array(
                'id' => $view->id,
                'file_url' => $view->file_url,
                'file_type' => $view->file_type
            );
        }
        
        wp_send_json_success(array(
            'template' => $template,
            'views' => $views
        ));
    }

    /**
     * Ajax save template.
     */
    public function ajax_save_template() {
        // Check nonce
        check_ajax_referer(CD_ADMIN_NONCE, 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'clothing-designer')));
            return;
        }
        
        // Get form data
        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        $description = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
        $file_url = isset($_POST['file_url']) ? esc_url_raw($_POST['file_url']) : '';
        $file_type = pathinfo($file_url, PATHINFO_EXTENSION);
        $thumbnail_url = isset($_POST['thumbnail_url']) ? esc_url_raw($_POST['thumbnail_url']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'publish';
           
        // Get template views data
        $template_views = isset($_POST['template_views']) ? $_POST['template_views'] : array();
    
        // Validate data
        if (empty($title)) {
            wp_send_json_error(array('message' => __('Title is required', 'clothing-designer')));
            return;
        }
        
        // Validate that front view is provided
        if (empty($template_views['front']) || empty($template_views['front']['file_url'])) {
            wp_send_json_error(array('message' => __('Front view is required', 'clothing-designer')));
            return;
        }
        
        global $wpdb;
        $templates_table = $wpdb->prefix . 'cd_templates';
        $template_views_table = $wpdb->prefix . 'cd_template_views';
        
        $data = array(
            'title' => $title,
            'description' => $description,
            'file_url' => $file_url,
            'file_type' => $file_type,
            'thumbnail_url' => $thumbnail_url,
            'status' => $status,
        );
        
        $format = array(
            '%s', // title
            '%s', // description
            '%s', // file_url
            '%s', // file_type
            '%s', // thumbnail_url
            '%s', // status
        );
        
        // Update or insert
        if ($template_id > 0) {
            $result = $wpdb->update(
                $templates_table,
                $data,
                array('id' => $template_id),
                $format,
                array('%d')
            );
            
            $message = __('Template updated successfully', 'clothing-designer');
        } else {
            $result = $wpdb->insert(
                $templates_table,
                $data,
                $format
            );
            
            $template_id = $wpdb->insert_id;
            $message = __('Template added successfully', 'clothing-designer');
        }
    
        // Handle template views
        if ($result !== false && !empty($template_views)) {
            // Get allowed file types from settings
            $options = get_option('cd_options');
            $allowed_file_types = isset($options['allowed_file_types']) ? $options['allowed_file_types'] : array('svg', 'png', 'jpg', 'jpeg', 'ai');
    
            foreach ($template_views as $view_type => $view_data) {
                $view_id = isset($view_data['id']) ? intval($view_data['id']) : 0;
                $view_file_url = isset($view_data['file_url']) ? esc_url_raw($view_data['file_url']) : '';
                
                // Skip if no file URL (except for front which is required - already checked above)
                if (empty($view_file_url)) {
                    if ($view_type === 'front') {
                        wp_send_json_error(array('message' => __('Front view is required', 'clothing-designer')));
                        return;
                    }
                    continue;
                }            
                
                // Verify file type if it's a local file
                $upload_dir = wp_upload_dir();
                $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $view_file_url);
                
                if (file_exists($file_path)) {
                    $verification_result = $this->verify_file_type($file_path, $allowed_file_types);
                    if ($verification_result !== true) {
                        wp_send_json_error(array('message' => $verification_result, 'clothing-designer'));
                        return;
                    }
                }
                
                $view_file_type = pathinfo($view_file_url, PATHINFO_EXTENSION);
                
                if ($view_id > 0) {
                    // Update existing view
                    $update_result = $wpdb->update(
                        $template_views_table,
                        array(
                            'file_url' => $view_file_url,
                            'file_type' => $view_file_type
                        ),
                        array('id' => $view_id),
                        array('%s', '%s'),
                        array('%d')
                    );
                    
                    if ($update_result === false) {
                        wp_send_json_error(array(
                            'message' => sprintf(__('Failed to update %s view', 'clothing-designer'), $view_type),
                            'error' => $wpdb->last_error
                        ));
                        return;
                    }
                } else {
                    // Insert new view
                    $insert_result = $wpdb->insert(
                        $template_views_table,
                        array(
                            'template_id' => $template_id,
                            'view_type' => $view_type,
                            'file_url' => $view_file_url,
                            'file_type' => $view_file_type
                        ),
                        array('%d', '%s', '%s', '%s')
                    );
                    
                    if ($insert_result === false) {
                        wp_send_json_error(array(
                            'message' => sprintf(__('Failed to add %s view', 'clothing-designer'), $view_type),
                            'error' => $wpdb->last_error
                        ));
                        return;
                    }
                }
            }
        }
        
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => $message,
                'template_id' => $template_id
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to save template. Database error occurred.', 'clothing-designer'),
                'error' => $wpdb->last_error
            ));
        }
    }

    /**
     * Ajax delete design.
     */
    public function ajax_delete_design() {
        // Check nonce
        check_ajax_referer(CD_ADMIN_NONCE, 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'clothing-designer')));
            return;
        }
        
         // Get design ID
        $design_id = isset($_POST['design_id']) ? intval($_POST['design_id']) : 0;
        
        if ($design_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid design ID', 'clothing-designer')));
            return;
        }
        
        global $wpdb;
        $designs_table = $wpdb->prefix . 'cd_designs';
        
        // Get the design to delete its preview image
        $design = $wpdb->get_row($wpdb->prepare("SELECT * FROM $designs_table WHERE id = %d", $design_id));
        
        if ($design && !empty($design->preview_url)) {
            // Get file path from URL
            $upload_dir = wp_upload_dir();
            $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $design->preview_url);
            
            // Delete the file if it exists
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
        }
        
        // Delete the design from the database
        $result = $wpdb->delete(
            $designs_table,
            array('id' => $design_id),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(array('message' => __('Design deleted successfully', 'clothing-designer')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete design', 'clothing-designer')));
        }
    }
    
    /**
     * Ajax delete template.
     */
    public function ajax_delete_template() {
        // Check nonce
        check_ajax_referer(CD_ADMIN_NONCE, 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'clothing-designer')));
            return;
        }
        
        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        
        if ($template_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid template ID', 'clothing-designer')));
            return;
        }
        
        global $wpdb;
        $templates_table = $wpdb->prefix . 'cd_templates';
        $template_views_table = $wpdb->prefix . 'cd_template_views';

        $wpdb->delete(
            $template_views_table,
            array('template_id' => $template_id),
            array('%d')
        );

        $result = $wpdb->delete(
            $templates_table,
            array('id' => $template_id),
            array('%d')
        );
        
        if ($result !== false) {
            wp_send_json_success(array('message' => __('Template deleted successfully', 'clothing-designer')));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete template', 'clothing-designer')));
        }
    }

    /**
     * Render API section.
     */
    public function render_api_section() {
        echo '<p>' . __('Configure API integrations for enhanced functionality.', 'clothing-designer') . '</p>';
    }
    

    /**
     * Render Cloudmersive API key field.
     */
    public function render_cloudmersive_api_key_field() {
        $options = get_option('cd_options');
        $api_key = isset($options['cloudmersive_api_key']) ? $options['cloudmersive_api_key'] : '';
        $override_env_api_key = isset($options['override_env_api_key']) ? $options['override_env_api_key'] : 'no';
        $env_api_key = '';
        $env_api_key_exists = false;

        // Check for API key in environment variables
        if (class_exists('CD_Env_Loader')) {
            $env_api_key = CD_Env_Loader::get('CLOUDMERSIVE_API_KEY');
            $env_api_key_exists = !empty($env_api_key);
        }
        
        $disabled = ($env_api_key_exists && $override_env_api_key !== 'yes') ? 'disabled' : '';
        echo '<input type="text" name="cd_options[cloudmersive_api_key]" value="' . esc_attr($api_key) . '" class="regular-text" ' . $disabled . ' />';

        if ($env_api_key_exists) {
            if ($override_env_api_key === 'yes') {
                echo '<p class="description">' . __('Environment variable detected but override is enabled. Your input will be used.', 'clothing-designer') . '</p>';
            } else {
                echo '<p class="description">' . __('API key is set via environment variable. Enable override below to use a custom value.', 'clothing-designer') . '</p>';
            }
        } else {
            echo '<p class="description">' . __('Enter your Cloudmersive API key for AI to SVG conversion.', 'clothing-designer');
            echo ' <a href="https://account.cloudmersive.com/signup" target="_blank">' . __('Get a Cloudmersive API key', 'clothing-designer') . '</a></p>';
        }
    }

    /**
     * Render use Cloudmersive field.
     */
    public function render_use_cloudmersive_field() {
        $options = get_option('cd_options');
        $use_cloudmersive = isset($options['use_cloudmersive']) ? $options['use_cloudmersive'] : 'yes';
        
        echo '<label>';
        echo '<input type="checkbox" name="cd_options[use_cloudmersive]" value="yes" ' . checked($use_cloudmersive, 'yes', false) . ' />';
        echo __('Prioritize Cloudmersive API for AI to SVG conversion (recommended)', 'clothing-designer');
        echo '</label>';
        echo '<p class="description">' . __('If unchecked, local conversion methods will be tried first.', 'clothing-designer') . '</p>';
    }
    /**
     * Add this method to CD_Admin class
     */
    public function render_override_env_api_key_field() {
        $options = get_option('cd_options');
        $override_env_api_key = isset($options['override_env_api_key']) ? $options['override_env_api_key'] : 'no';
        $env_api_key_exists = false;
        
        // Check if environment variable exists
        if (class_exists('CD_Env_Loader')) {
            $env_api_key = CD_Env_Loader::get('CLOUDMERSIVE_API_KEY');
            $env_api_key_exists = !empty($env_api_key);
        }
        
        // Only show this option if environment variable exists
        if ($env_api_key_exists) {
            echo '<label>';
            echo '<input type="checkbox" name="cd_options[override_env_api_key]" value="yes" ' . checked($override_env_api_key, 'yes', false) . ' />';
            echo __('Allow overriding environment variable API key with value entered below', 'clothing-designer');
            echo '</label>';
            echo '<p class="description">' . __('When checked, the API key entered in the field above will be used instead of the environment variable.', 'clothing-designer') . '</p>';
        } else {
            echo '<p class="description">' . __('No environment variable detected. This option only applies when an environment variable is set.', 'clothing-designer') . '</p>';
        }
    }
    
    /**
     * Ajax bulk delete templates.
     */
    public function ajax_bulk_delete_templates() {
        // Check nonce
        check_ajax_referer(CD_ADMIN_NONCE, 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'clothing-designer')));
            return;
        }
        
        // Get template IDs
        $template_ids = isset($_POST['template_ids']) ? array_map('intval', $_POST['template_ids']) : array();
        
        if (empty($template_ids)) {
            wp_send_json_error(array('message' => __('No templates selected', 'clothing-designer')));
            return;
        }
        
        global $wpdb;
        $templates_table = $wpdb->prefix . 'cd_templates';
        $template_views_table = $wpdb->prefix . 'cd_template_views';
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            // Delete template views
            $placeholders = implode(',', array_fill(0, count($template_ids), '%d'));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $template_views_table WHERE template_id IN ($placeholders)",
                $template_ids
            ));
            
            // Delete templates
            $result = $wpdb->query($wpdb->prepare(
                "DELETE FROM $templates_table WHERE id IN ($placeholders)",
                $template_ids
            ));
            
            if ($result === false) {
                throw new Exception($wpdb->last_error);
            }
            
            $wpdb->query('COMMIT');
            
            wp_send_json_success(array(
                'message' => sprintf(__('%d templates deleted successfully', 'clothing-designer'), count($template_ids))
            ));
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Ajax bulk delete designs.
     */
    public function ajax_bulk_delete_designs() {
        // Check nonce
        check_ajax_referer(CD_ADMIN_NONCE, 'nonce');
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'clothing-designer')));
            return;
        }
        
        // Get design IDs
        $design_ids = isset($_POST['design_ids']) ? array_map('intval', $_POST['design_ids']) : array();
        
        if (empty($design_ids)) {
            wp_send_json_error(array('message' => __('No designs selected', 'clothing-designer')));
            return;
        }
        
        global $wpdb;
        $designs_table = $wpdb->prefix . 'cd_designs';
        
        // First, get preview URLs to delete files
        $placeholders = implode(',', array_fill(0, count($design_ids), '%d'));
        $designs = $wpdb->get_results($wpdb->prepare(
            "SELECT id, preview_url, is_chunked FROM $designs_table WHERE id IN ($placeholders)",
            $design_ids
        ));
        
        // Start transaction
        $wpdb->query('START TRANSACTION');
        
        try {
            foreach ($designs as $design) {
                // Delete preview image if exists
                if (!empty($design->preview_url)) {
                    $upload_dir = wp_upload_dir();
                    $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $design->preview_url);
                    
                    if (file_exists($file_path)) {
                        @unlink($file_path);
                    }
                }
                
                // Delete chunked data file if exists
                if ($design->is_chunked) {
                    $file_path = CD_UPLOADS_DIR . 'designs/design-' . $design->id . '.json';
                    if (file_exists($file_path)) {
                        @unlink($file_path);
                    }
                }
            }
            
            // Delete designs from database
            $result = $wpdb->query($wpdb->prepare(
                "DELETE FROM $designs_table WHERE id IN ($placeholders)",
                $design_ids
            ));
            
            if ($result === false) {
                throw new Exception($wpdb->last_error);
            }
            
            $wpdb->query('COMMIT');
            
            wp_send_json_success(array(
                'message' => sprintf(__('%d designs deleted successfully', 'clothing-designer'), count($design_ids))
            ));
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

}