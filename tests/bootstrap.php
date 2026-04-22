<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

if (!class_exists('Mage_Core_Helper_Abstract')) {
    eval('class Mage_Core_Helper_Abstract { public function __($s) { return $s; } }');
}
if (!class_exists('Mage')) {
    eval('class Mage {
        public static function helper($name) { return new \stdClass(); }
        public static function getStoreConfig($path, $store = null) { return null; }
        public static function getStoreConfigFlag($path, $store = null) { return false; }
        public static function getModel($name, $args = []) { return new \stdClass(); }
    }');
}

spl_autoload_register(function ($class) {
    if (strpos($class, 'Mageaustralia_Preorder_') !== 0) {
        return;
    }
    $rel = str_replace('_', '/', $class) . '.php';
    $file = __DIR__ . '/../src/app/code/local/' . $rel;
    if (is_file($file)) {
        require_once $file;
    }
});
