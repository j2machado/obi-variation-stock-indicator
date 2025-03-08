<?php
namespace OVSI\Compatibility;

interface HandlerInterface {
    public function is_active(): bool;
    public function init(): void;
}