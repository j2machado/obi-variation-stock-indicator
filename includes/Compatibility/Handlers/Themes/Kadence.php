<?php
namespace OVSI\Compatibility\Handlers\Themes;

use OVSI\Compatibility\AbstractHandler;

class Kadence extends AbstractHandler {
    public function is_active(): bool {
        //return $this->is_theme_active('kadence');
        return false;   
    }

    public function init(): void {
        // Only add styles when our frontend script is enqueued
        add_action('wp_enqueue_scripts', [$this, 'add_compatibility_styles'], 20);
    }

    /**
     * Add specific styles to handle Kadence's select option overrides
     */
    public function add_compatibility_styles(): void {
        $css = "
            .variations select option:disabled {
                color: #999 !important; /* Light gray */
                opacity: 0.6;
                font-style: italic;
            }
        ";

        wp_add_inline_style('ovsi-frontend', $css);
    }
}