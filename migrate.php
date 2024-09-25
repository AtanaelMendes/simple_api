<?php

/**
 * Sistema de Migrations - Comando CLI
 * 
 * Uso:
 * php migrate.php migrate          - Executar todas as migrations pendentes
 * php migrate.php rollback         - Reverter última migration
 * php migrate.php rollback 2       - Reverter últimas 2 migrations
 * php migrate.php status           - Mostrar status das migrations
 * php migrate.php make nome_table  - Criar nova migration
 */

// Configurar autoload
spl_autoload_register(function ($class_name) {
    $class_path = str_replace('\\', DIRECTORY_SEPARATOR, $class_name);
    $file_path = __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . str_replace('App' . DIRECTORY_SEPARATOR, '', $class_path) . '.php';
    
    if (file_exists($file_path)) {
        require_once $file_path;
    }
});

use App\Core\Migration;

// Verificar argumentos
$command = $argv[1] ?? null;
$argument = $argv[2] ?? null;

if (!$command) {
    echo "❌ Comando não especificado!\n\n";
    showHelp();
    exit(1);
}

try {
    $migration = new Migration();
    
    switch ($command) {
        case 'migrate':
            $migration->migrate();
            break;
            
        case 'rollback':
            $steps = is_numeric($argument) ? (int)$argument : 1;
            $migration->rollback($steps);
            break;
            
        case 'status':
            $migration->status();
            break;
            
        case 'make':
            if (!$argument) {
                echo "❌ Nome da migration não especificado!\n";
                echo "Uso: php migrate.php make nome_da_migration\n";
                exit(1);
            }
            $migration->make($argument);
            break;
            
        default:
            echo "❌ Comando '{$command}' não reconhecido!\n\n";
            showHelp();
            exit(1);
    }
    
} catch (Exception $e) {
    echo "❌ Erro: {$e->getMessage()}\n";
    exit(1);
}

function showHelp()
{
    echo "🔧 Sistema de Migrations - Comandos Disponíveis:\n\n";
    echo "  migrate                    Executar todas as migrations pendentes\n";
    echo "  rollback [steps]          Reverter migrations (padrão: 1 step)\n";
    echo "  status                    Mostrar status das migrations\n";
    echo "  make <nome>               Criar nova migration\n\n";
    echo "Exemplos:\n";
    echo "  php migrate.php migrate\n";
    echo "  php migrate.php rollback\n";
    echo "  php migrate.php rollback 2\n";
    echo "  php migrate.php status\n";
    echo "  php migrate.php make create_users_table\n\n";
}
?>
