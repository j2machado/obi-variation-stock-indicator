<?php
namespace OVSI\Frontend;

class Assets {
    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    public function enqueue_scripts() {
        if ( !is_product()) return;

        wp_enqueue_script('jquery');

        wp_enqueue_script('ovsi-frontend', 
            OVSI_PLUGIN_URL . 'assets/js/frontend.js',
            ['jquery'],
            OVSI_VERSION,
            true
        ); // Used to have wc-add-to-cart-variation as dependency. Not needed anymore.

        $this->localize_script();
    }

    private function localize_script() {
        // Get plugin settings
        $options = get_option('ovsi_settings', array());
        $disable_out_of_stock = isset($options['disable_out_of_stock']) ? $options['disable_out_of_stock'] : 'yes';
        $stock_order = isset($options['stock_order']) ? $options['stock_order'] : 'disabled';
        
        // Get text strings
        $strings = array();
        foreach ($this->get_default_strings() as $key => $default) {
            $strings[$key] = $this->get_text_string($key);
        }
        
        // Add AJAX URL, settings and strings to script
        // Get low stock threshold
        $low_stock_threshold = isset($options['low_stock_threshold']) ? absint($options['low_stock_threshold']) : 10;

        wp_localize_script('jquery', 'wc_ajax_object', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'disable_out_of_stock' => $disable_out_of_stock,
            'stock_order' => $stock_order,
            'strings' => $strings,
            'low_stock_threshold' => $low_stock_threshold
        ));

        //wp_enqueue_script('ovsi-frontend', OVSI_PLUGIN_URL . 'assets/js/frontend.js', ['jquery'], OVSI_VERSION, true);
    }

    /**
     * Get customized or default text string
     *
     * @param string $key The text string key
     * @param string|int $quantity Optional quantity for strings that use it
     * @return string The customized or default text string
     */
    public function get_text_string($key, $quantity = null) {
        $options = get_option('ovsi_settings', array());
        $default_strings = $this->get_default_strings();
        
        // Get the custom text if set, otherwise use default
        $text_key = 'text_' . $key;
        $text = isset($options[$text_key]) && !empty($options[$text_key]) 
            ? $options[$text_key] 
            : $default_strings[$key];
        
        // Replace merge tag with quantity if provided
        if ($quantity !== null) {
            $text = str_replace('{stock}', $quantity, $text);
        }
        
        return $text;
    }

    public function get_default_strings() {
        return array(
            'in_stock' => 'In stock',
            'out_of_stock' => 'Out of stock',
            'on_backorder' => 'On backorder',
            'x_in_stock' => '{stock} in stock',
            'low_stock' => 'Only {stock} left in stock'
        );
    }

    private function get_text_strings() {
        return [
            'in_stock' => __('In stock', 'obi-variation-stock-indicator'),
            'out_of_stock' => __('Out of stock', 'obi-variation-stock-indicator'),
            'on_backorder' => __('Available on backorder', 'obi-variation-stock-indicator'),
            'low_stock' => __('Only %s left in stock', 'obi-variation-stock-indicator')
        ];
    }
}
