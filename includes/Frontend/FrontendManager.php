<?php
namespace OVSI\Frontend;

class FrontendManager {
    private $variation_handler;
    private $assets;

    public function __construct() {
        //$this->variation_handler = new VariationHandler();
        $this->assets = new Assets();
        
        //add_action('wp_footer', [$this, 'add_variation_script']);

        add_filter('woocommerce_dropdown_variation_attribute_options_args', 
            [$this, 'add_last_attribute_class'], 20, 1);

            add_filter('woocommerce_dropdown_variation_attribute_options_args', 
            [$this, 'track_current_attribute'], 10, 1);
    }
/*
    public function add_variation_script() {
        if (!is_product()) return;
        
        require_once OVSI_PLUGIN_DIR . 'templates/frontend/variation-script.php';
    }
*/
    /**
     * Add class to the last attribute dropdown
     */
    public function add_last_attribute_class($args) {
        global $product;
        if (!$product) return $args;
        
        $attributes = $product->get_variation_attributes();
        $last_attribute = array_key_last($attributes);
        
        if ($args['attribute'] === $last_attribute) {
            $args['class'] = isset($args['class']) ? 
                $args['class'] . ' last-attribute' : 'last-attribute';
        }
        
        return $args;
    }

    /**
     * Track which attribute is being processed
     */
    public function track_current_attribute($args) {
        global $current_attribute_name;
        $current_attribute_name = $args['attribute'];
        return $args;
    }
}