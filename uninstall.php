<?php
/**
 * File: uninstall.php
 * Description: Uninstall script for OctaHexa Server Monitor plugin
 * Version: 1.0.0
 * 
 * @package OctaHexa_Server_Monitor
 */

// Exit if uninstall not called from WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Clean up plugin data
 */
function oh_server_monitor_uninstall() {
    global $wpdb;
    
    // Remove options
    $options = array(
        'oh_server_monitor_cpu_threshold',
        'oh_server_monitor_memory_threshold',
        'oh_server_monitor_load_threshold',
        'oh_server_monitor_mysql_cpu_threshold',
        'oh_server_monitor_slow_query_threshold',
        'oh_server_monitor_email_notifications',
        'oh_server_monitor_notification_email',
        'oh_server_monitor_log_retention_days',
        'oh_server_monitor_check_interval',
        'oh_server_monitor_alert_cooldown',
        'oh_server_monitor_alert_active',
    );
    
    foreach ( $options as $option ) {
        delete_option( $option );
    }
    
    // Remove transients
    delete_transient( 'oh_server_monitor_last_alert' );
    
    // Drop custom table
    $table_name = $wpdb->prefix . 'oh_server_monitor_logs';
    $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
    
    // Clear scheduled cron events
    wp_clear_scheduled_hook( 'oh_server_monitor_check' );
    wp_clear_scheduled_hook( 'oh_server_monitor_cleanup' );
}

// Run uninstall
oh_server_monitor_uninstall();
