<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo sprintf(__('Design Preview - %s', 'clothing-designer'), esc_html($design->template_title)); ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
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
        .design-images {
            text-align: center;
            padding: 30px;
        }
        .design-image {
            max-width: 100%;
            height: auto;
            display: block;
            margin: 0 auto;
            max-height: 500px;
        }
        .design-views {
            display: flex;
            justify-content: center;
            padding: 0 0 20px;
            border-bottom: 1px solid #e5e5e5;
        }
        .view-tab {
            padding: 8px 16px;
            margin: 0 5px;
            cursor: pointer;
            background: #f0f0f0;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-weight: bold;
            color: black;
            font-size: 16px;
            display: inline-block;
        }
        .view-tab.active {
            background: #007cba;
            color: white;
            border-color: #006ba1;
        }
        .view-panel {
            display: none;
            padding: 20px;
            text-align: center;
        }
        .view-panel.active {
            display: block;
        }
        .no-preview {
            padding: 80px 20px;
            background-color: #f5f5f5;
            border-radius: 4px;
            color: #888;
            font-size: 16px;
        }
        .design-footer {
            padding: 15px 20px;
            border-top: 1px solid #e5e5e5;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
        }
    </style>
    <script src="<?php echo includes_url('/js/jquery/jquery.js'); ?>"></script>
</head>
<body>
    <div class="design-preview">
        <div class="design-header">
            <h1 class="design-title"><?php echo esc_html($design->template_title); ?> - <?php echo __('Design Preview', 'clothing-designer'); ?></h1>
            <div class="design-meta">
                <?php echo __('Template:', 'clothing-designer'); ?> <strong><?php echo esc_html($design->template_title); ?></strong>
            </div>
        </div>
        
        <?php
        // Parse design data to extract views
        $design_data = json_decode($design->design_data, true);
        $current_view = isset($design_data['currentView']) ? $design_data['currentView'] : 'front';
        $views = isset($design_data['views']) ? $design_data['views'] : array();
        error_log('check variables in design-view.php - design_data: ' . print_r($design_data, true) . 
    ' current_view: ' . $current_view . 
    ' views: ' . print_r($views, true));
        // If views exist, show view tabs and panels
        if (!empty($views)) {
            echo '<div class="design-views">';
            foreach (array_keys($views) as $view_type) {
                $active_class = ($view_type === $current_view) ? 'active' : '';
                echo '<div class="view-tab ' . $active_class . '" data-view="' . esc_attr($view_type) . '">' . 
                     ucfirst(esc_html($view_type)) . '</div>';
            }
            echo '</div>';
       /*      {"currentView":"front",
                "views":{
                    "front":{
                        "elements":[{
                            "id":"element-yyvio7r",
                            "name":"67d362611e26c-9862556.jpg",
                            "type":"image",
                            "left":509,
                            "top":264.75,
                            "scaleX":0.08825,
                            "scaleY":0.08825,
                            "angle":0,
                            "src":"https://bmssportswear.com/wp-content/uploads/clothing-designs/67d362611e26c-9862556.jpg"
                        }],
                        "templateData":{
                            "file_url":"https://bmssportswear.com/wp-content/uploads/clothing-designs/67d361dd32ea8-svg1.svg","file_type":"svg"
                        }
                    },"back":{
                        "elements":[{
                            "id":"element-8qbgk57",
                            "name":"67d3625b01c5a-9862556.jpg",
                            "type":"image",
                            "left":287,
                            "top":287.72828139754483,
                            "scaleX":0.08825,
                            "scaleY":0.08825,
                            "angle":0,
                            "src":"https://bmssportswear.com/wp-content/uploads/clothing-designs/67d3625b01c5a-9862556.jpg"
                        }],
                        "templateData":{"file_url":"https://bmssportswear.com/wp-content/uploads/clothing-designs/67d361e0a1656-2shirts.svg","file_type":"svg"}},
                        "left":{"elements":[],"templateData":{"file_url":"https://bmssportswear.com/wp-content/uploads/clothing-designs/67d361e7ca344-9862554.ai","file_type":"ai"}}
                    }} */
            echo '<div class="design-images">';
            foreach ($views as $view_type => $view_data) {
                $active_class = ($view_type === $current_view) ? 'active' : '';
                
                // Get preview URL from view data or use main preview
                $preview_url = isset($view_data['preview_url']) ? $view_data['preview_url'] : '';
                if (empty($preview_url) && $view_type === $current_view) {
                    $preview_url = $design->preview_url;
                }
                // If still empty, try using template file URL as a fallback
                if (empty($preview_url) && isset($view_data['templateData']['file_url'])) {
                    $preview_url = $view_data['templateData']['file_url'];
                }
                
                echo '<div class="view-panel ' . $active_class . '" data-view="' . esc_attr($view_type) . '">';
                if (!empty($preview_url)) {
                    echo '<img src="' . esc_url($preview_url) . '" alt="' . 
                         sprintf(__('%s view', 'clothing-designer'), ucfirst($view_type)) . '" class="design-image">';
                } else {
                    echo '<div class="no-preview">' . 
                         sprintf(__('No preview available for %s view', 'clothing-designer'), ucfirst($view_type)) . '</div>';
                }
                echo '</div>';
            }
            echo '</div>';
        } else {
            // Fallback to single preview
            echo '<div class="design-images">';
            if (!empty($design->preview_url)) {
                echo '<img src="' . esc_url($design->preview_url) . '" alt="' . esc_html__('Design Preview', 'clothing-designer') . '" class="design-image">';
            } else {
                echo '<div class="no-preview">' . esc_html__('No preview image available', 'clothing-designer') . '</div>';
            }
            echo '</div>';
        }
        ?>
        
        <div class="design-footer">
            <div class="design-user">
                <?php 
                $user_name = $design->user_id > 0 ? get_userdata($design->user_id)->display_name : __('Guest', 'clothing-designer');
                echo esc_html__('Created by:', 'clothing-designer'); ?> <strong><?php echo esc_html($user_name); ?></strong>
            </div>
            <div class="design-date">
                <?php echo esc_html__(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($design->created_at))); ?>
            </div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // View tab click handler
        $('.view-tab').on('click', function() {
            const viewType = $(this).data('view');
            
            // Update active tab
            $('.view-tab').removeClass('active');
            $(this).addClass('active');
            
            // Show the selected view panel
            $('.view-panel').removeClass('active');
            $(`.view-panel[data-view="${viewType}"]`).addClass('active');
        });
    });
    </script>
</body>
</html>