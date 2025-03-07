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
            return $imagick->writeImage($svg_path);
        } catch (Exception $e) {
            return false;
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
     * Sanitize SVG content.
     *
     * @param string $svg_content SVG content.
     * @return string Sanitized SVG content.
     */
    public function sanitize_svg($svg_content) {

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
                // Replace with harmless SVG if suspicious content detected
                return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><text x="10" y="50" font-family="Arial">Sanitized SVG</text></svg>';
            }
        }

        // Create a new DOMDocument
        $dom = new DOMDocument();
        
        // Disable entity loading to prevent XXE attacks
        $old_value = false;
        if (function_exists('libxml_disable_entity_loader')) {
            $old_value = libxml_disable_entity_loader(true);
        }

        // Suppress warnings from malformed SVG
        $use_errors = libxml_use_internal_errors(true);
        
        // Load SVG as XML
        $success = $dom->loadXML($svg_content);
        
        // Restore previous entity loader setting
        if (function_exists('libxml_disable_entity_loader')) {
            libxml_disable_entity_loader($old_value);
        }

        // Get any errors
        $errors = libxml_get_errors();
        libxml_clear_errors();
        
        // Restore previous error handling setting
        libxml_use_internal_errors($use_errors);
        
        // If loading failed, try as HTML
        if (!$success) {
            // Attempt to load as HTML (some SVGs might be invalid XML but valid HTML)
            $dom = new DOMDocument();
            $success = $dom->loadHTML('<div>' . $svg_content . '</div>');
            
            // If still fails, return a minimal valid SVG
            if (!$success) {
                return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"></svg>';
            }
            
            // Find the SVG element if it exists
            $svg_element = $dom->getElementsByTagName('svg')->item(0);
            
            if (!$svg_element) {
                return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"></svg>';
            }
        }
        
        // Remove potentially harmful elements and attributes
        $this->remove_svg_harmful_elements($dom);
        
        // Save clean SVG
        return $dom->saveXML($dom->documentElement);
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
            'color-profile'
        );
        
        // Add more potentially harmful attributes
        $harmful_attributes = array(
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
            'formaction',
            'href',
            'xlink:href',
            'action',
            'data',
            'from',
            'to',
            'values',
            'by',
            'attributeName',
            'begin',
            'dur',
            'requiredFeatures',
            'requiredExtensions'
        );
            
        // Remove harmful elements
        foreach ($harmful_elements as $tag_name) {
            $elements = $dom->getElementsByTagName($tag_name);
            
            // Need to loop backwards as removing nodes changes the live NodeList
            for ($i = $elements->length - 1; $i >= 0; $i--) {
                $element = $elements->item($i);
                
                if ($element) {
                    $element->parentNode->removeChild($element);
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
                }
            }
            
            // Check for javascript in style attributes
            if ($element->hasAttribute('style')) {
                $style = $element->getAttribute('style');
                
                // Remove javascript from style
                if (preg_match('/expression|javascript|behavior/i', $style)) {
                    $element->removeAttribute('style');
                }
            }
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
        
        // Get text elements
        $text_elements = $svg->getElementsByTagName('text');
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
            
            if ($element) {
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
        return [$return_var === 0, $output_text, $output_text];
    }
}