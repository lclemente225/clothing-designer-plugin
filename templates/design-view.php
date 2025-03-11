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
        
        // If views exist, show view tabs and panels
        if (!empty($views)) {
            echo '<div class="design-views">';
            foreach (array_keys($views) as $view_type) {
                $active_class = ($view_type === $current_view) ? 'active' : '';
                echo '<div class="view-tab ' . $active_class . '" data-view="' . esc_attr($view_type) . '">' . 
                     ucfirst(esc_html($view_type)) . '</div>';
            }
            echo '</div>';
            
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
                echo '<img src="' . esc_url($design->preview_url) . '" alt="' . __('Design Preview', 'clothing-designer') . '" class="design-image">';
            } else {
                echo '<div class="no-preview">' . __('No preview image available', 'clothing-designer') . '</div>';
            }
            echo '</div>';
        }
        ?>
        
        <div class="design-footer">
            <div class="design-user">
                <?php 
                $user_name = $design->user_id > 0 ? get_userdata($design->user_id)->display_name : __('Guest', 'clothing-designer');
                echo __('Created by:', 'clothing-designer'); ?> <strong><?php echo esc_html($user_name); ?></strong>
            </div>
            <div class="design-date">
                <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($design->created_at))); ?>
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