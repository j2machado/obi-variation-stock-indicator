<?php
namespace OVSI\Compatibility;

abstract class AbstractHandler implements HandlerInterface {
    protected function is_plugin_active($plugin): bool {
        return in_array($plugin, apply_filters('active_plugins', get_option('active_plugins')));
    }

    protected function is_theme_active($theme): bool {
        return get_template() === $theme;
    }

    protected function modify_script_dependency($handle, $dep) {
        global $wp_scripts;
        if (!isset($wp_scripts->registered[$handle])) return;
        
        if (!in_array($dep, $wp_scripts->registered[$handle]->deps)) {
            $wp_scripts->registered[$handle]->deps[] = $dep;
        }
    }
}