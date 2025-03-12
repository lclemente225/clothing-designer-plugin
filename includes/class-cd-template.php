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
        
        add_action('wp_ajax_cd_view_design', array($this, 'ajax_view_design_template'));
        add_action('wp_ajax_nopriv_cd_view_design', array($this, 'ajax_view_design_template'));
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
        error_log('CD: Getting template only for template id: ' . $template_id);

        return $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$templates_table}` WHERE id = %d AND status = 'publish'", $template_id));
    }
    
    /**
     * Get template file contents.
     *
     * @param string $file_url Template file URL.
     * @return string Template file contents.
     */
    public function get_template_contents($file_url) {
        error_log('CD: Getting template contents for URL: ' . $file_url);
        
        if (empty($file_url)) {
            error_log('CD: Empty file URL provided');
            return '';
        }
        
        // Method 1: Convert URL to server path if it's a local file
        if (strpos($file_url, site_url()) === 0) {
            $file_path = str_replace(site_url('/'), ABSPATH, $file_url);
            
            if (file_exists($file_path)) {
                $content = file_get_contents($file_path);
                if ($content !== false) {
                    error_log('CD: Successfully loaded file content from path (Method 1): ' . $file_path . $c);
                    return $content;
                }
                error_log('CD: Failed to get file contents from path (Method 1): ' . $file_path);
            } else {
                error_log('CD: File does not exist at path (Method 1): ' . $file_path);
            }
        }
        
        // Method 2: Use wp_upload_dir for uploads
        $upload_dir = wp_upload_dir();
        if (strpos($file_url, $upload_dir['baseurl']) === 0) {
            $file_path = str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $file_url);
            
            if (file_exists($file_path)) {
                $content = file_get_contents($file_path);
                if ($content !== false) {
                    error_log('CD: Successfully loaded file content from uploads (Method 2): ' . $file_path);
                    return $content;
                }
                error_log('CD: Failed to get file contents from uploads (Method 2): ' . $file_path);
            } else {
                error_log('CD: File does not exist in uploads (Method 2): ' . $file_path);
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
                    error_log('CD: Successfully loaded file content using WP_Filesystem (Method 3)');
                    return $content;
                }
                error_log('CD: Failed to get file contents using WP_Filesystem (Method 3)');
            } else {
                error_log('CD: WP_Filesystem not available or file path empty (Method 3)');
            }
        }
        
        // Method 4: Fetch via HTTP as last resort
        error_log('CD: Trying to fetch content via HTTP (Method 4): ' . $file_url);
        $response = wp_remote_get($file_url, array(
            'timeout' => 30, // Longer timeout for large files
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            error_log('CD: WP Error fetching URL: ' . $response->get_error_message());
            return '';
        }
        
        $content = wp_remote_retrieve_body($response);
        
        if (empty($content)) {
            error_log('CD: Empty content received from HTTP request');
            return '';
        }
        $content = wp_remote_retrieve_body($response);
        if (!empty($content)) {
            error_log('CD: Successfully loaded file content via HTTP with length: ' . strlen($content) . ' bytes');
            return $content;
        }
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
         // Check design data size
        $data_size = strlen($design_data);
        $max_safe_size = 1024 * 1024; // 1MB
        
        // Start transaction for database consistency
        $wpdb->query('START TRANSACTION');
        
        try {
            // For very large design data, consider chunking or compression
            if ($data_size > $max_safe_size) {
                // Option 1: Compress data
                $compressed_data = gzcompress($design_data);
                if ($compressed_data !== false) {
                    $design_data = $compressed_data;
                    $is_compressed = true;
                }
                
                // Option 2: If still too large, store metadata only and save full data separately
                if (strlen($design_data) > $max_safe_size) {
                    // Extract basic metadata
                    $design_metadata = $this->extract_design_metadata($design_data);
                    
                    // Save metadata to database
                    $result = $wpdb->insert(
                        $designs_table,
                        array(
                            'user_id' => $user_id,
                            'template_id' => $template_id,
                            'design_data' => $design_metadata,
                            'preview_url' => $preview_url,
                            'is_chunked' => 1,
                        ),
                        array('%d', '%d', '%s', '%s', '%d')
                    );
                    
                    if ($result === false) {
                        $wpdb->query('ROLLBACK');
                        return false;
                    }
                    
                    $design_id = $wpdb->insert_id;
                    
                    // Save full data to file
                    $file_path = CD_UPLOADS_DIR . 'designs/design-' . $design_id . '.json';
                    if (!is_dir(dirname($file_path))) {
                        wp_mkdir_p(dirname($file_path));
                    }
                    
                    $file_saved = file_put_contents($file_path, $design_data);
                    if ($file_saved === false) {
                        $wpdb->query('ROLLBACK');
                        return false;
                    }
                    
                    $wpdb->query('COMMIT');
                    return $design_id;
                }
            }
            
            // Normal case: save directly to database
            $result = $wpdb->insert(
                $designs_table,
                array(
                    'user_id' => $user_id,
                    'template_id' => $template_id,
                    'design_data' => $design_data,
                    'preview_url' => $preview_url,
                    'is_compressed' => isset($is_compressed) ? 1 : 0,
                ),
                array('%d', '%d', '%s', '%s', '%d')
            );
            
            if ($result === false) {
                $wpdb->query('ROLLBACK');
                return false;
            }
            
            $wpdb->query('COMMIT');
            return $wpdb->insert_id;
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('Clothing Designer: Error saving design: ' . $e->getMessage());
            return false;
        }
        ;
    }

    /**
     * Extract basic metadata from design data
     * 
     * @param string $design_data Full JSON design data
     * @return string Basic metadata JSON
     */
    private function extract_design_metadata($design_data) {
        $data = json_decode($design_data, true);
        
        // If JSON parsing fails, return a minimal structure
        if (json_last_error() !== JSON_ERROR_NONE) {
            return json_encode(array(
                'is_chunked' => true,
                'timestamp' => time()
            ));
        }
        
        // Create a reduced version with essential metadata
        $metadata = array(
            'is_chunked' => true,
            'timestamp' => time(),
            'currentView' => isset($data['currentView']) ? $data['currentView'] : 'front',
            'views' => array()
        );
        
        // Include basic structure but remove large content
        if (isset($data['views']) && is_array($data['views'])) {
            foreach ($data['views'] as $viewName => $view) {
                $metadata['views'][$viewName] = array(
                    'elementCount' => isset($view['elements']) ? count($view['elements']) : 0
                );
            }
        }
        
        return json_encode($metadata);
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
        
        $design = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$designs_table}` WHERE id = %d", $design_id));
        
        if (!$design) {
            return false;
        }
        
        // Handle compressed data
        if (isset($design->is_compressed) && $design->is_compressed) {
            $design->design_data = gzuncompress($design->design_data);
        }
        
        // Handle chunked data
        if (isset($design->is_chunked) && $design->is_chunked) {
            $file_path = CD_UPLOADS_DIR . 'designs/design-' . $design_id . '.json';
            
            if (file_exists($file_path)) {
                $full_data = file_get_contents($file_path);
                if ($full_data !== false) {
                    $design->design_data = $full_data;
                    
                    // Handle if this is also compressed
                    if (isset($design->is_compressed) && $design->is_compressed) {
                        $design->design_data = gzuncompress($design->design_data);
                    }
                } else {
                    error_log("Clothing Designer: Could not read chunked design data from file: {$file_path}");
                }
            } else {
                error_log("Clothing Designer: Chunked design data file not found: {$file_path}");
            }
        }
        
        return $design;
    }
    
    /**
     * Ajax get template.
     */
    public function ajax_get_template() {
        // Check nonce
        check_ajax_referer(CD_AJAX_NONCE, 'nonce');
        
        // Get template ID
        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        error_log('ajax get template check for template id' . $template_id);

        if ($template_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid template ID', 'clothing-designer')));
            return;
        }
        
        // Get template
        $template = $this->get_template($template_id);
        error_log('ajax get template check template' . json_encode($template));

        if (!$template) {
            wp_send_json_error(array('message' => __('Template not found', 'clothing-designer')));
            return;
        }
        
        // Get template views
        global $wpdb;
        $template_views_table = $wpdb->prefix . 'cd_template_views';
        
        // Get the front view specifically
        $template_view = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$template_views_table}` WHERE template_id = %d AND view_type = 'front'",
            $template_id
        ));
        // Get template file contents
        $file_contents = $this->get_template_contents($template_view->file_url);
        
        if (empty($file_contents)) {
            wp_send_json_error(array('message' => __('Failed to load template file', 'clothing-designer')));
            return;
        }
        
        // Prepare template data
        $template_data = array(
            'id' => $template->id,
            'title' => $template->title,
            'file_url' => $template_view->file_url,
            'file_type' => $template_view->file_type,
            'content' => $file_contents,
        );
        
        wp_send_json_success(array('template' => $template_data));
        return json_encode($template_data);
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
        $design_data = isset($_POST['design_data']) ? wp_unslash($_POST['design_data']) : '';
        $preview_image = isset($_POST['preview_image']) ? $_POST['preview_image'] : '';
        if ($template_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid template ID', 'clothing-designer')));
            return;
        }
        
        $preview_url = '';
        if (!empty($preview_image)) {
            // Extract the base64 data
            $image_parts = explode(';base64,', $preview_image);
            
            // Make sure we have valid base64 data
            if (count($image_parts) === 2) {
                $image_data = base64_decode($image_parts[1]);
                
                // Generate a unique filename
                $filename = 'design-preview-' . uniqid() . '.png';
                $upload_dir = CD_UPLOADS_DIR;
                $file_path = $upload_dir . $filename;
                
                // Ensure directory exists
                if (!file_exists($upload_dir)) {
                    wp_mkdir_p($upload_dir);
                }
                
                // Save the file
                if (file_put_contents($file_path, $image_data)) {
                    $preview_url = CD_UPLOADS_URL . $filename;
                } else {
                    error_log('Failed to save preview image: ' . $file_path);
                }
            }
        }
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
         // Check if GD library is available
        if (!function_exists('imagecreatefromstring') || !function_exists('imagecreatetruecolor')) {
            error_log('Clothing Designer: GD library functions not available for image resizing');
            return $image_data; // Return original if GD not available
        }

        // Create image resource from data
        $source = imagecreatefromstring($image_data);
        if (!$source) {
            error_log('Clothing Designer: Failed to create image from string data');
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
            if (!$new_image) {
                imagedestroy($source);
                error_log('Clothing Designer: Failed to create resized image');
                return $image_data;
            }
            
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
        // Get design
        $design = $this->get_design($design_id);
        // Get template
        $template = $this->get_template($design->template_id);
        // Get template file contents
        $file_contents = $this->get_template_contents($template->file_url);

        if ($design_id <= 0) {
            wp_send_json_error(array('message' => __('Invalid design ID', 'clothing-designer')));
            return;
        }
        if (!$design) {
            wp_send_json_error(array('message' => __('Design not found', 'clothing-designer')));
            return;
        }
        // In CD_Template's ajax_load_design method:
        if (!$this->can_access_design($design)) {
            wp_send_json_error(array('message' => __('You do not have permission to access this design', 'clothing-designer')));
            return;
        }
        if (!$template) {
            wp_send_json_error(array('message' => __('Template not found', 'clothing-designer')));
            return;
        }
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
    public function ajax_view_design_template() {
        // Check nonce
        check_ajax_referer('cd-view-design', 'nonce');
       
        // Get design ID
        $design_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        // Check permissions - allow viewing by design owners or administrators
        $design = $this->get_design($design_id);

        if (!$design) {
            wp_die(__('Design not found', 'clothing-designer'));
            return;
        }

        if ($design_id <= 0) {
                wp_die(__('Invalid design ID', 'clothing-designer'));
                return;
        }

        // Allow admins and design creators to view designs
        // In CD_Template's ajax_view_design method:
        if (!$this->can_access_design($design)) {
            wp_die(__('You do not have permission to access this design', 'clothing-designer'));
            return;
        }        
        
        // Get template
        $template = $this->get_template($design->template_id);
        
        if (!$template) {
            wp_die(__('Template not found', 'clothing-designer'));
            return;
        }
        $design->template_title = $template->title;
        // Get user info
        $user_info = $design->user_id > 0 ? get_userdata($design->user_id) : null;
        $user_name = $user_info ? $user_info->display_name : __('Guest', 'clothing-designer');
        
        // Output design preview
        include(CD_PLUGIN_DIR . 'templates/design-view.php');
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
        check_ajax_referer(CD_AJAX_NONCE, 'nonce');
        
        // Get parameters
        $design_id = isset($_POST['design_id']) ? intval($_POST['design_id']) : 0;
        $element_id = isset($_POST['element_id']) ? sanitize_text_field($_POST['element_id']) : '';
        $svg_content = isset($_POST['svg_content']) ? wp_unslash($_POST['svg_content']) : '';
        $view_types = isset($_POST['view_types']) ? sanitize_text_field($_POST['view_types']) : '';
        error_log('Saving design with views: ' . $view_types);

        if ($design_id <= 0 || empty($element_id) || empty($svg_content)) {
            wp_send_json_error();
            return;
        }
        
        // Get existing design
        global $wpdb;
        $designs_table = $wpdb->prefix . 'cd_designs';
        $design = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$designs_table}` WHERE id = %d", $design_id));
        
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
        
        if ($result === false) {
            wp_send_json_error(array('message' => 'Database update failed', 'error' => $wpdb->last_error));
        } else {
            wp_send_json_success(array('updated' => true, 'view' => $view_types));
        }
    }
    /**
     * Get template with all views.
     */
    public function ajax_get_template_views() {
        error_log('CD: Template views requested');
        // Check nonce
        check_ajax_referer(CD_AJAX_NONCE, 'nonce');
        
        // Get template ID
        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        error_log('CD: Template views requested for template ID: ' . $template_id);

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
        error_log(message: 'CD: check template Id' . $template_id);

        $views = array();
        $view_results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `{$template_views_table}` WHERE template_id = %d",
            $template_id
        ));
        
        // Front view is the main template if no specific views are set
        if (empty($view_results)) {
            error_log(message: 'CD: No specific views found, using main template as front view');
            $content = $this->get_template_contents($template->file_url);
            if (!empty($content)) {
                $views['front'] = array(
                    'id' => 0,
                    'template_id' => $template_id,
                    'view_type' => 'front',
                    'file_url' => $template->file_url,
                    'file_type' => $template->file_type,
                    'content' => $content 
                );
                error_log('CD: Added front view with content length: ' . strlen($content));
            }
        } else {
            foreach ($view_results as $view) {
                error_log('CD: Processing view type: ' . $view->view_type);
                $content = $this->get_template_contents($view->file_url);
                if (!empty($content)) {
                    $views[$view->view_type] = array(
                        'id' => $view->id,
                        'template_id' => $view->template_id,
                        'view_type' => $view->view_type,
                        'file_url' => $view->file_url,
                        'file_type' => $view->file_type,
                        'content' => $content  // Make sure this is included
                    );
                    error_log('CD: Added view: ' . $view->view_type . ' with content length: ' . strlen($content));
                }else {
                    error_log('CD: Failed to get content for view: ' . $view->view_type);
                }
            }
            // Make sure front view exists (if not already defined)
            if (!isset($views['front']) && !empty($view_results)) {
                error_log('CD: Front view not found in results, adding default front view');

                $content = $this->get_template_contents($view->file_url);
                $views['front'] = array(
                    'id' => 0, 
                    'template_id' => $template_id,
                    'view_type' => 'front',
                    'file_url' => $template->file_url,
                    'file_type' => $template->file_type,
                    'content' => $content
                );
            }else {
                error_log('CD: Failed to get content for default front view');
            }
        }
        
        // Prepare template data
        $template_data = array(
            'id' => $template->id,
            'title' => $template->title,
            'file_url' => $template->file_url,
            'file_type' => $template->file_type
        );
        error_log('CD: Sending template views response with views: ' . implode(', ', array_keys($views)));

        wp_send_json_success(array(
            'template' => $template_data,
            'views' => $views
        ));
    }
    
}