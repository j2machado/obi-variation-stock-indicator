<?php
namespace OVSI\Admin;

class AdminManager {
    private $settings;

    public function __construct() {
        $this->settings = new Settings();
        
        //add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
    }

    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'Variation Stock Indicator',
            'Stock Indicator',
            'manage_options',
            'variation-stock-indicator',
            array($this, 'render_admin_page')
        );
    }

    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'woocommerce_page_variation-stock-indicator') {
            return;
        }
        
        wp_enqueue_script('ovsi-admin', OVSI_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], OVSI_VERSION, true);
    }

    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        require_once OVSI_PLUGIN_DIR . 'templates/admin/settings-page.php';
    }
}