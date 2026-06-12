<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

// Autoloader — maps App\Foo\Bar → app/Foo/Bar.php
spl_autoload_register(function (string $class): void {
    $file = BASE_PATH . '/' . str_replace(['App\\', '\\'], ['app/', '/'], $class) . '.php';
    if (file_exists($file)) require_once $file;
});

// Error handling
$cfg = require BASE_PATH . '/app/Config/app.php';
if ($cfg['debug']) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(0);
}

new App\Core\App();
