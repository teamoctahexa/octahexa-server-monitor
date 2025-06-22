<?php
/**
 * Plugin Name:       OctaHexa Server Monitor
 * Plugin URI:        https://octahexa.com/plugins/server-monitor
 * Description:       Monitor server resources (CPU, Memory, Load, MySQL) with email alerts and logging
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Brian Chin
 * Author URI:        https://octahexa.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       oh-server-monitor
 * Domain Path:       /languages
 * 
 * @package OctaHexa_Server_Monitor
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Define plugin constants
 */
define( 'OH_SERVER_MONITOR_VERSION', '1.0.0' );
define( 'OH_SERVER_MONITOR_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'OH_SERVER_MONITOR_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'OH_SERVER_MONITOR_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class
 */
class OH_Server_Monitor {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Get instance of this class
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Activation/Deactivation hooks
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
        
        // Admin hooks
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        
        // Cron hooks
        add_action( 'oh_server_monitor_check', array( $this, 'run_resource_check' ) );
        add_action( 'oh_server_monitor_cleanup', array( $this, 'cleanup_old_logs' ) );
        
        // AJAX hooks
        add_action( 'wp_ajax_oh_get_server_stats', array( $this, 'ajax_get_server_stats' ) );
        
        // Settings
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database table
        $this->create_logs_table();
        
        // Schedule cron events
        if ( ! wp_next_scheduled( 'oh_server_monitor_check' ) ) {
            wp_schedule_event( time(), 'oh_every_minute', 'oh_server_monitor_check' );
        }
        
        if ( ! wp_next_scheduled( 'oh_server_monitor_cleanup' ) ) {
            wp_schedule_event( time(), 'daily', 'oh_server_monitor_cleanup' );
        }
        
        // Set default options
        $this->set_default_options();
        
        // Add custom cron interval
        add_filter( 'cron_schedules', array( $this, 'add_cron_interval' ) );
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook( 'oh_server_monitor_check' );
        wp_clear_scheduled_hook( 'oh_server_monitor_cleanup' );
    }
    
    /**
     * Add custom cron interval
     */
    public function add_cron_interval( $schedules ) {
        $schedules['oh_every_minute'] = array(
            'interval' => 60,
            'display'  => __( 'Every Minute', 'oh-server-monitor' )
        );
        return $schedules;
    }
    
    /**
     * Create logs table
     */
    private function create_logs_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'oh_server_monitor_logs';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            cpu_usage float NOT NULL,
            memory_usage float NOT NULL,
            memory_used bigint(20) NOT NULL,
            memory_total bigint(20) NOT NULL,
            load_average varchar(50) NOT NULL,
            cpu_steal float DEFAULT 0,
            mysql_cpu float DEFAULT 0,
            slow_queries int DEFAULT 0,
            alert_sent tinyint(1) DEFAULT 0,
            alert_type varchar(50) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
    
    /**
     * Set default options
     */
    private function set_default_options() {
        $defaults = array(
            'cpu_threshold' => 80,
            'memory_threshold' => 85,
            'load_threshold' => 0, // Will be calculated based on cores
            'mysql_cpu_threshold' => 50,
            'slow_query_threshold' => 10,
            'email_notifications' => 1,
            'notification_email' => get_option( 'admin_email' ),
            'log_retention_days' => 7,
            'check_interval' => 60, // seconds
            'alert_cooldown' => 300, // 5 minutes
        );
        
        foreach ( $defaults as $key => $value ) {
            if ( get_option( 'oh_server_monitor_' . $key ) === false ) {
                update_option( 'oh_server_monitor_' . $key, $value );
            }
        }
        
        // Calculate load threshold based on CPU cores
        if ( get_option( 'oh_server_monitor_load_threshold' ) == 0 ) {
            $cores = $this->get_cpu_cores();
            update_option( 'oh_server_monitor_load_threshold', $cores * 2 );
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'Server Monitor', 'oh-server-monitor' ),
            __( 'Server Monitor', 'oh-server-monitor' ),
            'manage_options',
            'oh-server-monitor',
            array( $this, 'admin_page' ),
            'dashicons-desktop',
            100
        );
        
        add_submenu_page(
            'oh-server-monitor',
            __( 'Settings', 'oh-server-monitor' ),
            __( 'Settings', 'oh-server-monitor' ),
            'manage_options',
            'oh-server-monitor-settings',
            array( $this, 'settings_page' )
        );
        
        add_submenu_page(
            'oh-server-monitor',
            __( 'Logs', 'oh-server-monitor' ),
            __( 'Logs', 'oh-server-monitor' ),
            'manage_options',
            'oh-server-monitor-logs',
            array( $this, 'logs_page' )
        );
    }
    
    /**
     * Get server stats
     */
    public function get_server_stats() {
        $stats = array();
        
        // CPU Usage
        $stats['cpu'] = $this->get_cpu_usage();
        
        // Memory Usage
        $stats['memory'] = $this->get_memory_usage();
        
        // Load Average
        $stats['load'] = $this->get_load_average();
        
        // CPU Cores
        $stats['cores'] = $this->get_cpu_cores();
        
        // CPU Steal (if available)
        $stats['cpu_steal'] = $this->get_cpu_steal();
        
        // MySQL Stats
        $stats['mysql'] = $this->get_mysql_stats();
        
        // Disk Usage
        $stats['disk'] = $this->get_disk_usage();
        
        return $stats;
    }
    
    /**
     * Get CPU usage
     */
    private function get_cpu_usage() {
        if ( ! function_exists( 'sys_getloadavg' ) ) {
            return 0;
        }
        
        // Get CPU stats from /proc/stat
        if ( is_readable( '/proc/stat' ) ) {
            $stat1 = file_get_contents( '/proc/stat' );
            usleep( 100000 ); // Sleep for 0.1 seconds
            $stat2 = file_get_contents( '/proc/stat' );
            
            if ( preg_match( '/^cpu\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $stat1, $matches1 ) &&
                 preg_match( '/^cpu\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $stat2, $matches2 ) ) {
                
                $idle1 = $matches1[4] + $matches1[5];
                $idle2 = $matches2[4] + $matches2[5];
                
                $total1 = array_sum( array_slice( $matches1, 1, 7 ) );
                $total2 = array_sum( array_slice( $matches2, 1, 7 ) );
                
                $diff_idle = $idle2 - $idle1;
                $diff_total = $total2 - $total1;
                
                if ( $diff_total > 0 ) {
                    $cpu_usage = 100 - ( $diff_idle / $diff_total * 100 );
                    return round( $cpu_usage, 2 );
                }
            }
        }
        
        return 0;
    }
    
    /**
     * Get memory usage
     */
    private function get_memory_usage() {
        $memory = array(
            'total' => 0,
            'used' => 0,
            'free' => 0,
            'percentage' => 0
        );
        
        if ( is_readable( '/proc/meminfo' ) ) {
            $meminfo = file_get_contents( '/proc/meminfo' );
            
            preg_match( '/MemTotal:\s+(\d+)/', $meminfo, $total );
            preg_match( '/MemFree:\s+(\d+)/', $meminfo, $free );
            preg_match( '/Buffers:\s+(\d+)/', $meminfo, $buffers );
            preg_match( '/Cached:\s+(\d+)/', $meminfo, $cached );
            
            if ( isset( $total[1] ) && isset( $free[1] ) ) {
                $memory['total'] = $total[1] * 1024; // Convert to bytes
                $memory['free'] = ( $free[1] + ( isset( $buffers[1] ) ? $buffers[1] : 0 ) + ( isset( $cached[1] ) ? $cached[1] : 0 ) ) * 1024;
                $memory['used'] = $memory['total'] - $memory['free'];
                $memory['percentage'] = round( ( $memory['used'] / $memory['total'] ) * 100, 2 );
            }
        }
        
        return $memory;
    }
    
    /**
     * Get load average
     */
    private function get_load_average() {
        if ( function_exists( 'sys_getloadavg' ) ) {
            $load = sys_getloadavg();
            return array(
                '1min' => round( $load[0], 2 ),
                '5min' => round( $load[1], 2 ),
                '15min' => round( $load[2], 2 )
            );
        }
        
        return array( '1min' => 0, '5min' => 0, '15min' => 0 );
    }
    
    /**
     * Get CPU cores
     */
    private function get_cpu_cores() {
        $cores = 1;
        
        if ( is_readable( '/proc/cpuinfo' ) ) {
            $cpuinfo = file_get_contents( '/proc/cpuinfo' );
            preg_match_all( '/^processor/m', $cpuinfo, $matches );
            $cores = count( $matches[0] );
        }
        
        return $cores;
    }
    
    /**
     * Get CPU steal
     */
    private function get_cpu_steal() {
        if ( is_readable( '/proc/stat' ) ) {
            $stat = file_get_contents( '/proc/stat' );
            if ( preg_match( '/^cpu\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $stat, $matches ) ) {
                if ( isset( $matches[8] ) ) {
                    $total = array_sum( array_slice( $matches, 1, 8 ) );
                    $steal = $matches[8];
                    if ( $total > 0 ) {
                        return round( ( $steal / $total ) * 100, 2 );
                    }
                }
            }
        }
        
        return 0;
    }
    
    /**
     * Get MySQL stats
     */
    private function get_mysql_stats() {
        global $wpdb;
        
        $stats = array(
            'cpu' => 0,
            'slow_queries' => 0,
            'connections' => 0,
            'queries_per_second' => 0
        );
        
        // Get MySQL process list
        $processes = $wpdb->get_results( "SHOW PROCESSLIST" );
        $mysql_cpu = 0;
        
        if ( $processes ) {
            foreach ( $processes as $process ) {
                if ( $process->Time > 0 ) {
                    $mysql_cpu += $process->Time;
                }
            }
        }
        
        // Get MySQL status
        $status = $wpdb->get_results( "SHOW GLOBAL STATUS" );
        if ( $status ) {
            foreach ( $status as $row ) {
                if ( $row->Variable_name == 'Slow_queries' ) {
                    $stats['slow_queries'] = intval( $row->Value );
                } elseif ( $row->Variable_name == 'Threads_connected' ) {
                    $stats['connections'] = intval( $row->Value );
                }
            }
        }
        
        $stats['cpu'] = $mysql_cpu;
        
        return $stats;
    }
    
    /**
     * Get disk usage
     */
    private function get_disk_usage() {
        $disk = array(
            'total' => 0,
            'used' => 0,
            'free' => 0,
            'percentage' => 0
        );
        
        if ( function_exists( 'disk_total_space' ) && function_exists( 'disk_free_space' ) ) {
            $disk['total'] = disk_total_space( ABSPATH );
            $disk['free'] = disk_free_space( ABSPATH );
            $disk['used'] = $disk['total'] - $disk['free'];
            $disk['percentage'] = round( ( $disk['used'] / $disk['total'] ) * 100, 2 );
        }
        
        return $disk;
    }
    
    /**
     * Run resource check
     */
    public function run_resource_check() {
        $stats = $this->get_server_stats();
        
        // Get thresholds
        $cpu_threshold = get_option( 'oh_server_monitor_cpu_threshold', 80 );
        $memory_threshold = get_option( 'oh_server_monitor_memory_threshold', 85 );
        $load_threshold = get_option( 'oh_server_monitor_load_threshold', $stats['cores'] * 2 );
        $mysql_cpu_threshold = get_option( 'oh_server_monitor_mysql_cpu_threshold', 50 );
        $slow_query_threshold = get_option( 'oh_server_monitor_slow_query_threshold', 10 );
        
        // Check for alerts
        $alerts = array();
        
        if ( $stats['cpu'] > $cpu_threshold ) {
            $alerts[] = sprintf( __( 'CPU usage is %s%% (threshold: %s%%)', 'oh-server-monitor' ), $stats['cpu'], $cpu_threshold );
        }
        
        if ( $stats['memory']['percentage'] > $memory_threshold ) {
            $alerts[] = sprintf( __( 'Memory usage is %s%% (threshold: %s%%)', 'oh-server-monitor' ), $stats['memory']['percentage'], $memory_threshold );
        }
        
        if ( $stats['load']['1min'] > $load_threshold ) {
            $alerts[] = sprintf( __( 'Load average is %s (threshold: %s)', 'oh-server-monitor' ), $stats['load']['1min'], $load_threshold );
        }
        
        if ( $stats['mysql']['cpu'] > $mysql_cpu_threshold ) {
            $alerts[] = sprintf( __( 'MySQL CPU usage is %s (threshold: %s)', 'oh-server-monitor' ), $stats['mysql']['cpu'], $mysql_cpu_threshold );
        }
        
        if ( $stats['mysql']['slow_queries'] > $slow_query_threshold ) {
            $alerts[] = sprintf( __( 'Slow queries: %s (threshold: %s)', 'oh-server-monitor' ), $stats['mysql']['slow_queries'], $slow_query_threshold );
        }
        
        if ( $stats['cpu_steal'] > 5 ) {
            $alerts[] = sprintf( __( 'CPU steal is %s%% (this may indicate virtualization issues)', 'oh-server-monitor' ), $stats['cpu_steal'] );
        }
        
        // Log the data
        $this->log_stats( $stats, ! empty( $alerts ) );
        
        // Handle alerts
        if ( ! empty( $alerts ) ) {
            $this->handle_alerts( $alerts, 'high' );
        } else {
            // Check if we need to send recovery email
            $this->check_recovery();
        }
    }
    
    /**
     * Log stats to database
     */
    private function log_stats( $stats, $alert = false ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'oh_server_monitor_logs';
        
        $wpdb->insert(
            $table_name,
            array(
                'cpu_usage' => $stats['cpu'],
                'memory_usage' => $stats['memory']['percentage'],
                'memory_used' => $stats['memory']['used'],
                'memory_total' => $stats['memory']['total'],
                'load_average' => json_encode( $stats['load'] ),
                'cpu_steal' => $stats['cpu_steal'],
                'mysql_cpu' => $stats['mysql']['cpu'],
                'slow_queries' => $stats['mysql']['slow_queries'],
                'alert_sent' => $alert ? 1 : 0,
            ),
            array( '%f', '%f', '%d', '%d', '%s', '%f', '%f', '%d', '%d' )
        );
    }
    
    /**
     * Handle alerts
     */
    private function handle_alerts( $alerts, $type = 'high' ) {
        if ( ! get_option( 'oh_server_monitor_email_notifications', 1 ) ) {
            return;
        }
        
        // Check cooldown
        $last_alert = get_transient( 'oh_server_monitor_last_alert' );
        $cooldown = get_option( 'oh_server_monitor_alert_cooldown', 300 );
        
        if ( $last_alert && ( time() - $last_alert ) < $cooldown ) {
            return;
        }
        
        // Send email
        $to = get_option( 'oh_server_monitor_notification_email', get_option( 'admin_email' ) );
        $subject = sprintf( __( '[%s] Server Resource Alert', 'oh-server-monitor' ), get_bloginfo( 'name' ) );
        $message = __( 'The following server resource alerts have been triggered:', 'oh-server-monitor' ) . "\n\n";
        
        foreach ( $alerts as $alert ) {
            $message .= "• " . $alert . "\n";
        }
        
        $message .= "\n" . __( 'Server Information:', 'oh-server-monitor' ) . "\n";
        $message .= sprintf( __( 'Server: %s', 'oh-server-monitor' ), $_SERVER['SERVER_NAME'] ) . "\n";
        $message .= sprintf( __( 'Time: %s', 'oh-server-monitor' ), current_time( 'mysql' ) ) . "\n";
        $message .= "\n" . sprintf( __( 'View detailed statistics: %s', 'oh-server-monitor' ), admin_url( 'admin.php?page=oh-server-monitor' ) );
        
        wp_mail( $to, $subject, $message );
        
        // Set cooldown
        set_transient( 'oh_server_monitor_last_alert', time(), $cooldown );
        
        // Mark as alert sent
        update_option( 'oh_server_monitor_alert_active', true );
    }
    
    /**
     * Check for recovery
     */
    private function check_recovery() {
        if ( get_option( 'oh_server_monitor_alert_active', false ) ) {
            // Send recovery email
            $to = get_option( 'oh_server_monitor_notification_email', get_option( 'admin_email' ) );
            $subject = sprintf( __( '[%s] Server Resources Recovered', 'oh-server-monitor' ), get_bloginfo( 'name' ) );
            $message = __( 'Server resources have returned to normal levels.', 'oh-server-monitor' ) . "\n\n";
            $message .= sprintf( __( 'Time: %s', 'oh-server-monitor' ), current_time( 'mysql' ) ) . "\n";
            $message .= "\n" . sprintf( __( 'View detailed statistics: %s', 'oh-server-monitor' ), admin_url( 'admin.php?page=oh-server-monitor' ) );
            
            wp_mail( $to, $subject, $message );
            
            // Clear alert state
            delete_option( 'oh_server_monitor_alert_active' );
        }
    }
    
    /**
     * Cleanup old logs
     */
    public function cleanup_old_logs() {
        global $wpdb;
        
        $retention_days = get_option( 'oh_server_monitor_log_retention_days', 7 );
        $table_name = $wpdb->prefix . 'oh_server_monitor_logs';
        
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM $table_name WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $retention_days
        ) );
    }
    
    /**
     * AJAX handler for getting server stats
     */
    public function ajax_get_server_stats() {
        check_ajax_referer( 'oh_server_monitor_nonce', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Unauthorized', 'oh-server-monitor' ) );
        }
        
        $stats = $this->get_server_stats();
        wp_send_json_success( $stats );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( strpos( $hook, 'oh-server-monitor' ) === false ) {
            return;
        }
        
        wp_enqueue_script(
            'oh-server-monitor-admin',
            OH_SERVER_MONITOR_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery', 'chart-js' ),
            OH_SERVER_MONITOR_VERSION,
            true
        );
        
        wp_enqueue_style(
            'oh-server-monitor-admin',
            OH_SERVER_MONITOR_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            OH_SERVER_MONITOR_VERSION
        );
        
        wp_localize_script(
            'oh-server-monitor-admin',
            'oh_server_monitor',
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'oh_server_monitor_nonce' ),
                'strings' => array(
                    'cpu' => __( 'CPU', 'oh-server-monitor' ),
                    'memory' => __( 'Memory', 'oh-server-monitor' ),
                    'load' => __( 'Load', 'oh-server-monitor' ),
                )
            )
        );
        
        // Enqueue Chart.js
        wp_enqueue_script(
            'chart-js',
            'https://cdn.jsdelivr.net/npm/chart.js',
            array(),
            '3.9.1',
            true
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        $settings = array(
            'cpu_threshold',
            'memory_threshold',
            'load_threshold',
            'mysql_cpu_threshold',
            'slow_query_threshold',
            'email_notifications',
            'notification_email',
            'log_retention_days',
            'check_interval',
            'alert_cooldown',
        );
        
        foreach ( $settings as $setting ) {
            register_setting( 'oh_server_monitor_settings', 'oh_server_monitor_' . $setting );
        }
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        $stats = $this->get_server_stats();
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <div class="oh-server-monitor-dashboard">
                <div class="oh-stats-grid">
                    <div class="oh-stat-box">
                        <h3><?php _e( 'CPU Usage', 'oh-server-monitor' ); ?></h3>
                        <div class="oh-stat-value">
                            <span id="oh-cpu-usage"><?php echo esc_html( $stats['cpu'] ); ?></span>%
                        </div>
                        <div class="oh-stat-meta">
                            <?php printf( __( '%d Cores', 'oh-server-monitor' ), $stats['cores'] ); ?>
                            <?php if ( $stats['cpu_steal'] > 0 ) : ?>
                                | <?php printf( __( 'Steal: %s%%', 'oh-server-monitor' ), $stats['cpu_steal'] ); ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="oh-stat-box">
                        <h3><?php _e( 'Memory Usage', 'oh-server-monitor' ); ?></h3>
                        <div class="oh-stat-value">
                            <span id="oh-memory-usage"><?php echo esc_html( $stats['memory']['percentage'] ); ?></span>%
                        </div>
                        <div class="oh-stat-meta">
                            <?php 
                            printf( 
                                __( '%s / %s', 'oh-server-monitor' ), 
                                size_format( $stats['memory']['used'] ),
                                size_format( $stats['memory']['total'] )
                            ); 
                            ?>
                        </div>
                    </div>
                    
                    <div class="oh-stat-box">
                        <h3><?php _e( 'Load Average', 'oh-server-monitor' ); ?></h3>
                        <div class="oh-stat-value">
                            <span id="oh-load-average"><?php echo esc_html( $stats['load']['1min'] ); ?></span>
                        </div>
                        <div class="oh-stat-meta">
                            <?php 
                            printf( 
                                __( '1m: %s | 5m: %s | 15m: %s', 'oh-server-monitor' ), 
                                $stats['load']['1min'],
                                $stats['load']['5min'],
                                $stats['load']['15min']
                            ); 
                            ?>
                        </div>
                    </div>
                    
                    <div class="oh-stat-box">
                        <h3><?php _e( 'MySQL', 'oh-server-monitor' ); ?></h3>
                        <div class="oh-stat-value">
                            <span id="oh-mysql-cpu"><?php echo esc_html( $stats['mysql']['cpu'] ); ?></span>
                        </div>
                        <div class="oh-stat-meta">
                            <?php 
                            printf( 
                                __( 'Slow Queries: %d | Connections: %d', 'oh-server-monitor' ), 
                                $stats['mysql']['slow_queries'],
                                $stats['mysql']['connections']
                            ); 
                            ?>
                        </div>
                    </div>
                    
                    <div class="oh-stat-box">
                        <h3><?php _e( 'Disk Usage', 'oh-server-monitor' ); ?></h3>
                        <div class="oh-stat-value">
                            <span id="oh-disk-usage"><?php echo esc_html( $stats['disk']['percentage'] ); ?></span>%
                        </div>
                        <div class="oh-stat-meta">
                            <?php 
                            printf( 
                                __( '%s / %s', 'oh-server-monitor' ), 
                                size_format( $stats['disk']['used'] ),
                                size_format( $stats['disk']['total'] )
                            ); 
                            ?>
                        </div>
                    </div>
                </div>
                
                <div class="oh-charts-container">
                    <div class="oh-chart-box">
                        <h3><?php _e( 'Resource History (Last 24 Hours)', 'oh-server-monitor' ); ?></h3>
                        <canvas id="oh-resource-chart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <form method="post" action="options.php">
                <?php settings_fields( 'oh_server_monitor_settings' ); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="oh_server_monitor_cpu_threshold">
                                <?php _e( 'CPU Threshold (%)', 'oh-server-monitor' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="oh_server_monitor_cpu_threshold" 
                                   name="oh_server_monitor_cpu_threshold" 
                                   value="<?php echo esc_attr( get_option( 'oh_server_monitor_cpu_threshold', 80 ) ); ?>"
                                   min="1" max="100" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oh_server_monitor_memory_threshold">
                                <?php _e( 'Memory Threshold (%)', 'oh-server-monitor' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="oh_server_monitor_memory_threshold" 
                                   name="oh_server_monitor_memory_threshold" 
                                   value="<?php echo esc_attr( get_option( 'oh_server_monitor_memory_threshold', 85 ) ); ?>"
                                   min="1" max="100" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oh_server_monitor_load_threshold">
                                <?php _e( 'Load Average Threshold', 'oh-server-monitor' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="oh_server_monitor_load_threshold" 
                                   name="oh_server_monitor_load_threshold" 
                                   value="<?php echo esc_attr( get_option( 'oh_server_monitor_load_threshold', $this->get_cpu_cores() * 2 ) ); ?>"
                                   min="0.1" step="0.1" />
                            <p class="description">
                                <?php printf( __( 'Your server has %d CPU cores. Recommended threshold: %d', 'oh-server-monitor' ), $this->get_cpu_cores(), $this->get_cpu_cores() * 2 ); ?>
                            </p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oh_server_monitor_mysql_cpu_threshold">
                                <?php _e( 'MySQL CPU Threshold', 'oh-server-monitor' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="oh_server_monitor_mysql_cpu_threshold" 
                                   name="oh_server_monitor_mysql_cpu_threshold" 
                                   value="<?php echo esc_attr( get_option( 'oh_server_monitor_mysql_cpu_threshold', 50 ) ); ?>"
                                   min="1" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oh_server_monitor_slow_query_threshold">
                                <?php _e( 'Slow Query Threshold', 'oh-server-monitor' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="oh_server_monitor_slow_query_threshold" 
                                   name="oh_server_monitor_slow_query_threshold" 
                                   value="<?php echo esc_attr( get_option( 'oh_server_monitor_slow_query_threshold', 10 ) ); ?>"
                                   min="1" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oh_server_monitor_email_notifications">
                                <?php _e( 'Email Notifications', 'oh-server-monitor' ); ?>
                            </label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="oh_server_monitor_email_notifications" 
                                       name="oh_server_monitor_email_notifications" 
                                       value="1"
                                       <?php checked( get_option( 'oh_server_monitor_email_notifications', 1 ), 1 ); ?> />
                                <?php _e( 'Enable email alerts', 'oh-server-monitor' ); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oh_server_monitor_notification_email">
                                <?php _e( 'Notification Email', 'oh-server-monitor' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="email" 
                                   id="oh_server_monitor_notification_email" 
                                   name="oh_server_monitor_notification_email" 
                                   value="<?php echo esc_attr( get_option( 'oh_server_monitor_notification_email', get_option( 'admin_email' ) ) ); ?>"
                                   class="regular-text" />
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oh_server_monitor_log_retention_days">
                                <?php _e( 'Log Retention (Days)', 'oh-server-monitor' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="oh_server_monitor_log_retention_days" 
                                   name="oh_server_monitor_log_retention_days" 
                                   value="<?php echo esc_attr( get_option( 'oh_server_monitor_log_retention_days', 7 ) ); ?>"
                                   min="1" max="90" />
                            <p class="description"><?php _e( 'How many days to keep logs (1-90)', 'oh-server-monitor' ); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="oh_server_monitor_alert_cooldown">
                                <?php _e( 'Alert Cooldown (Seconds)', 'oh-server-monitor' ); ?>
                            </label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="oh_server_monitor_alert_cooldown" 
                                   name="oh_server_monitor_alert_cooldown" 
                                   value="<?php echo esc_attr( get_option( 'oh_server_monitor_alert_cooldown', 300 ) ); ?>"
                                   min="60" />
                            <p class="description"><?php _e( 'Minimum time between alert emails', 'oh-server-monitor' ); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Logs page
     */
    public function logs_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'oh_server_monitor_logs';
        
        // Get logs from last 24 hours
        $logs = $wpdb->get_results(
            "SELECT * FROM $table_name 
             WHERE timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR) 
             ORDER BY timestamp DESC 
             LIMIT 1000"
        );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <div class="oh-logs-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e( 'Time', 'oh-server-monitor' ); ?></th>
                            <th><?php _e( 'CPU %', 'oh-server-monitor' ); ?></th>
                            <th><?php _e( 'Memory %', 'oh-server-monitor' ); ?></th>
                            <th><?php _e( 'Load Average', 'oh-server-monitor' ); ?></th>
                            <th><?php _e( 'CPU Steal %', 'oh-server-monitor' ); ?></th>
                            <th><?php _e( 'MySQL CPU', 'oh-server-monitor' ); ?></th>
                            <th><?php _e( 'Slow Queries', 'oh-server-monitor' ); ?></th>
                            <th><?php _e( 'Alert', 'oh-server-monitor' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( $logs ) : ?>
                            <?php foreach ( $logs as $log ) : ?>
                                <?php $load = json_decode( $log->load_average, true ); ?>
                                <tr class="<?php echo $log->alert_sent ? 'oh-alert-row' : ''; ?>">
                                    <td><?php echo esc_html( $log->timestamp ); ?></td>
                                    <td><?php echo esc_html( $log->cpu_usage ); ?>%</td>
                                    <td><?php echo esc_html( $log->memory_usage ); ?>%</td>
                                    <td><?php echo esc_html( $load['1min'] ); ?></td>
                                    <td><?php echo esc_html( $log->cpu_steal ); ?>%</td>
                                    <td><?php echo esc_html( $log->mysql_cpu ); ?></td>
                                    <td><?php echo esc_html( $log->slow_queries ); ?></td>
                                    <td><?php echo $log->alert_sent ? '<span class="dashicons dashicons-warning"></span>' : '-'; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <tr>
                                <td colspan="8"><?php _e( 'No logs found.', 'oh-server-monitor' ); ?></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
}

// Initialize the plugin
add_action( 'plugins_loaded', array( 'OH_Server_Monitor', 'get_instance' ) );

// Add cron interval filter early
add_filter( 'cron_schedules', function( $schedules ) {
    $schedules['oh_every_minute'] = array(
        'interval' => 60,
        'display'  => __( 'Every Minute', 'oh-server-monitor' )
    );
    return $schedules;
});
