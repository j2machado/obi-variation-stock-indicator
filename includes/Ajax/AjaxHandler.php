<?php
namespace OVSI\Ajax;

class AjaxHandler {
    public function __construct() {
       // add_action('wp_ajax_get_product_variations', [$this, 'get_product_variations']);
        //add_action('wp_ajax_nopriv_get_product_variations', [$this, 'get_product_variations']);
    }

    /**
     * Backend/AJAX: Retrieves product variations data
     */
    public function get_product_variations() {
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        
        if (!$product_id) {
            wp_send_json_error('No product ID provided');
            return;
        }
        
        $product = wc_get_product($product_id);
        
        if (!$product || !$product->is_type('variable')) {
            wp_send_json_error('Invalid product');
            return;
        }
        
        $available_variations = $product->get_available_variations();
        wp_send_json_success($available_variations);
    }
}