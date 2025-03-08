<?php
namespace OVSI\Compatibility;

class CompatibilityManager {
    private $handlers = [];
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->discover_handlers();
    }

    private function discover_handlers() {
        $handler_dir = OVSI_PLUGIN_DIR . 'includes/Compatibility/Handlers';
        
        if (!is_dir($handler_dir)) {
            return;
        }

        // Recursively get all PHP files in handlers directory and subdirectories
        $handler_files = $this->get_handler_files($handler_dir);
        
        foreach ($handler_files as $file) {
            // Convert path to namespace format
            $relative_path = str_replace(
                [$handler_dir, '/', '.php'],
                ['OVSI\\Compatibility\\Handlers', '\\', ''],
                $file
            );
            
            $class_name = $relative_path;
            
            if (class_exists($class_name)) {
                $reflection = new \ReflectionClass($class_name);
                if ($reflection->implementsInterface(HandlerInterface::class)) {
                    $handler = new $class_name();
                    if ($handler->is_active()) {
                        $handler->init();
                        $this->handlers[basename($file, '.php')] = $handler;
                    }
                }
            }
        }
    }

    private function get_handler_files($dir): array {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        
        return $files;
    }

    /**
     * Get all active handlers
     *
     * @return array
     */
    public function get_active_handlers(): array {
        return $this->handlers;
    }
}
