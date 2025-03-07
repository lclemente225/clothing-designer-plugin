<?php
// If uninstall is not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin database tables
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}cd_designs");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}cd_templates");

// Delete plugin options
delete_option('cd_options');

// Optionally, you might want to remove the uploaded files
// Uncomment the following code if you want to remove all user-created designs

$upload_dir = wp_upload_dir();
$designs_dir = $upload_dir['basedir'] . '/clothing-designs/';

if (is_dir($designs_dir)) {
    // Use a recursive function to delete directory and contents
    function cd_recursive_rmdir($dir) {
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? cd_recursive_rmdir($path) : unlink($path);
        }
        return rmdir($dir);
    }
    
    cd_recursive_rmdir($designs_dir);
}
