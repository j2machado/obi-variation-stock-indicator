<?php
namespace OVSI\Compatibility\Handlers\Plugins\WooCommerce;

use OVSI\Compatibility\AbstractHandler;

class QuickViewbyKestrel extends AbstractHandler {
    public function is_active(): bool {
        return $this->is_plugin_active('woocommerce-quick-view/woocommerce-quick-view.php');
    }

    public function init(): void {
        // Hook into the OVSI Assets class enqueue process
        add_filter('ovsi_should_enqueue_frontend_scripts', array($this, 'modify_script_enqueue'), 10, 1);
    }

    /**
     * Modify the script enqueue condition to include shop pages when Quick View is active
     *
     * @param bool $should_enqueue Current enqueue decision
     * @return bool Modified enqueue decision
     */
    public function modify_script_enqueue($should_enqueue): bool {
        // If it's already supposed to be enqueued, keep it that way
        if ($should_enqueue) {
            return true;
        }

        // Add enqueue for shop pages when Quick View is active
        return is_shop() || is_product_category() || is_product_tag();
    }
}