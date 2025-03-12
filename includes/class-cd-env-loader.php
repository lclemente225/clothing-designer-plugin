<?php
/**
 * Loads environment variables from a .env file for Clothing Designer plugin
 *
 * @package Clothing_Designer
 */

if (!defined('ABSPATH')) {
    exit;
}

class CD_Env_Loader {
    /**
     * Instance.
     *
     * @var CD_Env_Loader
     */
    private static $instance = null;
    
    /**
     * Environment variables
     * 
     * @var array
     */
    private $env_vars = array();
    
    /**
     * Get instance.
     *
     * @return CD_Env_Loader
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
        $this->load_env_file();
    }
    
    /**
     * Load environment variables from .env file
     */
    private function load_env_file() {
        if (!defined('CD_PLUGIN_DIR')) {
            return;
        }
        
        $env_file = CD_PLUGIN_DIR . '.env';
        
        if (file_exists($env_file)) {
            $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            if ($lines === false) {
                return;
            }
            
            foreach ($lines as $line) {
                // Skip comments
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }
                
                // Skip lines without equals sign
                if (strpos($line, '=') === false) {
                    continue;
                }
                
                // Parse line
                list($name, $value) = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                
                // Strip quotes if present
                if (strpos($value, '"') === 0 && strrpos($value, '"') === strlen($value) - 1) {
                    $value = substr($value, 1, -1);
                } elseif (strpos($value, "'") === 0 && strrpos($value, "'") === strlen($value) - 1) {
                    $value = substr($value, 1, -1);
                }
                
                // Store in our array
                $this->env_vars[$name] = $value;
                
                // Set in $_ENV array if not already set
                if (!isset($_ENV[$name])) {
                    $_ENV[$name] = $value;
                }
                
                // Try to use putenv if allowed by server configuration
                if (function_exists('putenv') && !getenv($name)) {
                    @putenv("$name=$value");
                }
            }
        }
    }
    
    /**
     * Get environment variable
     *
     * @param string $key Variable name
     * @param mixed $default Default value if not found
     * @return mixed
     */
    public static function get($key, $default = null) {
        $instance = self::get_instance();
        
        if (isset($instance->env_vars[$key])) {
            return $instance->env_vars[$key];
        }
        
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        
        return $default;
    }
}