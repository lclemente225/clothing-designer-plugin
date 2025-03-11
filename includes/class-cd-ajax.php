<?php
/**
 * Ajax class
 *
 * @package Clothing_Designer
 */

if (!defined('ABSPATH')) {
    exit;
}

class CD_Ajax {
    
    /**
     * Instance.
     *
     * @var CD_Ajax
     */
    private static $instance = null;
    
    /**
     * Get instance.
     *
     * @return CD_Ajax
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
        // File upload handler
        add_action('wp_ajax_cd_upload_file', array($this, 'ajax_upload_file'));
        add_action('wp_ajax_nopriv_cd_upload_file', array($this, 'ajax_upload_file'));
        
        // SVG text extraction
        add_action('wp_ajax_cd_extract_svg_text', array($this, 'ajax_extract_svg_text'));
        add_action('wp_ajax_nopriv_cd_extract_svg_text', array($this, 'ajax_extract_svg_text'));
        
        // AI to SVG conversion
        add_action('wp_ajax_cd_convert_ai_to_svg', array($this, 'ajax_convert_ai_to_svg'));
        add_action('wp_ajax_nopriv_cd_convert_ai_to_svg', array($this, 'ajax_convert_ai_to_svg'));

        // Add handler for updating SVG text
        add_action('wp_ajax_cd_update_svg_text', array($this, 'ajax_update_svg_text'));
        add_action('wp_ajax_nopriv_cd_update_svg_text', array($this, 'ajax_update_svg_text'));

        add_action('wp_ajax_cd_save_design', array($this, 'ajax_save_design'));
        add_action('wp_ajax_nopriv_cd_save_design', array($this, 'ajax_save_design'));
        
        add_action('wp_ajax_cd_load_design', array($this, 'ajax_load_design'));
        add_action('wp_ajax_nopriv_cd_load_design', array($this, 'ajax_load_design'));
        
        add_action('wp_ajax_cd_save_design_element', array($this, 'ajax_save_design_element'));
        add_action('wp_ajax_nopriv_cd_save_design_element', array($this, 'ajax_save_design_element'));
        
        add_action('wp_ajax_cd_view_design', array($this, 'ajax_view_design'));
        add_action('wp_ajax_nopriv_cd_view_design', array($this, 'ajax_view_design'));
        
    }
    
    /**
     * Ajax upload file.
     */
    public function ajax_upload_file() {
        // Check nonce
        if (!isset($_REQUEST['nonce']) || !wp_verify_nonce($_REQUEST['nonce'], CD_AJAX_NONCE)) {
            wp_send_json_error(array('message' => __('Security check failed', 'clothing-designer')));
            return;
        }

        // Check if file was uploaded
        if (!isset($_FILES['file']) || empty($_FILES['file']['tmp_name'])) {
            wp_send_json_error(array('message' => __('No file uploaded', 'clothing-designer')));
            return;
        }
        
        // Check allowed file types
        $options = get_option('cd_options', array());
        $allowed_file_types = isset($options['allowed_file_types']) ? $options['allowed_file_types'] : array('svg', 'png', 'jpg', 'jpeg', 'ai');
        
        $file = $_FILES['file'];
        $file_name = sanitize_file_name($file['name']);
        $file_type = $file['type'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        $file_error = $file['error'];
        
        // Check for upload errors
        if ($file_error !== UPLOAD_ERR_OK) {
            $error_message = $this->get_upload_error_message($file_error);
            wp_send_json_error(array('message' => $error_message));
            return;
        }
        
        // Get file extension
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Check file extension is allowed
        if (!in_array($file_ext, $allowed_file_types)) {
            $allowed_types_str = implode(', ', array_map(function($ext) { return '.' . $ext; }, $allowed_file_types));
            wp_send_json_error(array(
                'message' => __('File type not allowed. Allowed file types:', 'clothing-designer') . ' ' . $allowed_types_str
            ));
            return;
        }


        // Check file size
        $max_size = wp_max_upload_size();
        if ($file_size > $max_size) {
            wp_send_json_error(array(
                'message' => sprintf(
                    __('File exceeds maximum upload size of %s', 'clothing-designer'),
                    size_format($max_size)
                )
            ));
            return;
        }
        
        // Create file handler
        $file_handler = new CD_File_Handler();
        
        // Handle file based on type
        $result = null;
        
        switch ($file_ext) {
            case 'svg':
                $result = $file_handler->process_svg_file($file_tmp, $file_name);
                break;
                
            case 'png':
            case 'jpg':
            case 'jpeg':
                $result = $file_handler->process_image_file($file_tmp, $file_name);
                break;
                
            case 'ai':
                $result = $file_handler->process_ai_file($file_tmp, $file_name);
                break;
                
            default:
                wp_send_json_error(array('message' => __('Unsupported file type', 'clothing-designer')));
                return;
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Ajax extract SVG text.
     */
    public function ajax_extract_svg_text() {
        // Check nonce
        check_ajax_referer(CD_AJAX_NONCE, 'nonce');
        
        // Get SVG URL or content
        $svg_url = isset($_POST['svg_url']) ? esc_url_raw($_POST['svg_url']) : '';
        $svg_content = isset($_POST['svg_content']) ? $_POST['svg_content'] : '';
        $view_type = isset($_POST['view_type']) ? sanitize_text_field($_POST['view_type']) : 'front';

        if (empty($svg_url) && empty($svg_content)) {
            wp_send_json_error(array('message' => __('No SVG content provided', 'clothing-designer')));
            return;
        }
        
        // Get SVG content from URL if provided
        if (!empty($svg_url)) {
            $svg_content = $this->get_remote_content($svg_url);
            
            if (empty($svg_content)) {
                wp_send_json_error(array('message' => __('Failed to fetch SVG content from URL', 'clothing-designer')));
                return;
            }
        }
        
        // Validate SVG content
        if (!$this->is_valid_svg($svg_content)) {
            wp_send_json_error(array('message' => __('Invalid SVG content', 'clothing-designer')));
            return;
        }

        // Create file handler
        $file_handler = new CD_File_Handler();
        
        // Extract text elements
        $text_elements = $file_handler->extract_svg_text($svg_content);
        
        if (is_wp_error($text_elements)) {
            wp_send_json_error(array('message' => $text_elements->get_error_message()));
            return;
        }
        
        wp_send_json_success(array('text_elements' => $text_elements, 'view_type' => $view_type));
    }
    
    /**
     * Check if content is valid SVG
     *
     * @param string $content SVG content
     * @return bool Is valid
     */
    private function is_valid_svg($content) {
        // Check for SVG tag
        if (stripos($content, '<svg') === false) {
            return false;
        }
        
        // Check for potentially malicious content
        $dangerous_content = array(
            '<script', 'javascript:', 'onload=', 'onerror=', 'onclick=',
            'onmouseover=', 'onmouseout=', 'onmousedown=', 'onmouseup='
        );
        
        foreach ($dangerous_content as $needle) {
            if (stripos($content, $needle) !== false) {
                return false;
            }
        }

        if (preg_match('/<svg[^>]*>.*<\/svg>/is', $content)) {
            return true;
        }    
        
        // Try to load with SimpleXML to verify well-formed XML
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($content);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        
        // If there are errors, check if they're just warnings that don't affect rendering
        if ($doc === false && !empty($errors)) {
            // Count only errors that would affect rendering
            $critical_errors = 0;
            foreach ($errors as $error) {
                // Level 3 is LIBXML_ERR_FATAL, which definitely affects rendering
                if ($error->level === 3) {
                    $critical_errors++;
                }
            }
            
            // If no critical errors, still consider it valid for our purposes
            return $critical_errors === 0;
        }

        return $doc !== false;
    }

    /**
     * Ajax convert AI to SVG.
     */
    public function ajax_convert_ai_to_svg() {
        // Check nonce
        check_ajax_referer(CD_AJAX_NONCE, 'nonce');
        
        // Get AI file URL
        $ai_url = isset($_POST['ai_url']) ? esc_url_raw($_POST['ai_url']) : '';
        
        if (empty($ai_url)) {
            wp_send_json_error(array('message' => __('No AI file URL provided', 'clothing-designer')));
            return;
        }
        
        // Create file handler
        $file_handler = new CD_File_Handler();
        
        // Convert AI to SVG
        $result = $file_handler->convert_ai_to_svg($ai_url);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
            return;
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Ajax update SVG text.
     */
    public function ajax_update_svg_text() {
        // Check nonce
        check_ajax_referer(CD_AJAX_NONCE, 'nonce');
        
        // Get SVG content and text updates
        $svg_content = isset($_POST['svg_content']) ? $_POST['svg_content'] : '';
        $text_updates = isset($_POST['text_updates']) ? $_POST['text_updates'] : array();
        $view_type = isset($_POST['view_type']) ? sanitize_text_field($_POST['view_type']) : 'front';

        if (empty($svg_content) || empty($text_updates) || !is_array($text_updates)) {
            wp_send_json_error(array('message' => __('Invalid request parameters', 'clothing-designer')));
            return;
        }
        
        // Create file handler
        $file_handler = new CD_File_Handler();
        
        // Update SVG text
        $updated_svg = $file_handler->update_svg_text($svg_content, $text_updates);
        
        if (is_wp_error($updated_svg)) {
            wp_send_json_error(array('message' => $updated_svg->get_error_message()));
            return;
        }
        
        // Extract updated text elements
        $text_elements = $file_handler->extract_svg_text($updated_svg);
        
        if (is_wp_error($text_elements)) {
            wp_send_json_error(array('message' => $text_elements->get_error_message()));
            return;
        }
        
        wp_send_json_success(array(
            'updated_svg' => $updated_svg,
            'text_elements' => $text_elements,
            'view_type' => $view_type
        ));
    }
    
    /**
     * Get upload error message.
     *
     * @param int $error_code Error code.
     * @return string Error message.
     */
    private function get_upload_error_message($error_code) {
        switch ($error_code) {
            case UPLOAD_ERR_INI_SIZE:
                return __('The uploaded file exceeds the upload_max_filesize directive in php.ini', 'clothing-designer');
                
            case UPLOAD_ERR_FORM_SIZE:
                return __('The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form', 'clothing-designer');
                
            case UPLOAD_ERR_PARTIAL:
                return __('The uploaded file was only partially uploaded', 'clothing-designer');
                
            case UPLOAD_ERR_NO_FILE:
                return __('No file was uploaded', 'clothing-designer');
                
            case UPLOAD_ERR_NO_TMP_DIR:
                return __('Missing a temporary folder', 'clothing-designer');
                
            case UPLOAD_ERR_CANT_WRITE:
                return __('Failed to write file to disk', 'clothing-designer');
                
            case UPLOAD_ERR_EXTENSION:
                return __('A PHP extension stopped the file upload', 'clothing-designer');
                
            default:
                return __('Unknown upload error', 'clothing-designer');
        }
    }
    
    /**
     * Get remote content.
     *
     * @param string $url URL.
     * @return string Content.
     */
    private function get_remote_content($url) {
        $args = array(
            'timeout' => 30, // 30 seconds timeout
            'sslverify' => false,
        );
        
        $response = wp_remote_get($url, $args);
        
        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return '';
        }
        
        return wp_remote_retrieve_body($response);
    }

    /**
     * Save design.
     */
    public function ajax_save_design() {
        // Check nonce
        check_ajax_referer(CD_AJAX_NONCE, 'nonce');
        
        // Get design data
        $template_id = isset($_POST['template_id']) ? intval($_POST['template_id']) : 0;
        $design_data = isset($_POST['design_data']) ? $_POST['design_data'] : '';
        $preview_images = isset($_POST['preview_images']) ? $_POST['preview_images'] : '';
        $view_types = isset($_POST['view_types']) ? sanitize_text_field($_POST['view_types']) : '';

        // Validate data
        if (empty($template_id) || empty($design_data)) {
            wp_send_json_error(array('message' => __('Invalid design data', 'clothing-designer')));
            return;
        }

        // Check if template exists
        global $wpdb;
        $templates_table = $wpdb->prefix . 'cd_templates';
        $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM $templates_table WHERE id = %d", $template_id));
        
        if (!$template) {
            wp_send_json_error(array('message' => __('Template not found', 'clothing-designer')));
            return;
        }
        
        // Get user ID
        $user_id = get_current_user_id();
        
        // Check guest designs setting if user is not logged in
        if ($user_id === 0) {
            $options = get_option('cd_options', array());
            $allow_guest_designs = isset($options['allow_guest_designs']) ? $options['allow_guest_designs'] : 'yes';
            
            if ($allow_guest_designs !== 'yes') {
                wp_send_json_error(array('message' => __('You must be logged in to save designs', 'clothing-designer')));
                return;
            }
        }
        
        // Save preview image
        
        $preview_urls = array();
        if (!empty($preview_images)) {
            $images_data = json_decode($preview_images, true);
            
            if (is_array($images_data)) {
                foreach ($images_data as $view_type => $image_data) {
                    $preview_urls[$view_type] = $this->save_preview_image($image_data, $view_type);
                }
            }
        }
        
        $parsed_design_data = json_decode($design_data, true);
        // Add preview URLs to design data
        if (is_array($parsed_design_data) && isset($parsed_design_data['views'])) {
            foreach ($parsed_design_data['views'] as $view_type => &$view_data) {
                if (isset($preview_urls[$view_type])) {
                    $view_data['preview_url'] = $preview_urls[$view_type];
                }
            }
        }    
        
        // Re-encode the modified design data
        $enhanced_design_data = json_encode($parsed_design_data);

         // Save main preview URL (from current view) to the main record
        $main_preview_url = '';
        if (isset($parsed_design_data['currentView']) && 
            isset($preview_urls[$parsed_design_data['currentView']])) {
            $main_preview_url = $preview_urls[$parsed_design_data['currentView']];
        } else if (!empty($preview_urls)) {
            // Fallback to first preview if current view preview not available
            $main_preview_url = reset($preview_urls);
        }
        // Insert or update design
        $designs_table = $wpdb->prefix . 'cd_designs';
        $design_id = isset($_POST['design_id']) ? intval($_POST['design_id']) : 0;
        
        $data = array(
            'user_id' => $user_id,
            'template_id' => $template_id,
            'design_data' => $enhanced_design_data,
            'preview_url' => $main_preview_url
        );
        
        $format = array(
            '%d', // user_id
            '%d', // template_id
            '%s', // design_data
            '%s'  // preview_url
        );
        
        if ($design_id > 0) {
            // Update existing design
            $result = $wpdb->update(
                $designs_table,
                $data,
                array('id' => $design_id),
                $format,
                array('%d')
            );
        } else {
            // Insert new design
            $result = $wpdb->insert(
                $designs_table,
                $data,
                $format
            );
            
            $design_id = $wpdb->insert_id;
        }
        
        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to save design. Database error occurred.', 'clothing-designer'), 'error' => $wpdb->last_error));
            return;
        }
        
        wp_send_json_success(array(
            'message' => __('Design saved successfully', 'clothing-designer'),
            'design_id' => $design_id,
            'preview_urls' => $preview_urls,
            'views' => explode(',', $view_types),
        ));
    }

    /**
     * Save preview image.
     *
     * @param string $data_url Data URL
     * @return string URL
     */
    private function save_preview_image($data_url, $view_type = 'front') {
            // Check if data URL is valid
        if (empty($data_url) || !is_string($data_url)) {
            error_log('Clothing Designer: Invalid preview image data');
            return '';
        }

        // Extract the base64 image data
        if (!preg_match('/^data:image\/(\w+);base64,/', $data_url, $type)) {
            error_log('Clothing Designer: Invalid image data format');
            return '';
        }
    
        // Extract the base64 image data
        $data = substr($data_url, strpos($data_url, ',') + 1);
        $type = strtolower($type[1]); // jpg, png, gif
        
        if (!in_array($type, array('jpg', 'jpeg', 'gif', 'png'))) {
            error_log('Clothing Designer: Invalid image type: ' . $type);
            return '';
        }
        
        $data = base64_decode($data);
            
           
         if ($data === false) {
            error_log('Clothing Designer: Failed to decode base64 image data');
                return '';
            }
        // Create upload directory if it doesn't exist
        if (!file_exists(CD_UPLOADS_DIR)) {
            wp_mkdir_p(CD_UPLOADS_DIR);
        }
           // Check decoded data size
        $max_size = 5 * 1024 * 1024; // 5MB limit
        if (strlen($data) > $max_size) {
            error_log('Clothing Designer: Preview image too large: ' . size_format(strlen($data)));
            
            // Try to resize the image
            $resized_data = $this->resize_image_data($data);
            if (strlen($resized_data) > $max_size) {
                error_log('Clothing Designer: Failed to reduce image size sufficiently');
                return '';
            }
            $data = $resized_data;
        }

        // Create upload directory if it doesn't exist
        if (!file_exists(CD_UPLOADS_DIR)) {
            $dir_created = wp_mkdir_p(CD_UPLOADS_DIR);
            if (!$dir_created) {
                error_log('Clothing Designer: Failed to create uploads directory: ' . CD_UPLOADS_DIR);
                return '';
            }
            
            // Set proper permissions
            chmod(CD_UPLOADS_DIR, 0755);
        }
        
        // Validate directory is writable
        if (!is_writable(CD_UPLOADS_DIR)) {
            error_log('Clothing Designer: Uploads directory is not writable: ' . CD_UPLOADS_DIR);
            return '';
        }
        
        // Generate filename
        $filename = 'preview-' . time() . '-' . wp_generate_password(6, false) . '.' . $type;
        $file_path = CD_UPLOADS_DIR . $filename;
        $file_url = CD_UPLOADS_URL . $filename;
        
         // Save file
        $bytes_written = file_put_contents($file_path, $data);
        
        if ($bytes_written === false || $bytes_written === 0) {
            error_log('Clothing Designer: Failed to write file: ' . $file_path);
            return '';
        }
        
        // Verify file exists and is readable
        if (!file_exists($file_path) || !is_readable($file_path)) {
            error_log('Clothing Designer: File created but not readable: ' . $file_path);
            return '';
        }
        
        return $file_url;
    }

    /**
     * Load design.
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
        global $wpdb;
        $designs_table = $wpdb->prefix . 'cd_designs';
        $design = $wpdb->get_row($wpdb->prepare("SELECT * FROM $designs_table WHERE id = %d", $design_id));
        
        if (!$design) {
            wp_send_json_error(array('message' => __('Design not found', 'clothing-designer')));
            return;
        }
        
        // Check user permission
        $user_id = get_current_user_id();
        
        if ($user_id !== 0 && $design->user_id !== 0 && $design->user_id !== $user_id && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to view this design', 'clothing-designer')));
            return;
        }
        
        wp_send_json_success(array('design' => $design));
    }

    /**
     * Save design element.
     */
    public function ajax_save_design_element() {
        // Check nonce
        check_ajax_referer(CD_AJAX_NONCE, 'nonce');
        
        // Get parameters
        $design_id = isset($_POST['design_id']) ? intval($_POST['design_id']) : 0;
        $element_id = isset($_POST['element_id']) ? sanitize_text_field($_POST['element_id']) : '';
        $svg_content = isset($_POST['svg_content']) ? wp_unslash($_POST['svg_content']) : '';
        $view_type = isset($_POST['view_type']) ? sanitize_text_field($_POST['view_type']) : 'front';
        
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
        if (!$design_data) {
            wp_send_json_error();
            return;
        }
        
        // Check if we're using the new format with views
        if (isset($design_data['views']) && is_array($design_data['views'])) {
            // Make sure the view exists
            if (!isset($design_data['views'][$view_type])) {
                $design_data['views'][$view_type] = array('elements' => array());
            }
            
            // Make sure elements array exists
            if (!isset($design_data['views'][$view_type]['elements'])) {
                $design_data['views'][$view_type]['elements'] = array();
            }
            
            // Update element with SVG content
            $element_updated = false;
            foreach ($design_data['views'][$view_type]['elements'] as &$element) {
                if (isset($element['id']) && $element['id'] === $element_id) {
                    $element['svg_content'] = $svg_content;
                    $element_updated = true;
                    break;
                }
            }
        } 
        // Legacy format without views
        else if (isset($design_data['elements']) && is_array($design_data['elements'])) {
            // Update element with SVG content
            $element_updated = false;
            foreach ($design_data['elements'] as &$element) {
                if (isset($element['id']) && $element['id'] === $element_id) {
                    $element['svg_content'] = $svg_content;
                    $element_updated = true;
                    break;
                }
            }
        } else {
            wp_send_json_error();
            return;
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
     * View design.
     */
    public function ajax_view_design() {
        // Check nonce
        check_ajax_referer('cd-view-design', 'nonce');
        
        // Get design ID
        $design_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($design_id <= 0) {
            wp_die(__('Invalid design ID', 'clothing-designer'));
            return;
        }
        
        // Get design
        global $wpdb;
        $designs_table = $wpdb->prefix . 'cd_designs';
        $templates_table = $wpdb->prefix . 'cd_templates';
        
        $design = $wpdb->get_row($wpdb->prepare("
            SELECT d.*, t.title as template_title 
            FROM $designs_table d
            LEFT JOIN $templates_table t ON d.template_id = t.id
            WHERE d.id = %d
        ", $design_id));
        
        if (!$design) {
            wp_die(__('Design not found', 'clothing-designer'));
            return;
        }
        
        // Check user permission
        $user_id = get_current_user_id();
        
        if ($user_id !== 0 && $design->user_id !== 0 && $design->user_id !== $user_id && !current_user_can('manage_options')) {
            wp_die(__('You do not have permission to view this design', 'clothing-designer'));
            return;
        }
        
        // Output design view
        include(CD_PLUGIN_DIR . 'templates/design-view.php');
        exit;
    }
}