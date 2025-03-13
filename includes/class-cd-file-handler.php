<?php
/**
 * File Handler class
 *
 * @package Clothing_Designer
 */

if (!defined('ABSPATH')) {
    exit;
}

class CD_File_Handler {
    
    /**
     * Process SVG file.
     *
     * @param string $file_tmp Temporary file path.
     * @param string $file_name File name.
     * @return array|WP_Error Result data or error.
     */
    public function process_svg_file($file_tmp, $file_name) {
        // Read SVG content
        $svg_content = file_get_contents($file_tmp);
        
        if ($svg_content === false) {
            return new WP_Error('read_error', __('Failed to read SVG file', 'clothing-designer'));
        }
        
        // Sanitize SVG content
        $svg_content = $this->sanitize_svg($svg_content);
        
        // Generate unique filename
        $new_filename = uniqid() . '-' . $file_name;
        $file_path = CD_UPLOADS_DIR . $new_filename;
        
        // Save sanitized SVG
        if (file_put_contents($file_path, $svg_content) === false) {
            return new WP_Error('write_error', __('Failed to save SVG file', 'clothing-designer'));
        }
        
        // Extract SVG metadata
        $svg_info = $this->extract_svg_metadata($svg_content);
        
        // Extract text elements
        $text_elements = $this->extract_svg_text($svg_content);
        
        return array(
            'file_url' => CD_UPLOADS_URL . $new_filename,
            'file_name' => $file_name,
            'file_type' => 'svg',
            'content' => $svg_content,
            'width' => $svg_info['width'],
            'height' => $svg_info['height'],
            'viewBox' => $svg_info['viewBox'],
            'text_elements' => $text_elements,
            'editable' => !empty($text_elements)
        );
    }
    
    /**
     * Process image file.
     *
     * @param string $file_tmp Temporary file path.
     * @param string $file_name File name.
     * @return array|WP_Error Result data or error.
     */
    public function process_image_file($file_tmp, $file_name) {
        // Generate unique filename
        $new_filename = uniqid() . '-' . $file_name;
        $file_path = CD_UPLOADS_DIR . $new_filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file_tmp, $file_path)) {
            return new WP_Error('move_error', __('Failed to move uploaded file', 'clothing-designer'));
        }
        
        // Get image dimensions
        $image_size = getimagesize($file_path);
        
        if ($image_size === false) {
            return new WP_Error('image_error', __('Failed to get image dimensions', 'clothing-designer'));
        }
        
        return array(
            'file_url' => CD_UPLOADS_URL . $new_filename,
            'file_name' => $file_name,
            'file_type' => pathinfo($file_name, PATHINFO_EXTENSION),
            'width' => $image_size[0],
            'height' => $image_size[1],
            'mime_type' => $image_size['mime']
        );
    }
    
    /**
     * Process AI file.
     *
     * @param string $file_tmp Temporary file path.
     * @param string $file_name File name.
     * @return array|WP_Error Result data or error.
     */
    public function process_ai_file($file_tmp, $file_name) {
        // Generate unique filename
        $new_filename = uniqid() . '-' . $file_name;
        $file_path = CD_UPLOADS_DIR . $new_filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file_tmp, $file_path)) {
            return new WP_Error('move_error', __('Failed to move uploaded file', 'clothing-designer'));
        }
        
        // Try to convert AI to SVG
        $conversion_result = $this->convert_ai_to_svg($file_path);
        
        // If conversion failed, return the original AI file info
        if (is_wp_error($conversion_result)) {
            return array(
                'file_url' => CD_UPLOADS_URL . $new_filename,
                'file_name' => $file_name,
                'file_type' => 'ai',
                'conversion_error' => $conversion_result->get_error_message(),
                'message' => __('AI file uploaded successfully, but automatic conversion to SVG failed. You can still use this file as a template, but editing text within it will not be possible.', 'clothing-designer')
            );
        }
        
        return $conversion_result;
    }
    
    /**
     * Convert AI to SVG.
     *
     * @param string $ai_path Path to AI file.
     * @return array|WP_Error Result data or error.
     */
    public function convert_ai_to_svg($ai_path) {
        // Check if file exists
        if (!file_exists($ai_path)) {
            return new WP_Error('file_not_found', __('AI file not found', 'clothing-designer'));
        }
        
        // Get filename without extension
        $filename = pathinfo($ai_path, PATHINFO_FILENAME);
        $svg_filename = $filename . '.svg';
        $svg_path = CD_UPLOADS_DIR . $svg_filename;
        
        // Check for server-side conversion tools
        $conversion_method = $this->get_ai_conversion_method();
        
        if ($conversion_method === 'none') {
            return new WP_Error('no_conversion', __('No AI to SVG conversion method available on server. Please install Inkscape, ImageMagick with SVG support, or pdf2svg.', 'clothing-designer'));
        }
        
        // Convert using the available method
        $conversion_success = false;
        $error_message = '';
        
        switch ($conversion_method) {
            case 'cloudmersive':
                list($conversion_success, $error_message) = $this->convert_ai_with_cloudmersive($ai_path, $svg_path);
                break;

            case 'inkscape':
                list($conversion_success, $error_message) = $this->convert_ai_with_inkscape($ai_path, $svg_path);
                break;
                
            case 'imagick':
                list($conversion_success, $error_message) = $this->convert_ai_with_imagick($ai_path, $svg_path);
                break;
                
            case 'pdf2svg':
                list($conversion_success, $error_message) = $this->convert_ai_with_pdf2svg($ai_path, $svg_path);
                break;
        }
        
        if (!$conversion_success) {
            return new WP_Error(
                'conversion_failed', 
                sprintf(__('Failed to convert AI to SVG using %s: %s', 'clothing-designer'), 
                    $conversion_method, 
                    $error_message
                )
            );
        }
        
    
        // Process the converted SVG file
        $svg_content = file_get_contents($svg_path);
        
        if ($svg_content === false) {
            return new WP_Error('read_error', __('Failed to read converted SVG file', 'clothing-designer'));
        }
        
        // Extract SVG metadata
        $svg_info = $this->extract_svg_metadata($svg_content);
        
        // Extract text elements
        $text_elements = $this->extract_svg_text($svg_content);
        
        return array(
            'file_url' => CD_UPLOADS_URL . $svg_filename,
            'file_name' => $svg_filename,
            'file_type' => 'svg',
            'content' => $svg_content,
            'width' => $svg_info['width'],
            'height' => $svg_info['height'],
            'viewBox' => $svg_info['viewBox'],
            'text_elements' => $text_elements,
            'converted_from' => 'ai',
            'original_file' => basename($ai_path)
        );
    }
    
    /**
     * Get AI conversion method available on server.
     *
     * @return string Method name or 'none'.
     */
    private function get_ai_conversion_method() {
        // Check if Cloudmersive should be used first
        $options = get_option('cd_options', array());
        $use_cloudmersive = isset($options['use_cloudmersive']) ? $options['use_cloudmersive'] : 'yes';
        
        if ($use_cloudmersive === 'yes') {
            $api_key = $this->get_cloudmersive_api_key();
            
            if (!empty($api_key)) {
                return 'cloudmersive';
            }
        }
    
        // Check for Inkscape
        if ($this->is_program_available('inkscape')) {
            return 'inkscape';
        }
        
        // Check for ImageMagick with SVG support
        if (extension_loaded('imagick')) {
            $imagick = new Imagick();
            $formats = $imagick->queryFormats();
            if (in_array('SVG', $formats) && in_array('AI', $formats)) {
                return 'imagick';
            }
        }
        
        // Check for pdf2svg
        if ($this->is_program_available('pdf2svg')) {
            return 'pdf2svg';
        }
        
        // If Cloudmersive wasn't prioritized, try it as a last resort
        if ($use_cloudmersive !== 'yes') {
            $api_key = $this->get_cloudmersive_api_key();
            
            if (!empty($api_key)) {
                return 'cloudmersive';
            }
        }
        return 'none';
    }
    
    /**
     * Check if a program is available on the server.
     *
     * @param string $program Program name.
     * @return bool True if available, false otherwise.
     */
    private function is_program_available($program) {
        if (!function_exists('exec')) {
            return false;
        }
        
        @exec("command -v $program 2>&1", $output, $return_var);
        return $return_var === 0;
    }
    
    /**
     * Convert AI to SVG using Inkscape.
     *
     * @param string $ai_path Path to AI file.
     * @param string $svg_path Output SVG path.
     * @return bool True on success, false on failure.
     */
    private function convert_ai_with_inkscape($ai_path, $svg_path) {
        $result = $this->execute_command('inkscape', ['--without-gui', '--file='.$ai_path, '--export-plain-svg='.$svg_path]);
        return [$result[0] && file_exists($svg_path), $result[2]];
    }
        
    /**
     * Convert AI to SVG using ImageMagick.
     *
     * @param string $ai_path Path to AI file.
     * @param string $svg_path Output SVG path.
     * @return bool True on success, false on failure.
     */
    private function convert_ai_with_imagick($ai_path, $svg_path) {
        try {
            $imagick = new Imagick();
            $imagick->readImage($ai_path);
            $imagick->setImageFormat('svg');
            $success = $imagick->writeImage($svg_path);
            return [$success, $success ? '' : 'Failed to write SVG image'];
        } catch (Exception $e) {
            return [false, $e->getMessage()];
        }
    }
    
    /**
     * Convert AI to SVG using pdf2svg.
     *
     * @param string $ai_path Path to AI file.
     * @param string $svg_path Output SVG path.
     * @return bool True on success, false on failure.
     */
    
    private function convert_ai_with_pdf2svg($ai_path, $svg_path) {
        $result = $this->execute_command('pdf2svg', [$ai_path, $svg_path]);
        return [$result[0] && file_exists($svg_path), $result[2]];
    }

    /**
     * Convert AI to SVG using Cloudmersive API.
     *
     * @param string $ai_path Path to AI file.
     * @param string $svg_path Output SVG path.
     * @return array [success (bool), error message (string)]
     */
    private function convert_ai_with_cloudmersive($ai_path, $svg_path) {
        $api_key = $this->get_cloudmersive_api_key();
        
        if (empty($api_key)) {
            return array(false, 'Cloudmersive API key not configured');
        }
        
        if (!file_exists($ai_path)) {
            return array(false, 'AI file not found');
        }
        
        // Prepare file for upload
        $file_data = file_get_contents($ai_path);
        
        if ($file_data === false) {
            return array(false, 'Failed to read AI file');
        }
        
        // Generate random boundary for multipart data
        $boundary = wp_generate_password(24, false);
        
        // Build request body
        $data = '';
        $data .= "--$boundary\r\n";
        $data .= "Content-Disposition: form-data; name=\"inputFile\"; filename=\"" . basename($ai_path) . "\"\r\n";
        $data .= "Content-Type: application/postscript\r\n\r\n";
        $data .= $file_data . "\r\n";
        $data .= "--$boundary--\r\n";
        
        // Send API request
        $response = wp_remote_post('https://api.cloudmersive.com/convert/ai/to/svg', array(
            'method' => 'POST',
            'timeout' => 60,
            'headers' => array(
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
                'Apikey' => $api_key
            ),
            'body' => $data,
            'sslverify' => true
        ));
        
        if (is_wp_error($response)) {
            return array(false, 'API request failed: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            return array(false, 'API request failed with status code: ' . $status_code);
        }
        
        $svg_content = wp_remote_retrieve_body($response);
        
        if (empty($svg_content)) {
            return array(false, 'Empty response from API');
        }
        
        // Validate SVG content
        if (strpos($svg_content, '<svg') === false) {
            return array(false, 'Invalid SVG content received from API');
        }
        
        // Save SVG file
        $result = file_put_contents($svg_path, $svg_content);
        
        if ($result === false) {
            return array(false, 'Failed to save SVG file');
        }
        
        return array(true, '');
    }

    /**
     * Get Cloudmersive API key.
     *
     * @return string API key or empty string.
     */
    private function get_cloudmersive_api_key() {    
        // Get options
        $options = get_option('cd_options', array());
        $override_env_api_key = isset($options['override_env_api_key']) ? $options['override_env_api_key'] : 'no';
        $admin_api_key = isset($options['cloudmersive_api_key']) ? $options['cloudmersive_api_key'] : '';

        // If override is enabled and we have an admin-set key, use that
        if ($override_env_api_key === 'yes' && !empty($admin_api_key)) {
            return $admin_api_key;
        }
        
        // Check environment variable first if class exists
        if (class_exists('CD_Env_Loader')) {
            $env_api_key = CD_Env_Loader::get('CLOUDMERSIVE_API_KEY');
            
            if (!empty($env_api_key)) {
                return $env_api_key;
            }
        }
        
        // Check options
        $options = get_option('cd_options', array());
        return isset($options['cloudmersive_api_key']) ? $options['cloudmersive_api_key'] : '';
    }

    /**
     * Sanitize SVG content.
     *
     * @param string $svg_content SVG content.
     * @return string Sanitized SVG content.
     */
    public function sanitize_svg($svg_content) {
        $malicious_detected = false;
        // Check for null or empty SVG content
        if ($svg_content === null || trim($svg_content) === '') {
            error_log('Clothing Designer: Empty SVG content provided for sanitization');
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"></svg>';
        }
        $svg_content = preg_replace('/(<svg[^>]*)(\s+\w+)=(\w+)(\s|>)/i', '$1$2="$3"$4', $svg_content);

        // Check for potentially malicious strings before parsing
        $suspicious_patterns = [
            '/javascript:/i',
            '/eval\s*\(/i',
            '/expression\s*\(/i',
            '/<script[^>]*>/i',
            '/data:(?!image\/svg\+xml)[^;]*;/i',
            '/base64/i',
            '/&#[x0-9a-f]+;/i'  // Hex entities which might be used for obfuscation
        ];
        
        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $svg_content)) {
                $malicious_detected = true;
                // Replace with harmless SVG if suspicious content detected 
                error_log('Clothing Designer: Potentially malicious pattern detected in SVG: ' . $pattern);
                break;
            }
        }
        
        if ($malicious_detected) {
            // Return safe fallback SVG with warning
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">
                <text x="10" y="50" font-family="Arial" font-size="12">Sanitized SVG</text>
            </svg>';
        }
        
        // If content doesn't contain SVG tag at all, reject it
        if (stripos($svg_content, '<svg') === false) {
            error_log('Clothing Designer: Content does not contain SVG tag');
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"></svg>';
        }

        // Create a new DOMDocument
        $dom = new DOMDocument();
        
        // Disable entity loading to prevent XXE attacks
        $options = LIBXML_NONET; 

        // Suppress warnings from malformed SVG
        $use_errors = libxml_use_internal_errors(true);
        
           // Suppress libxml errors
        $use_errors = libxml_use_internal_errors(true);
        
        // Try loading as XML first
        $success = false;
        try {
            $success = $dom->loadXML($svg_content, $options);
        } catch (Exception $e) {
            error_log('Clothing Designer: Exception when loading SVG: ' . $e->getMessage());
        }

        // Get any errors
        $errors = libxml_get_errors();
        libxml_clear_errors();
        
        // Restore previous error handling setting
        libxml_use_internal_errors($use_errors);
        
        // Restore previous entity loader setting
        // If loading failed, try as HTML
        if (!$success) {
            error_log('Clothing Designer: Failed to load SVG as XML. Trying as HTML');
        
            // Log specific errors
            foreach ($errors as $error) {
                error_log('Clothing Designer: XML Error: ' . $error->message);
            }

            // Attempt to load as HTML (some SVGs might be invalid XML but valid HTML)
            $dom = new DOMDocument();
            try {
                $success = $dom->loadHTML('<div>' . $svg_content . '</div>', $options);
            } catch (Exception $e) {
                error_log('Clothing Designer: Exception when loading SVG as HTML: ' . $e->getMessage());
            }
            
            // If still fails, return a minimal valid SVG
            if (!$success) {
                error_log('Clothing Designer: Failed to load SVG as HTML. Returning fallback SVG');
                return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"></svg>';
            }
            
            // Find the SVG element if it exists
            $svg_element = $dom->getElementsByTagName('svg')->item(0);
            
            if (!$svg_element) {
                error_log('Clothing Designer: No SVG element found in HTML');
                return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"></svg>';
            }
        }

        // Get SVG element (for XML parsing)
        $svg_element = $dom->getElementsByTagName('svg')->item(0);

        if (!$svg_element) {
            error_log('Clothing Designer: No SVG element found after parsing');
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"></svg>';
        }

        // Ensure SVG has viewBox
        if (!$svg_element->hasAttribute('viewBox')) {
            // Try to construct viewBox from width/height
            $width = $svg_element->hasAttribute('width') ? $svg_element->getAttribute('width') : '100';
            $height = $svg_element->hasAttribute('height') ? $svg_element->getAttribute('height') : '100';
            $svg_element->setAttribute('viewBox', "0 0 $width $height");
        }

        // Remove potentially harmful elements and attributes
        $this->remove_svg_harmful_elements($dom);
        
        // Save clean SVG
        try {
            $clean_svg = $dom->saveXML($svg_element);
            return $clean_svg;
        } catch (Exception $e) {
            error_log('Clothing Designer: Exception when saving SVG: ' . $e->getMessage());
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"></svg>';
        }
    }
    
    /**
     * Remove harmful elements and attributes from SVG.
     *
     * @param DOMDocument $dom DOM document.
     */
    private function remove_svg_harmful_elements($dom) {
        // Add more potentially harmful elements
        $harmful_elements = array(
            'script',
            'foreignObject',
            'animate',
            'set',
            'use',
            'iframe',
            'embed',
            'object',
            'handler',
            'listener',
            'annotation-xml',
            'color-profile',
            'a',
            'audio',
            'video',
            'html',
            'body',
            'head',
            'meta',
            'link',
            'style'    
        );
        
        // Add more potentially harmful attributes
        $harmful_attributes = array(
            // Event handlers
            'onload',
            'onclick',
            'onunload',
            'onabort',
            'onerror',
            'onresize',
            'onscroll',
            'onzoom',
            'onactivate',
            'onbegin',
            'onchange',
            'onfocusin',
            'onfocusout',
            'onend',
            'onmousedown',
            'onmousemove',
            'onmouseout',
            'onmouseover',
            'onmouseup',
            'ontouchstart',
            'ontouchmove',
            'ontouchend',
            'ontouchcancel',
            
            // Navigation/form attributes
            'formaction',
            'href',
            'xlink:href',
            'action',
            
            // Animation and content loading attributes
            'data',
            'from',
            'to',
            'values',
            'by',
            'attributeName',
            'begin',
            'dur',
            
            // External content attributes
            'requiredFeatures',
            'requiredExtensions',
            'systemLanguage',
            'externalResourcesRequired'
        );
        // Track statistics for logging
        $removed_elements = 0;
        $removed_attributes = 0;

        // Remove harmful elements
        foreach ($harmful_elements as $tag_name) {
            $elements = $dom->getElementsByTagName($tag_name);
            
            // Need to loop backwards as removing nodes changes the live NodeList
            $elements_to_remove = array();
            for ($i = 0; $i < $elements->length; $i++) {
                $elements_to_remove[] = $elements->item($i);
            }
            
            // Now remove the elements
            foreach ($elements_to_remove as $element) {
                if ($element && $element->parentNode) {
                    $element->parentNode->removeChild($element);
                    $removed_elements++;
                }
            }
        }
        
        // Remove harmful attributes from all elements
        $all_elements = $dom->getElementsByTagName('*');
        
        for ($i = 0; $i < $all_elements->length; $i++) {
            $element = $all_elements->item($i);
            
            foreach ($harmful_attributes as $attr_name) {
                if ($element->hasAttribute($attr_name)) {
                    $element->removeAttribute($attr_name);
                    $removed_attributes++;
                }
            }
            
            // Check for javascript in style attributes
            if ($element->hasAttribute('style')) {
                $style = $element->getAttribute('style');
                $banned_css_patterns = array(
                    '/expression\s*\(/i',
                    '/url\s*\(/i',      // Can be used for data URLs or external resources
                    '/behavior\s*:/i',  // IE-specific
                    '/javascript\s*:/i',
                    '/@import\s/i',     // Could import external CSS
                    '/position\s*:\s*fixed/i'  // Could create overlay that captures clicks
                );
                // Remove javascript from style
                 
                $has_harmful_css = false;
                foreach ($banned_css_patterns as $pattern) {
                    if (preg_match($pattern, $style)) {
                        $has_harmful_css = true;
                        break;
                    }
                }
                
                if ($has_harmful_css) {
                    $element->removeAttribute('style');
                    $removed_attributes++;
                }
            }
        }

        // Log statistics if anything was removed
        if ($removed_elements > 0 || $removed_attributes > 0) {
            error_log("Clothing Designer: Sanitized SVG - removed $removed_elements elements and $removed_attributes attributes");
        }
    }
    
    /**
     * Extract SVG metadata.
     *
     * @param string $svg_content SVG content.
     * @return array SVG metadata.
     */
    public function extract_svg_metadata($svg_content) {
        // Create a new DOMDocument
        $dom = new DOMDocument();
        
        // Suppress warnings from malformed SVG
        $use_errors = libxml_use_internal_errors(true);
        
        // Load SVG
        $dom->loadXML($svg_content);
        
        // Restore previous error handling setting
        libxml_clear_errors();
        libxml_use_internal_errors($use_errors);
        
        // Get SVG element
        $svg = $dom->getElementsByTagName('svg')->item(0);
        
        $width = 0;
        $height = 0;
        $viewBox = '';
        
        if ($svg) {
            // Get width and height attributes
            $width_attr = $svg->getAttribute('width');
            $height_attr = $svg->getAttribute('height');
            
            // Get viewBox attribute
            $viewBox = $svg->getAttribute('viewBox');
            
            // Parse width and height values
            $width = $this->parse_svg_dimension($width_attr);
            $height = $this->parse_svg_dimension($height_attr);
            
            // If no width/height but has viewBox, use viewBox dimensions
            if (($width == 0 || $height == 0) && !empty($viewBox)) {
                $viewBox_parts = preg_split('/[\s,]+/', trim($viewBox));
                
                if (count($viewBox_parts) === 4) {
                    if ($width == 0) {
                        $width = floatval($viewBox_parts[2]);
                    }
                    
                    if ($height == 0) {
                        $height = floatval($viewBox_parts[3]);
                    }
                }
            }
            
            // Ensure we have a viewBox
            if (empty($viewBox) && $width > 0 && $height > 0) {
                $viewBox = "0 0 $width $height";
            }
        }
        
        return array(
            'width' => $width > 0 ? $width : 100,
            'height' => $height > 0 ? $height : 100,
            'viewBox' => !empty($viewBox) ? $viewBox : '0 0 100 100'
        );
    }
    
    /**
     * Parse SVG dimension value.
     *
     * @param string $value Dimension value.
     * @return float Parsed value in pixels.
     */
    private function parse_svg_dimension($value) {
        if (empty($value)) {
            return 0;
        }
        
        // If value is already a number, return it
        if (is_numeric($value)) {
            return floatval($value);
        }
        
        // Extract numeric value and unit
        if (preg_match('/^([0-9.]+)([a-z%]*)$/i', trim($value), $matches)) {
            $numeric = floatval($matches[1]);
            $unit = isset($matches[2]) ? strtolower($matches[2]) : '';
            
            // Convert to pixels based on unit
            switch ($unit) {
                case 'px':
                    return $numeric;
                    
                case 'pt':
                    return $numeric * 1.33;
                    
                case 'pc':
                    return $numeric * 16;
                    
                case 'mm':
                    return $numeric * 3.78;
                    
                case 'cm':
                    return $numeric * 37.8;
                    
                case 'in':
                    return $numeric * 96;
                    
                case '%':
                    // Can't really convert % without context
                    return $numeric;
                    
                default:
                    return $numeric;
            }
        }
        
        return 0;
    }
    
    /**
     * Extract text elements from SVG.
     *
     * @param string $svg_content SVG content.
     * @return array|WP_Error Array of text elements or error.
     */
    public function extract_svg_text($svg_content) {
        // Early validation
        if (empty($svg_content)) {
            return new WP_Error('empty_svg', __('Empty SVG content provided', 'clothing-designer'));
        }

        // Create a new DOMDocument
        $dom = new DOMDocument();
        
        // Suppress warnings from malformed SVG
        $use_errors = libxml_use_internal_errors(true);
        
        // Load SVG
        $dom->loadXML($svg_content);
        
        // Restore previous error handling setting
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($use_errors);
        
        // Check for parsing errors
        if (!empty($errors)) {
            // Log errors for debugging but try to continue
            error_log('SVG parsing errors: ' . print_r($errors, true));
        }
        
        // Get SVG element
        $svg = $dom->getElementsByTagName('svg')->item(0);
        
        if (!$svg) {
            return new WP_Error('invalid_svg', __('Invalid SVG: Root SVG element not found', 'clothing-designer'));
        } 
        
        // Create XPath for handling namespaces properly
        $xpath = new DOMXPath($dom);

        // Register any namespaces
        $namespaces = array();
        foreach ($svg->attributes as $attr) {
            if (strpos($attr->nodeName, 'xmlns:') === 0) {
                $prefix = substr($attr->nodeName, 6);
                $namespaces[$prefix] = $attr->nodeValue;
                $xpath->registerNamespace($prefix, $attr->nodeValue);
            }
        }
        
        // Register default SVG namespace
        $xpath->registerNamespace('svg', 'http://www.w3.org/2000/svg');
        
        // Get text elements
        $text_elements = $xpath->query('//svg:text | //text');
        $result = array();
        
        foreach ($text_elements as $index => $text) {
            // Get or create ID for the text element
            $id = $text->getAttribute('id');
            
            if (empty($id)) {
                $id = 'text-' . $index;
                $text->setAttribute('id', $id);
            }
            
            // Get text content
            $content = $text->textContent;
            
            // Get position
            $x = $text->getAttribute('x') ?: 0;
            $y = $text->getAttribute('y') ?: 0;
            
            // Get style attributes
            $font_family = $text->getAttribute('font-family') ?: '';
            $font_size = $text->getAttribute('font-size') ?: '';
            $fill = $text->getAttribute('fill') ?: '#000000';
            
            // Get style attribute and parse it
            $style_attr = $text->getAttribute('style');
            $style = array();
            
            if (!empty($style_attr)) {
                $style_parts = explode(';', $style_attr);
                
                foreach ($style_parts as $style_part) {
                    $style_part = trim($style_part);
                    
                    if (empty($style_part)) {
                        continue;
                    }
                    
                    $style_kv = explode(':', $style_part, 2);
                    
                    if (count($style_kv) === 2) {
                        $key = trim($style_kv[0]);
                        $value = trim($style_kv[1]); 
                        $style[$key] = $value;
                        
                        // Check for specific style properties
                        if ($key === 'font-family') {
                            $font_family = $value;
                        } elseif ($key === 'font-size') {
                            $font_size = $value;
                        } elseif ($key === 'fill') {
                            $fill = $value;
                        }
                    }
                }
            }
            
            $result[] = array(
                'id' => $id,
                'content' => $content,
                'x' => $x,
                'y' => $y,
                'font_family' => $font_family,
                'font_size' => $font_size,
                'fill' => $fill,
                'style' => $style
            );
        }
        
        return $result;
    }
    
    /**
     * Update text content in SVG.
     *
     * @param string $svg_content SVG content.
     * @param array $text_updates Array of text updates (id => new_content).
     * @return string|WP_Error Updated SVG content or error.
     */
    public function update_svg_text($svg_content, $text_updates) {
        if (empty($text_updates) || !is_array($text_updates)) {
            return new WP_Error('invalid_updates', __('Invalid text updates', 'clothing-designer'));
        }

        
        // Create a new DOMDocument
        $dom = new DOMDocument();
        
        // Suppress warnings from malformed SVG
        $use_errors = libxml_use_internal_errors(true);
        
        // Load SVG
        $dom->loadXML($svg_content);
        
        // Restore previous error handling setting
        libxml_clear_errors();
        libxml_use_internal_errors($use_errors);
        
        // Get SVG element
        $svg = $dom->getElementsByTagName('svg')->item(0);
        
        if (!$svg) {
            return new WP_Error('invalid_svg', __('Invalid SVG: Root SVG element not found', 'clothing-designer'));
        }
        
        $updated = false;
        
        foreach ($text_updates as $id => $new_content) {
            // Get text element by ID
            $element = $dom->getElementById($id);
            
            if (!$element) {
                // If element not found by ID, try to find by index (for older SVGs without IDs)
                if (is_numeric($id)) {
                    $index = intval($id);
                    $text_elements = $svg->getElementsByTagName('text');
                    
                    if ($index >= 0 && $index < $text_elements->length) {
                        $element = $text_elements->item($index);
                    }
                }
            }

            if ($element && $element->nodeName === 'text') {
                // Sanitize the new content
                $new_content = sanitize_text_field($new_content);
                
                // Update text content
                while ($element->firstChild) {
                    $element->removeChild($element->firstChild);
                }
                
                $element->appendChild($dom->createTextNode($new_content));
                $updated = true;
            }
        }
        
        if (!$updated) {
            return new WP_Error('no_updates', __('No text elements were updated', 'clothing-designer'));
        }
        
        return $dom->saveXML($dom->documentElement);
    }

    /**
     * Execute system command with proper platform-specific escaping
     * 
     * @param string $command The command to execute
     * @param array $args Arguments to be escaped and added to the command
     * @return array [success (bool), output (string), error message (string)]
     */
    private function execute_command($command, $args = []) {
        if (!function_exists('exec')) {
            return [false, '', 'PHP exec function is disabled'];
        }
        
        // Prepare full command with escaped arguments
        $full_command = $command;
        
        foreach ($args as $arg) {
            if (defined('PHP_WINDOWS_VERSION_BUILD')) {
                // Windows escaping: double quotes around argument with internal double quotes doubled
                $escaped = '"' . str_replace('"', '""', $arg) . '"';
            } else {
                // Unix escaping
                $escaped = escapeshellarg($arg);
            }
            $full_command .= ' ' . $escaped;
        }
        
        $output = [];
        $return_var = 0;
        @exec($full_command . " 2>&1", $output, $return_var);
        
        $output_text = implode("\n", $output);
        $error_message = $return_var !== 0 ? 'Command failed with status ' . $return_var . ': ' . $output_text : '';

        return [$return_var === 0, $output_text, $error_message];
    }
}