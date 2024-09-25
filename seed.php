<?php

/**
 * Sistema de Seeders - Comando CLI
 * 
 * Uso:
 * php seed.php seed            - Executar todos os seeders pendentes
 * php seed.php run nome        - Executar um seeder específico
 * php seed.php reset           - Limpar registros de seeders executados
 * php seed.php status          - Mostrar status dos seeders
 */

// Configurar autoload
spl_autoload_register(function ($class_name) {
    $class_path = str_replace('\\', DIRECTORY_SEPARATOR, $class_name);
    $file_path = __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . str_replace('App' . DIRECTORY_SEPARATOR, '', $class_path) . '.php';
    
    if (file_exists($file_path)) {
        require_once $file_path;
    }
});

use App\Core\Seeder;

// Verificar argumentos
$command = $argv[1] ?? null;
$argument = $argv[2] ?? null;

if (!$command) {
    echo "❌ Comando não especificado!\n\n";
    showHelp();
    exit(1);
}

try {
    $seeder = new Seeder();
    
    switch ($command) {
        case 'seed':
            $seeder->seed();
            break;
            
        case 'run':
            if (!$argument) {
                echo "❌ Nome do seeder não especificado!\n";
                echo "Uso: php seed.php run nome_do_seeder\n";
                exit(1);
            }
            $seeder->run($argument);
            break;
            
        case 'reset':
            $seeder->reset();
            break;
            
        case 'status':
            $seeder->status();
            break;
            
        default:
            echo "❌ Comando desconhecido: {$command}\n\n";
            showHelp();
            exit(1);
    }
    
} catch (Exception $e) {
    echo "\n❌ Erro fatal: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . "\n";
    echo "Linha: " . $e->getLine() . "\n\n";
    exit(1);
}

/**
 * Exibe ajuda do comando
 */
function showHelp()
{
    echo "Sistema de Seeders - Comandos disponíveis:\n\n";
    echo "  php seed.php seed            - Executar todos os seeders pendentes\n";
    echo "  php seed.php run <nome>      - Executar um seeder específico\n";
    echo "  php seed.php reset           - Limpar registros de seeders executados\n";
    echo "  php seed.php status          - Mostrar status dos seeders\n\n";
    echo "Exemplos:\n";
    echo "  php seed.php seed\n";
    echo "  php seed.php run ProductsSeeder\n";
    echo "  php seed.php status\n\n";
}
