<?php
namespace OVSI;

class Plugin {
    private static $instance = null;
    private $admin_manager;
    private $admin_settings;
    private $frontend;
    private $ajax_handler;

    public static function get_instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        if (!$this->check_dependencies()) return;
        
        $this->init_components();
        $this->init_hooks();
    }

    private function check_dependencies() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>Obi Variation Stock Indicator requires WooCommerce to be installed and active.</p></div>';
            });
            return false;
        }
        return true;
    }


    private function init_components() {
        $this->admin_manager = new Admin\AdminManager();
        $this->admin_settings = new Admin\Settings();
        $this->frontend = new Frontend\FrontendManager();
        $this->ajax_handler = new Ajax\AjaxHandler();
    }

    private function init_hooks() {
        //add_action('wp_enqueue_scripts', [$this->frontend, 'enqueue_scripts']);
        add_action('admin_menu', [$this->admin_manager, 'add_admin_menu']);
        add_action('admin_init', [$this->admin_settings, 'init_settings']);
        
        // AJAX handlers
        add_action('wp_ajax_get_product_variations', [$this->ajax_handler, 'get_product_variations']);
        add_action('wp_ajax_nopriv_get_product_variations', [$this->ajax_handler, 'get_product_variations']);
    }

    private function __clone() {}
    private function __wakeup() {}
}
