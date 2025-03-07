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
        
    }
    
    /**
     * Ajax upload file.
     */
    public function ajax_upload_file() {
        // Check nonce
        check_ajax_referer(CD_AJAX_NONCE, 'nonce');
        
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
        
        wp_send_json_success(array('text_elements' => $text_elements));
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
        
        // Try to load with SimpleXML to verify well-formed XML
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($content);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        
        return $doc !== false && empty($errors);
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
        check_ajax_referer('cd-ajax-nonce', 'nonce');
        
        // Get SVG content and text updates
        $svg_content = isset($_POST['svg_content']) ? $_POST['svg_content'] : '';
        $text_updates = isset($_POST['text_updates']) ? $_POST['text_updates'] : array();
        
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
            'text_elements' => $text_elements
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
}