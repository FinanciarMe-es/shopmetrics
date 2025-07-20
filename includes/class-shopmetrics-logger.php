<?php
/**
 * ShopMetrics Logger
 * 
 * Provides configurable logging with levels and rotation
 *
 * @package ShopMetrics
 */

if (!defined('ABSPATH')) {
    exit;
}

class ShopMetrics_Logger {
    
    // Log levels (hierarchical - lower numbers = higher priority)
    const ERROR = 1;
    const WARN  = 2;
    const INFO  = 3;
    const DEBUG = 4;
    
    private static $instance = null;
    private $log_level;
    private $log_file;
    private $max_file_size;
    private $max_files;
    private $enabled;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_settings();
    }
    
    /**
     * Initialize logger settings
     */
    private function init_settings() {
        // Get log level from constant or default to ERROR
        // Force re-evaluation of the constant as it might be defined after class loading
        if (defined('SHOPMETRICS_LOG_LEVEL')) {
            $this->log_level = SHOPMETRICS_LOG_LEVEL;
        } else {
            $this->log_level = self::ERROR;
            // Log a warning that the constant wasn't found (but only if logging is enabled)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                \ShopMetrics_Logger::get_instance()->error("ShopMetrics Logger: SHOPMETRICS_LOG_LEVEL constant not found, defaulting to ERROR level");
            }
        }
        
        // Enable logging if WP_DEBUG is on OR if explicitly enabled
        $settings = get_option('shopmetrics_settings', []);
        $this->enabled = !empty($settings['enable_debug_logging']);
        
        // Log file settings - use uploads directory for security and compatibility
        $upload_dir = wp_upload_dir();
        $log_dir = trailingslashit($upload_dir['basedir']) . 'shopmetrics';
        
        // Create log directory if it doesn't exist
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            // Add .htaccess to deny web access (extra security)
            file_put_contents($log_dir . '/.htaccess', "Deny from all\n");
            // Add index.php to prevent directory listing
            file_put_contents($log_dir . '/index.php', '<?php // Silence is golden');
        }
        
        $this->log_file = $log_dir . '/shopmetrics.log';
        $this->max_file_size = defined('SHOPMETRICS_LOG_MAX_SIZE') ? SHOPMETRICS_LOG_MAX_SIZE : 10 * 1024 * 1024; // 10MB
        $this->max_files = defined('SHOPMETRICS_LOG_MAX_FILES') ? SHOPMETRICS_LOG_MAX_FILES : 5;
    }
    
    /**
     * Log a message with specified level
     */
    public function log($message, $level, $context = array()) {
        // Early return if logging is disabled
        if (!$this->enabled) {
            return;
        }
        
        // Suppress logging during test summary mode
        if (defined('FINANCIARME_TEST_SUMMARY_MODE') && FINANCIARME_TEST_SUMMARY_MODE) {
            return;
        }
        
        // Check if this level should be logged
        if ($level > $this->log_level) {
            return;
        }
        
        // Rotate log if needed
        $this->rotate_if_needed();
        
        // Format the log entry
        $log_entry = $this->format_log_entry($level, $message, $context);
        
        // Write to file
        error_log($log_entry, 3, $this->log_file);
    }
    
    /**
     * Convenience methods for different log levels
     */
    public function error($message, $context = array()) {
        $this->log($message, self::ERROR, $context);
    }
    
    public function warn($message, $context = array()) {
        $this->log($message, self::WARN, $context);
    }
    
    public function info($message, $context = array()) {
        $this->log($message, self::INFO, $context);
    }
    
    public function debug($message, $context = array()) {
        $this->log($message, self::DEBUG, $context);
    }
    
    /**
     * Format log entry
     */
    private function format_log_entry($level, $message, $context) {
        $timestamp = current_time('Y-m-d H:i:s');
        $level_name = $this->get_level_name($level);
        $memory_usage = round(memory_get_usage() / 1024 / 1024, 2);
        
        // Add context if provided
        $context_str = '';
        if (!empty($context)) {
            $context_str = ' | Context: ' . json_encode($context);
        }
        
        // Get calling function for better debugging (only in debug mode)
        $caller = 'unknown';
        if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG && function_exists('debug_backtrace')) {
            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);
            
            // Find the first caller that's not this logger class
            foreach ($backtrace as $trace) {
                if (isset($trace['class']) && $trace['class'] !== __CLASS__) {
                    $caller = $trace['class'] . '::' . $trace['function'];
                    break;
                } elseif (!isset($trace['class']) && isset($trace['function'])) {
                    $caller = $trace['function'];
                    break;
                }
            }
        }
        
        return sprintf(
            "[%s] %s: %s | Caller: %s | Memory: %sMB%s\n",
            $timestamp,
            $level_name,
            $message,
            $caller,
            $memory_usage,
            $context_str
        );
    }
    
    /**
     * Get level name from level constant
     */
    private function get_level_name($level) {
        switch ($level) {
            case self::ERROR: return 'ERROR';
            case self::WARN:  return 'WARN';
            case self::INFO:  return 'INFO';
            case self::DEBUG: return 'DEBUG';
            default:          return 'UNKNOWN';
        }
    }
    
    /**
     * Rotate log file if it's too large
     */
    private function rotate_if_needed() {
        // Initialize WordPress filesystem
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }
        
        if (!$wp_filesystem->exists($this->log_file)) {
            return;
        }
        
        if ($wp_filesystem->size($this->log_file) > $this->max_file_size) {
            $this->rotate_logs();
        }
    }
    
    /**
     * Perform log rotation
     */
    private function rotate_logs() {
        // Initialize WordPress filesystem
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }
        
        $log_dir = dirname($this->log_file);
        $log_name = basename($this->log_file, '.log');
        
        // Remove oldest log file if we have too many
        $oldest_log = $log_dir . '/' . $log_name . '.' . $this->max_files . '.log';
        if ($wp_filesystem->exists($oldest_log)) {
            wp_delete_file($oldest_log);
        }
        
        // Rotate existing log files
        for ($i = $this->max_files - 1; $i >= 1; $i--) {
            $old_file = $log_dir . '/' . $log_name . '.' . $i . '.log';
            $new_file = $log_dir . '/' . $log_name . '.' . ($i + 1) . '.log';
            
            if ($wp_filesystem->exists($old_file)) {
                $wp_filesystem->move($old_file, $new_file);
            }
        }
        
        // Move current log to .1
        $rotated_file = $log_dir . '/' . $log_name . '.1.log';
        $wp_filesystem->move($this->log_file, $rotated_file);
        
        // Log the rotation event
        $this->info('Log file rotated', array(
            'old_size' => $wp_filesystem->exists($rotated_file) ? $wp_filesystem->size($rotated_file) : 0,
            'max_size' => $this->max_file_size
        ));
    }
    
    /**
     * Get current log level name
     */
    public function get_current_log_level() {
        // Force re-read of the constant in case it was defined after initialization
        $current_level = defined('SHOPMETRICS_LOG_LEVEL') ? SHOPMETRICS_LOG_LEVEL : $this->log_level;
        return $this->get_level_name($current_level);
    }
    
    /**
     * Check if logging is enabled
     */
    public function is_enabled() {
        // Check WP option for debug logging
        $settings = get_option('shopmetrics_settings', []);
        if (!empty($settings['enable_debug_logging'])) {
            return true;
        }
        // Fallback to constant or other logic if needed
        return defined('SHOPMETRICS_LOG_LEVEL') && SHOPMETRICS_LOG_LEVEL !== 'none';
    }
    
    /**
     * Get log file path
     */
    public function get_log_file() {
        return $this->log_file;
    }
    
    /**
     * Get log file size in MB
     */
    public function get_log_file_size() {
        // Initialize WordPress filesystem
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }
        
        if ($wp_filesystem->exists($this->log_file)) {
            return round($wp_filesystem->size($this->log_file) / 1024 / 1024, 2);
        }
        return 0;
    }
    
    /**
     * Clear all log files
     */
    public function clear_logs() {
        // Initialize WordPress filesystem
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }
        
        $log_dir = dirname($this->log_file);
        $log_name = basename($this->log_file, '.log');
        
        // Remove main log file
        if ($wp_filesystem->exists($this->log_file)) {
            wp_delete_file($this->log_file);
        }
        
        // Remove rotated log files
        for ($i = 1; $i <= $this->max_files; $i++) {
            $rotated_file = $log_dir . '/' . $log_name . '.' . $i . '.log';
            if ($wp_filesystem->exists($rotated_file)) {
                wp_delete_file($rotated_file);
            }
        }
        
        $this->info('All log files cleared');
    }
} 