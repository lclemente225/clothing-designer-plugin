<div class="cd-design-preview-container">
    <h1><?php echo esc_html($design->template_title); ?> - <?php echo __('Design Preview', 'clothing-designer'); ?></h1>
    
    <?php
    // Parse design data
    $design_data = json_decode($design->design_data, true);
    $views = isset($design_data['views']) ? $design_data['views'] : array();
    $current_view = isset($design_data['currentView']) ? $design_data['currentView'] : 'front';
    
    // Show view selector if multiple views
    if (count($views) > 1) {
        echo '<div class="cd-view-selector">';
        foreach (array_keys($views) as $view_type) {
            $active_class = ($view_type === $current_view) ? 'active' : '';
            echo '<button class="cd-view-btn ' . $active_class . '" data-view="' . esc_attr($view_type) . '">' . 
                ucfirst(esc_html($view_type)) . '</button>';
        }
        echo '</div>';
    }
    ?>
    
    <div class="cd-design-views">
        <?php 
        // Show all views
        foreach ($views as $view_type => $view_data) {
            $display = ($view_type === $current_view) ? 'block' : 'none';
            $preview_url = isset($view_data['preview_url']) ? $view_data['preview_url'] : '';
            
            // Fallback to main preview URL if view doesn't have its own
            if (empty($preview_url) && !empty($design->preview_url)) {
                $preview_url = $design->preview_url;
            }
            
            echo '<div class="cd-design-view" data-view="' . esc_attr($view_type) . '" style="display: ' . $display . ';">';
            
            if (!empty($preview_url)) {
                echo '<img src="' . esc_url($preview_url) . '" alt="' . sprintf(__('%s view', 'clothing-designer'), ucfirst($view_type)) . '">';
            } else {
                echo '<div class="cd-no-preview">' . sprintf(__('No preview available for %s view', 'clothing-designer'), ucfirst($view_type)) . '</div>';
            }
            
            echo '</div>';
        }
        ?>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // View button click handler
        $('.cd-view-btn').on('click', function() {
            var viewType = $(this).data('view');
            
            // Update active button
            $('.cd-view-btn').removeClass('active');
            $(this).addClass('active');
            
            // Show selected view, hide others
            $('.cd-design-view').hide();
            $('.cd-design-view[data-view="' + viewType + '"]').show();
        });
    });
    </script>
</div>

<style>
.cd-design-preview-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
}
.cd-view-selector {
    margin: 20px 0;
    text-align: center;
}
.cd-view-btn {
    padding: 8px 16px;
    margin: 0 5px;
    background: #f0f0f0;
    border: 1px solid #ddd;
    cursor: pointer;
}
.cd-view-btn.active {
    background: #007cba;
    color: white;
    border-color: #006ba1;
}
.cd-design-views {
    margin-top: 20px;
    text-align: center;
}
.cd-design-view img {
    max-width: 100%;
    max-height: 600px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.cd-no-preview {
    padding: 100px 20px;
    background: #f5f5f5;
    color: #666;
    font-size: 18px;
}
</style>