<?php
/**
 * Template class
 *
 * @package Clothing_Designer
 */

if (!defined('ABSPATH')) {
    exit;
}

class CD_Template {
    
    /**
     * Instance.
     *
     * @var CD_Template
     */
    private static $instance = null;
    
    /**
     * Get instance.
     *
     * @return CD_Template
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
        // Ajax handlers
        add_action('wp_ajax_cd_get_template', array($this, 'ajax_get_template'));
        add_action('wp_ajax_nopriv_cd_get_template', array($this, 'ajax_get_template'));
        
        add_action('wp_ajax_cd_save_design', array($this, 'ajax_save_design'));
        add_action('wp_ajax_nopriv_cd_save_design', array($this, 'ajax_save_design'));
        
        add_action('wp_ajax_cd_load_design', array($this, 'ajax_load_design'));
        add_action('wp_ajax_nopriv_cd_load_design', array($this, 'ajax_load_design'));
        
        add_action('wp_ajax_cd_view_design', array($this, 'ajax_view_design'));
        add_action('wp_ajax_nopriv_cd_view_design', array($this, 'ajax_view_design'));
        // Add handler for saving individual design elements
        add_action('wp_ajax_cd_save_design_element', array($this, 'ajax_save_design_element'));
        add_action('wp_ajax_nopriv_cd_save_design_element', array($this, 'ajax_save_design_element'));

        add_action('wp_ajax_cd_get_template_views', array($this, 'ajax_get_template_views'));
        add_action('wp_ajax_nopriv_cd_get_template_views', array($this, 'ajax_get_template_views'));
    }
    
    /**
     * Get template by ID.
     *
     * @param int $template_id Template ID.
     * @return object|false Template object or false if not found.
     */
    public function get_template($template_id) {
        global $wpdb;
        $templates_table = $wpdb->prefix . 'cd_templates';
        
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $templates_table WHERE id = %d AND status = 'publish'", $template_id));
    }
    
    /**
     * Get template file contents.
     *
     * @param string $file_url Template file URL.
     * @return string Template file contents.
     */
    public function get_template_contents($file_url) {
        // Method 1: Convert URL to server path if it's a local file
        if (strpos($file_url, site_url()) === 0) {
            $file_path = str_replace(site_url('/'), ABSPATH, $file_url);
            
            if (file_exists($file_path)) {
                $content = file_get_contents($file_path);
                if ($content !== false) {
                    return $content;
                }
            }
        }
        
        // Method 2: Use wp_upload_dir for uploads
        $upload_dir = wp_upload_dir();
        if (strpos($file_url, $upload_dir['baseurl']) === 0) {
            $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $file_url);
            
            if (file_exists($file_path)) {
                $content = file_get_contents($file_path);
                if ($content !== false) {
                    return $content;
                }
            }
        }
        
        // Method 3: Try to use the filesystem API
        if (function_exists('WP_Filesystem')) {
            global $wp_filesystem;
            
            if (empty($wp_filesystem)) {
                require_once(ABSPATH . '/wp-admin/includes/file.php');
                WP_Filesystem();
            }
            
            if (isset($wp_filesystem) && !empty($file_path)) {
                $content = $wp_filesystem->get_contents($file_path);
                if ($content !== false) {
                    return $content;
                }
            }
        }
        
        // Method 4: Fetch via HTTP as last resort
        $response = wp_remote_get($file_url, array(
            'timeout' => 30, // Longer timeout for large files
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            return '';
        }
        
        return wp_remote_retrieve_body($response);
    }
    
    /**
     * Save design.
     *
     * @param int $user_id User ID.
     * @param int $template_id Template ID.
     * @param string $design_data Design data.
     * @param string $preview_url Preview URL.
     * @return int|false Design ID or false on failure.
     */
    public function save_design($user_id, $template_id, $design_data, $preview_url) {
        global $wpdb;
        $designs_table = $wpdb->prefix . 'cd_designs';
        
        $result = $wpdb->insert(
            $designs_table,
            array(
                'user_id' => $user_id,
                'template_id' => $template_id,
                'design_data' => $design_data,
                'preview_url' => $preview_url,
            ),
            array(
                '%d', // user_id
                '%d', // template_id
                '%s', // design_data
                '%s', // preview_url
            )
        );
        
        if ($result === false) {
            return false;
        }
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get design by ID.
     *
     * @param int $design_id Design ID.
     * @return object|false Design object or false if not found.
     */
    public function get_design($design_id) {
        global $wpdb;
        $designs_table = $wpdb->prefix . 'cd_designs';
        
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $designs_table WHERE id = %d", $design_id));
    }
    
    /**
     * Ajax get template.
     */
    public function ajax_get_template() {
        // Check nonce
        check_ajax_referer(CD_AJAX_NONCE, 'nonce');
        
        // Get template ID
        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        
        if ($template_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid template ID', 'clothing-designer')));
            return;
        }
        
        // Get template
        $template = $this->get_template($template_id);
        
        if (!$template) {
            wp_send_json_error(array('message' => __('Template not found', 'clothing-designer')));
            return;
        }
        
        // Get template file contents
        $file_contents = $this->get_template_contents($template->file_url);
        
        if (empty($file_contents)) {
            wp_send_json_error(array('message' => __('Failed to load template file', 'clothing-designer')));
            return;
        }
        
        // Prepare template data
        $template_data = array(
            'id' => $template->id,
            'title' => $template->title,
            'file_url' => $template->file_url,
            'file_type' => $template->file_type,
            'content' => $file_contents,
        );
        
        wp_send_json_success(array('template' => $template_data));
    }
    
    /**
     * Ajax save design.
     */
    public function ajax_save_design() {
        // Check nonce
        check_ajax_referer(CD_AJAX_NONCE, 'nonce');
        
        // Check if guest designs are allowed
        $options = get_option('cd_options', array());
        $allow_guest_designs = isset($options['allow_guest_designs']) ? $options['allow_guest_designs'] : 'yes';
        
        if ($allow_guest_designs !== 'yes' && !is_user_logged_in()) {
            wp_send_json_error(array('message' => __('You must be logged in to save designs', 'clothing-designer')));
            return;
        }
        
        // Get user ID
        $user_id = get_current_user_id();
        
        // Get template ID
        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        
        if ($template_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid template ID', 'clothing-designer')));
            return;
        }
        
        // Get design data
        $design_data = isset($_POST['design_data']) ? $_POST['design_data'] : '';

        // Don't try to validate JSON structure - just make sure it's valid JSON
        if (empty($design_data)) {
            wp_send_json_error(array('message' => __('No design data provided', 'clothing-designer')));
            return;
        }
        // Check if it's valid JSON
        json_decode($design_data);
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(array('message' => __('Invalid design data format', 'clothing-designer')));
            return;
        }

        
        if (empty($design_data)) {
            wp_send_json_error(array('message' => __('No design data provided', 'clothing-designer')));
            return;
        }
        
        // Get preview image
        $preview_url = '';
        
        if (isset($_POST['preview_image']) && !empty($_POST['preview_image'])) {
            // Get preview image data
            $preview_image = sanitize_text_field($_POST['preview_image']);
            
            // Remove data URL prefix
            $preview_image = str_replace('data:image/png;base64,', '', $preview_image);
            $preview_image = str_replace(' ', '+', $preview_image);
            
            // Decode base64 data
            $preview_data = base64_decode($preview_image);
            
            if ($preview_data !== false) {
                // Check image size (limit to 1MB)
                $max_size = 1024 * 1024; // 1MB
                if (strlen($preview_data) > $max_size) {
                    // Resize or compress image instead of rejecting
                    $preview_data = $this->resize_image_data($preview_data);
                }

                // Generate filename
                $filename = 'design-preview-' . uniqid() . '.png';
                $file_path = CD_UPLOADS_DIR . $filename;
                
                // Save file
                if (file_put_contents($file_path, $preview_data)) {
                    $preview_url = CD_UPLOADS_URL . $filename;
                }
            }
        }
        
        // Save design
        $design_id = $this->save_design($user_id, $template_id, $design_data, $preview_url);
        
        if ($design_id === false) {
            wp_send_json_error(array('message' => __('Failed to save design', 'clothing-designer')));
            return;
        }
        
        wp_send_json_success(array(
            'design_id' => $design_id,
            'preview_url' => $preview_url,
            'message' => __('Design saved successfully', 'clothing-designer')
        ));
    }

    /**
     * Resize image data to reduce file size
     *
     * @param string $image_data Raw image data
     * @param int $max_width Maximum width (default 800px)
     * @return string Resized image data
     */
    private function resize_image_data($image_data, $max_width = 800) {
        // Create image resource from data
        $source = imagecreatefromstring($image_data);
        if (!$source) {
            return $image_data; // Return original if creation fails
        }
        
        // Get dimensions
        $width = imagesx($source);
        $height = imagesy($source);
        
        // Only resize if width is greater than max
        if ($width > $max_width) {
            $new_width = $max_width;
            $new_height = floor($height * ($new_width / $width));
            
            // Create new image
            $new_image = imagecreatetruecolor($new_width, $new_height);
            
            // Preserve transparency
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
            $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
            imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);
            
            // Resize
            imagecopyresampled($new_image, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
            
            // Output to buffer
            ob_start();
            imagepng($new_image, null, 9); // Highest compression
            $resized_data = ob_get_contents();
            ob_end_clean();
            
            // Clean up
            imagedestroy($source);
            imagedestroy($new_image);
            
            return $resized_data;
        }
        
        // Clean up
        imagedestroy($source);
        
        return $image_data;
    }
    
    /**
     * Ajax load design.
     */
    public function ajax_load_design() {
        // Check nonce
        check_ajax_referer(CD_AJAX_NONCE, 'nonce');
        
        // Get design ID
        $design_id = isset($_POST['design_id']) ? intval($_POST['design_id']) : 0;
        
        if ($design_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid design ID', 'clothing-designer')));
            return;
        }
        
        // Get design
        $design = $this->get_design($design_id);
        
        if (!$design) {
            wp_send_json_error(array('message' => __('Design not found', 'clothing-designer')));
            return;
        }
        // In CD_Template's ajax_load_design method:
        if (!$this->can_access_design($design)) {
            wp_send_json_error(array('message' => __('You do not have permission to access this design', 'clothing-designer')));
            return;
        }
        
        
        // Get template
        $template = $this->get_template($design->template_id);
        
        if (!$template) {
            wp_send_json_error(array('message' => __('Template not found', 'clothing-designer')));
            return;
        }
        
        // Get template file contents
        $file_contents = $this->get_template_contents($template->file_url);
        
        if (empty($file_contents)) {
            wp_send_json_error(array('message' => __('Failed to load template file', 'clothing-designer')));
            return;
        }
        
        // Prepare design data
        $design_data = array(
            'id' => $design->id,
            'user_id' => $design->user_id,
            'template_id' => $design->template_id,
            'template_title' => $template->title,
            'template_file_url' => $template->file_url,
            'template_file_type' => $template->file_type,
            'template_content' => $file_contents,
            'design_data' => $design->design_data,
            'preview_url' => $design->preview_url,
            'created_at' => $design->created_at
        );
        
        wp_send_json_success(array('design' => $design_data));
    }
    
    /**
     * Ajax view design.
     */
    public function ajax_view_design() {
        // Check nonce
        check_ajax_referer('cd-view-design', 'nonce');
       
        // Check permissions - allow viewing by design owners or administrators
        $design = $this->get_design($design_id);

        if (!$design) {
            wp_die(__('Design not found', 'clothing-designer'));
            return;
        }

        // Allow admins and design creators to view designs
        // In CD_Template's ajax_view_design method:
        if (!$this->can_access_design($design)) {
            wp_die(__('You do not have permission to access this design', 'clothing-designer'));
            return;
        }

        
        // Get design ID
        $design_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($design_id <= 0) {
            wp_die(__('Invalid design ID', 'clothing-designer'));
            return;
        }
        
        // Get design
        $design = $this->get_design($design_id);
        
        if (!$design) {
            wp_die(__('Design not found', 'clothing-designer'));
            return;
        }
        
        // Get template
        $template = $this->get_template($design->template_id);
        
        if (!$template) {
            wp_die(__('Template not found', 'clothing-designer'));
            return;
        }
        
        // Get user info
        $user_info = $design->user_id > 0 ? get_userdata($design->user_id) : null;
        $user_name = $user_info ? $user_info->display_name : __('Guest', 'clothing-designer');
        
        // Output design preview
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo sprintf(__('Design Preview - %s', 'clothing-designer'), esc_html($template->title)); ?></title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                    background-color: #f0f0f0;
                    margin: 0;
                    padding: 20px;
                }
                .design-preview {
                    max-width: 800px;
                    margin: 0 auto;
                    background-color: #fff;
                    border-radius: 5px;
                    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
                    overflow: hidden;
                }
                .design-header {
                    padding: 20px;
                    background-color: #f8f8f8;
                    border-bottom: 1px solid #e5e5e5;
                }
                .design-title {
                    margin: 0 0 10px;
                    font-size: 24px;
                }
                .design-meta {
                    color: #666;
                    font-size: 14px;
                }
                .design-image {
                    text-align: center;
                    padding: 30px;
                }
                .design-image img {
                    max-width: 100%;
                    height: auto;
                    display: block;
                    margin: 0 auto;
                    max-height: 500px;
                }
                .design-footer {
                    padding: 15px 20px;
                    border-top: 1px solid #e5e5e5;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    font-size: 14px;
                }
                .design-user {
                    color: #555;
                }
                .design-date {
                    color: #888;
                }
                .no-image {
                    background-color: #f5f5f5;
                    padding: 100px 30px;
                    text-align: center;
                    color: #888;
                    font-size: 18px;
                }
            </style>
        </head>
        <body>
            <div class="design-preview">
                <div class="design-header">
                    <h1 class="design-title"><?php echo esc_html($template->title); ?> - <?php echo __('Design Preview', 'clothing-designer'); ?></h1>
                    <div class="design-meta">
                        <?php echo __('Template:', 'clothing-designer'); ?> <strong><?php echo esc_html($template->title); ?></strong>
                    </div>
                </div>
                
                <div class="design-image">
                    <?php if (!empty($design->preview_url)) : ?>
                        <img src="<?php echo esc_url($design->preview_url); ?>" alt="<?php echo __('Design Preview', 'clothing-designer'); ?>">
                    <?php else : ?>
                        <div class="no-image"><?php echo __('No preview image available', 'clothing-designer'); ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="design-footer">
                    <div class="design-user">
                        <?php echo __('Created by:', 'clothing-designer'); ?> <strong><?php echo esc_html($user_name); ?></strong>
                    </div>
                    <div class="design-date">
                        <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($design->created_at))); ?>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

    /**
     * Check if user can view or edit a design.
     *
     * @param object $design Design object.
     * @param bool $require_edit_permission Whether to check for edit permission rather than just view.
     * @return bool
     */
    public function can_access_design($design, $require_edit_permission = false) {
        // Admins can always access
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // Get current user ID
        $current_user_id = get_current_user_id();
        
        // Check if it's a guest design (user_id = 0)
        if ($design->user_id === 0) {
            // For guest designs, allow viewing but only admin editing
            return $require_edit_permission ? false : true;
        }
        
        // Users can access their own designs
        if ($current_user_id > 0 && $design->user_id === $current_user_id) {
            return true;
        }
        
        // Check if guest designs are allowed for viewing
        if (!$require_edit_permission) {
            $options = get_option('cd_options', array());
            $allow_guest_designs = isset($options['allow_guest_designs']) ? $options['allow_guest_designs'] : 'yes';
            
            if ($allow_guest_designs === 'yes') {
                return true;
            }
        }
        
        return false;
    }

    // Add this new method
    public function ajax_save_design_element() {
        // Check nonce
        check_ajax_referer('cd-ajax-nonce', 'nonce');
        
        // Get parameters
        $design_id = isset($_POST['design_id']) ? intval($_POST['design_id']) : 0;
        $element_id = isset($_POST['element_id']) ? sanitize_text_field($_POST['element_id']) : '';
        $svg_content = isset($_POST['svg_content']) ? wp_unslash($_POST['svg_content']) : '';
        
        if ($design_id <= 0 || empty($element_id) || empty($svg_content)) {
            wp_send_json_error();
            return;
        }
        
        // Get existing design
        global $wpdb;
        $designs_table = $wpdb->prefix . 'cd_designs';
        $design = $wpdb->get_row($wpdb->prepare("SELECT * FROM $designs_table WHERE id = %d", $design_id));
        
        if (!$design) {
            wp_send_json_error();
            return;
        }
        
        // Parse existing design data
        $design_data = json_decode($design->design_data, true);
        if (!$design_data || !isset($design_data['elements'])) {
            wp_send_json_error();
            return;
        }
        
        // Update element with SVG content
        foreach ($design_data['elements'] as &$element) {
            if (isset($element['id']) && $element['id'] === $element_id) {
                $element['svg_content'] = $svg_content;
                break;
            }
        }
        
        // Update design data in database
        $result = $wpdb->update(
            $designs_table,
            array('design_data' => wp_json_encode($design_data)),
            array('id' => $design_id),
            array('%s'),
            array('%d')
        );
        
        wp_send_json_success();
    }
    /**
     * Get template with all views.
     */
    public function ajax_get_template_views() {
        // Check nonce
        check_ajax_referer('cd-ajax-nonce', 'nonce');
        
        // Get template ID
        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        
        if ($template_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid template ID', 'clothing-designer')));
            return;
        }
        
        // Get template
        $template = $this->get_template($template_id);
        
        if (!$template) {
            wp_send_json_error(array('message' => __('Template not found', 'clothing-designer')));
            return;
        }
        
        // Get template views
        global $wpdb;
        $template_views_table = $wpdb->prefix . 'cd_template_views';
        
        $views = array();
        $view_results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $template_views_table WHERE template_id = %d",
            $template_id
        ));
        
        // Front view is the main template if no specific views are set
        if (empty($view_results)) {
            $views['front'] = array(
                'id' => 0,
                'template_id' => $template_id,
                'view_type' => 'front',
                'file_url' => $template->file_url,
                'file_type' => $template->file_type,
                'content' => $this->get_template_contents($template->file_url)
            );
        } else {
            foreach ($view_results as $view) {
                $content = $this->get_template_contents($view->file_url);
                
                $views[$view->view_type] = array(
                    'id' => $view->id,
                    'template_id' => $view->template_id,
                    'view_type' => $view->view_type,
                    'file_url' => $view->file_url,
                    'file_type' => $view->file_type,
                    'content' => $content
                );
            }
        }
        
        // Prepare template data
        $template_data = array(
            'id' => $template->id,
            'title' => $template->title,
            'file_url' => $template->file_url,
            'file_type' => $template->file_type
        );
        
        wp_send_json_success(array(
            'template' => $template_data,
            'views' => $views
        ));
    }
    
}