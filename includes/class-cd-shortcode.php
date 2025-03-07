<?php
/**
 * Shortcode class
 *
 * @package Clothing_Designer
 */

if (!defined('ABSPATH')) {
    exit;
}

class CD_Shortcode {
    
    /**
     * Instance.
     *
     * @var CD_Shortcode
     */
    private static $instance = null;
    
    /**
     * Get instance.
     *
     * @return CD_Shortcode
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
        // Register shortcode
        add_shortcode('clothing_designer', array($this, 'render_shortcode'));
    }
    
    /**
     * Render shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @return string Shortcode output.
     */
    public function render_shortcode($atts) {
        // Extract attributes
        $atts = shortcode_atts(array(
            'template_id' => 0,
            'design_id' => 0,
            'width' => '',
            'height' => '',
        ), $atts, 'clothing_designer');
        
        // Validate template ID
        $template_id = intval($atts['template_id']);
        
        if ($template_id <= 0) {
            return '<p class="cd-error">' . __('Error: Template ID is required.', 'clothing-designer') . '</p>';
        }
        
        // Get template
        $template = $this->get_template($template_id);
        
        if (!$template) {
            return '<p class="cd-error">' . __('Error: Template not found.', 'clothing-designer') . '</p>';
        }
        
        // Get design if specified
        $design = null;
        $design_id = intval($atts['design_id']);
        $template_changed_notice = '';

        if ($design_id > 0) {
            $design = $this->get_design($design_id);
            // If design references a different template, use that template instead
            if ($design && $design->template_id != $template_id) {

                // Store the original template ID and title for the notice
                $original_template_id = $template_id;
                $original_template = $this->get_template($original_template_id);

                $template_id = $design->template_id;
                $template = $this->get_template($template_id);

                if (!$this->can_view_design($design)) {
                    return '<p class="cd-error">' . __('You do not have permission to view this design.', 'clothing-designer') . '</p>';
                }

                
                if (!$template) {
                    return '<p class="cd-error">' . __('Error: Template not found for the specified design.', 'clothing-designer') . '</p>';
                }

                // Create the template changed notice
                if ($original_template) {
                    $template_changed_notice = sprintf(
                        '<div class="cd-notice">%s</div>',
                        sprintf(
                            __('Note: This design uses template "%s" instead of the requested template "%s".', 'clothing-designer'),
                            esc_html($template->title),
                            esc_html($original_template->title)
                        )
                    );
                }
            }
        }
        
        // Get width and height
        $options = get_option('cd_options', array());
        $width = !empty($atts['width']) ? $atts['width'] : (isset($options['editor_width']) ? $options['editor_width'] : '100%');
        $height = !empty($atts['height']) ? $atts['height'] : (isset($options['editor_height']) ? $options['editor_height'] : '600px');
        
          // Enqueue required assets using the CD_Assets class
        $assets = CD_Assets::get_instance();
        $assets->enqueue_designer_assets();
            
        // Generate a unique ID for this instance
        $designer_id = 'cd-designer-' . uniqid();
        
        // Start output buffer
        ob_start();

        // Output the template changed notice if it exists
        echo $template_changed_notice;
        
        // Render designer container
        ?>
        
        <div class="cd-designer-container" id="<?php echo esc_attr($designer_id); ?>" style="width: <?php echo esc_attr($width); ?>; height: <?php echo esc_attr($height); ?>;">
            <div class="cd-designer-header">
                <div class="cd-designer-title"><?php echo esc_html($template->title); ?></div>
                <div class="cd-designer-actions">
                    <button class="cd-btn cd-btn-reset"><?php echo __('Reset', 'clothing-designer'); ?></button>
                    <button class="cd-btn cd-btn-save"><?php echo __('Save', 'clothing-designer'); ?></button>
                </div>
            </div>
            
            <div class="cd-designer-body">
                <div class="cd-sidebar">
                    <div class="cd-tool-panel cd-upload-panel">
                        <h3><?php echo __('Upload Design', 'clothing-designer'); ?></h3>
                        <div class="cd-tool-content">
                            <input type="file" id="<?php echo esc_attr($designer_id); ?>-upload" class="cd-file-input" accept=".svg,.png,.jpg,.jpeg">
                            <button class="cd-btn cd-btn-upload"><?php echo __('Upload', 'clothing-designer'); ?></button>
                        </div>
                    </div>
                     <div class="cd-view-selector">
                        <button class="cd-btn cd-view-btn active" data-view="front"><?php echo __('Front', 'clothing-designer'); ?></button>
                        <button class="cd-btn cd-view-btn" data-view="back"><?php echo __('Back', 'clothing-designer'); ?></button>
                        <button class="cd-btn cd-view-btn" data-view="left"><?php echo __('Left', 'clothing-designer'); ?></button>
                        <button class="cd-btn cd-view-btn" data-view="right"><?php echo __('Right', 'clothing-designer'); ?></button>
                    </div>
        
                    <div class="cd-tool-panel cd-text-panel">
                        <h3><?php echo __('Add Text', 'clothing-designer'); ?></h3>
                        <div class="cd-tool-content">
                            <input type="text" class="cd-text-input" placeholder="<?php echo __('Enter text...', 'clothing-designer'); ?>">
                            <button class="cd-btn cd-btn-add-text"><?php echo __('Add', 'clothing-designer'); ?></button>
                        </div>
                    </div>
                    
                    <div class="cd-tool-panel cd-properties-panel">
                        <h3><?php echo __('Properties', 'clothing-designer'); ?></h3>
                        <div class="cd-tool-content">
                            <div class="cd-property">
                                <label><?php echo __('Color', 'clothing-designer'); ?></label>
                                <input type="color" class="cd-color-picker" value="#000000">
                            </div>
                            
                            <div class="cd-property">
                                <label><?php echo __('Size', 'clothing-designer'); ?></label>
                                <input type="range" class="cd-size-slider" min="10" max="200" value="40">
                            </div>
                            
                            <div class="cd-property">
                                <label><?php echo __('Rotation', 'clothing-designer'); ?></label>
                                <input type="range" class="cd-rotation-slider" min="0" max="360" value="0">
                            </div>
                        </div>
                    </div>
                    
                    <div class="cd-tool-panel cd-layers-panel">
                        <h3><?php echo __('Layers', 'clothing-designer'); ?></h3>
                        <div class="cd-tool-content">
                            <ul class="cd-layers-list"></ul>
                        </div>
                    </div>
                </div>
                
                <div class="cd-design-area">
                    <div class="cd-canvas-container">
                        <canvas id="<?php echo esc_attr($designer_id); ?>-canvas"></canvas>
                    </div>
                    
                    <div class="cd-loading-overlay">
                        <div class="cd-spinner"></div>
                        <div class="cd-loading-text"><?php echo __('Loading...', 'clothing-designer'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Check if the required libraries are loaded
                if (typeof fabric === 'undefined') {
                    console.error('Fabric.js library not loaded. Please check your theme for conflicts.');
                    $('.cd-canvas-container').html('<div class="cd-error"><?php echo esc_js(__('Error: Required libraries could not be loaded. Please contact the administrator.', 'clothing-designer')); ?></div>');
                    return;
                }
                
                // Initialize designer
                if (typeof ClothingDesigner !== 'undefined') {
                    try {
                        new ClothingDesigner({
                            containerId: '<?php echo esc_js($designer_id); ?>',
                            canvasId: '<?php echo esc_js($designer_id); ?>-canvas',
                            uploadId: '<?php echo esc_js($designer_id); ?>-upload',
                            templateId: <?php echo esc_js($template_id); ?>,
                            <?php if ($design) : ?>
                            designId: <?php echo esc_js($design_id); ?>,
                            <?php endif; ?>
                            width: '<?php echo esc_js($width); ?>',
                            height: '<?php echo esc_js($height); ?>'
                        });
                    } catch (e) {
                        console.error('Error initializing Clothing Designer:', e);
                        $('.cd-canvas-container').html('<div class="cd-error"><?php echo esc_js(__('Error initializing the designer. Please try again or contact support.', 'clothing-designer')); ?></div>');
                    }
                } else {
                    console.error('ClothingDesigner not found. Make sure the script is loaded correctly.');
                    $('.cd-canvas-container').html('<div class="cd-error"><?php echo esc_js(__('Error: Designer component not loaded. Please contact the administrator.', 'clothing-designer')); ?></div>');
                }
            });
        </script>
        <?php
        
        // Return output buffer content
        return ob_get_clean();
    }
    
    /**
     * Check if the current user can view a design.
     *
     * @param object $design Design object.
     * @return boolean True if can view, false otherwise.
     */
    private function can_view_design($design) {
        // If guest designs are allowed, anyone can view
        $options = get_option('cd_options', array());
        $allow_guest_designs = isset($options['allow_guest_designs']) ? $options['allow_guest_designs'] : 'yes';
        
        if ($allow_guest_designs === 'yes') {
            return true;
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return false;
        }
        
        // Admin can view all designs
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // User can view their own designs
        $current_user_id = get_current_user_id();
        return ($design->user_id == $current_user_id);
    }

    /**
     * Get template by ID.
     *
     * @param int $template_id Template ID.
     * @return object|false Template object or false if not found.
     */
    private function get_template($template_id) {
        global $wpdb;
        $templates_table = $wpdb->prefix . 'cd_templates';
        
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$templates_table} WHERE id = %d AND status = 'publish'", $template_id));
    }
    
    /**
     * Get design by ID.
     *
     * @param int $design_id Design ID.
     * @return object|false Design object or false if not found.
     */
    private function get_design($design_id) {
        global $wpdb;
        $designs_table = $wpdb->prefix . 'cd_designs';
        
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$designs_table} WHERE id = %d", $design_id));
    }
}