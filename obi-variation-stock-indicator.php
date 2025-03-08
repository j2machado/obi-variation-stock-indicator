<?php
/**
 * Plugin Name: Obi Variation Stock Indicator
 * Description: A WordPress plugin that displays the stock indicator for each variation of a product.
 * Version: 1.0
 * Author: Obi Juan
 * Author URI: https://www.obijuan.dev
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: obi-variation-stock-indicator
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('OVSI_VERSION', '1.0.0');
define('OVSI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OVSI_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoloader for plugin classes
spl_autoload_register(function ($class) {
    // Project-specific namespace prefix
    $prefix = 'OVSI\\';
    $base_dir = OVSI_PLUGIN_DIR . 'includes/';

    // Check if the class uses the namespace prefix
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Get the relative class name
    $relative_class = substr($class, $len);

    // Replace namespace separators with directory separators
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // If the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

// Initialize the plugin
add_action('plugins_loaded', function() {
    OVSI\Plugin::get_instance();
});
