<?php
/**
 * CONFIGURAÇÃO DE CAMINHOS DO PROJETO
 * Este arquivo define todos os caminhos absolutos e URLs do sistema
 */

// Caminhos absolutos (filesystem)
define('ROOT_PATH', dirname(__DIR__));
define('CONFIG_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'config');
define('SRC_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'src');
define('PUBLIC_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'public');
define('API_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'api');
define('ASSETS_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'assets');
define('VIEWS_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'views');
define('UPLOADS_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'uploads');
define('REPORTS_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'relatorios');

// URLs (web)
define('BASE_URL', 'http://localhost/2025-TCC');
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOADS_URL', BASE_URL . '/uploads');

/**
 * AUTOLOAD PARA CLASSES
 * Carrega automaticamente classes da pasta src/classes
 */
spl_autoload_register(function ($class) {
    $file = SRC_PATH . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

/**
 * Função auxiliar para debug de caminhos
 */
if (!function_exists('debug_paths')) {
    function debug_paths() {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            echo "<pre>";
            echo "ROOT_PATH: " . ROOT_PATH . "\n";
            echo "CONFIG_PATH: " . CONFIG_PATH . "\n";
            echo "PUBLIC_PATH: " . PUBLIC_PATH . "\n";
            echo "Arquivo atual: " . __FILE__ . "\n";
            echo "</pre>";
        }
    }
}
?>
