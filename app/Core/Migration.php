<?php

namespace App\Core;

use App\Core\Database;
use App\Config\Environment;

/**
 * Classe Migration - Sistema de migrações estilo Laravel
 * Permite executar e reverter migrations do banco de dados
 */
class Migration
{
    private $db;
    private $migrationsPath;
    private $dbConnection;

    public function __construct()
    {
        // Force refresh to use current .env settings
        $this->db = Database::getInstance(true);
        $this->migrationsPath = __DIR__ . '/../../database/migrations';
        $this->dbConnection = Environment::get('DB_CONNECTION', 'mysql');
        $this->createMigrationsTable();
    }

    /**
     * Criar tabela de controle de migrations
     */
    private function createMigrationsTable()
    {
        try {
            if ($this->dbConnection === 'postgresql') {
                // PostgreSQL
                $sql = "CREATE TABLE IF NOT EXISTS migrations (
                    id SERIAL PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL,
                    batch INT NOT NULL,
                    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )";
            } else {
                // MySQL
                $sql = "CREATE TABLE IF NOT EXISTS migrations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    migration VARCHAR(255) NOT NULL,
                    batch INT NOT NULL,
                    executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
            }
            
            $this->db->execute($sql);
        } catch (Exception $e) {
            // Tabela pode já existir, ignorar erro
        }
    }

    /**
     * Executar todas as migrations pendentes
     */
    public function migrate()
    {
        echo "🚀 Executando migrations...\n\n";
        
        $executedMigrations = $this->getExecutedMigrations();
        $migrationFiles = $this->getMigrationFiles();
        $batch = $this->getNextBatch();
        
        $executed = 0;
        
        foreach ($migrationFiles as $file) {
            $migrationName = pathinfo($file, PATHINFO_FILENAME);
            
            if (!in_array($migrationName, $executedMigrations)) {
                echo "📦 Executando migration: {$migrationName}\n";
                
                try {
                    $migration = include $file;
                    
                    if (isset($migration['up']) && is_callable($migration['up'])) {
                        $migration['up']($this->db);
                        
                        // Registrar migration como executada
                        $this->db->insert(
                            "INSERT INTO migrations (migration, batch) VALUES (?, ?)",
                            [$migrationName, $batch]
                        );
                        
                        $executed++;
                        echo "   ✅ Concluída!\n\n";
                    } else {
                        echo "   ❌ Erro: Migration não tem função 'up' válida\n\n";
                    }
                    
                } catch (Exception $e) {
                    echo "   ❌ Erro ao executar migration: {$e->getMessage()}\n\n";
                    break;
                }
            }
        }
        
        if ($executed === 0) {
            echo "ℹ️ Nenhuma migration pendente encontrada.\n";
        } else {
            echo "🎉 {$executed} migration(s) executada(s) com sucesso!\n";
        }
    }

    /**
     * Reverter a última migration ou um batch específico
     */
    public function rollback($steps = 1)
    {
        echo "⏪ Revertendo migrations...\n\n";
        
        $batches = $this->db->select(
            "SELECT DISTINCT batch FROM migrations ORDER BY batch DESC LIMIT ?",
            [$steps]
        );
        
        if (empty($batches)) {
            echo "ℹ️ Nenhuma migration para reverter.\n";
            return;
        }
        
        $rollbackCount = 0;
        
        foreach ($batches as $batchData) {
            $batch = $batchData['batch'];
            
            $migrations = $this->db->select(
                "SELECT migration FROM migrations WHERE batch = ? ORDER BY id DESC",
                [$batch]
            );
            
            foreach ($migrations as $migrationData) {
                $migrationName = $migrationData['migration'];
                $migrationFile = $this->migrationsPath . "/{$migrationName}.php";
                
                echo "📦 Revertendo migration: {$migrationName}\n";
                
                if (file_exists($migrationFile)) {
                    try {
                        $migration = include $migrationFile;
                        
                        if (isset($migration['down']) && is_callable($migration['down'])) {
                            $migration['down']($this->db);
                            
                            // Remover registro da migration
                            $this->db->delete(
                                "DELETE FROM migrations WHERE migration = ?",
                                [$migrationName]
                            );
                            
                            $rollbackCount++;
                            echo "   ✅ Revertida!\n\n";
                        } else {
                            echo "   ❌ Erro: Migration não tem função 'down' válida\n\n";
                        }
                        
                    } catch (Exception $e) {
                        echo "   ❌ Erro ao reverter migration: {$e->getMessage()}\n\n";
                    }
                } else {
                    echo "   ❌ Arquivo de migration não encontrado\n\n";
                }
            }
        }
        
        echo "🎉 {$rollbackCount} migration(s) revertida(s) com sucesso!\n";
    }

    /**
     * Mostrar status das migrations
     */
    public function status()
    {
        echo "📋 Status das Migrations:\n\n";
        
        $executedMigrations = $this->getExecutedMigrations();
        $migrationFiles = $this->getMigrationFiles();
        
        if (empty($migrationFiles)) {
            echo "ℹ️ Nenhuma migration encontrada.\n";
            return;
        }
        
        foreach ($migrationFiles as $file) {
            $migrationName = pathinfo($file, PATHINFO_FILENAME);
            $status = in_array($migrationName, $executedMigrations) ? '✅ Executada' : '⏳ Pendente';
            echo "  {$status} - {$migrationName}\n";
        }
        
        echo "\n";
    }

    /**
     * Criar uma nova migration
     */
    public function make($name)
    {
        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_{$name}.php";
        $filepath = $this->migrationsPath . "/{$filename}";
        
        // Criar template específico para o banco de dados
        $template = $this->generateMigrationTemplate($name);
        
        file_put_contents($filepath, $template);
        echo "✅ Migration criada: {$filename}\n";
        echo "📝 Banco de dados: {$this->dbConnection}\n";
        
        return $filepath;
    }

    /**
     * Gerar template de migration de acordo com o banco de dados
     */
    private function generateMigrationTemplate($name)
    {
        $date = date('Y-m-d H:i:s');
        
        if ($this->dbConnection === 'postgresql') {
            return "<?php

/**
 * Migration: {$name}
 * Data: {$date}
 * Banco: PostgreSQL
 * Descrição: [Descreva o que esta migration faz]
 */

return [
    'description' => '{$name}',
    
    'up' => function(\$db) {
        // Código para executar a migration
        // Exemplo para PostgreSQL:
        // \$sql = \"CREATE TABLE example (
        //     id SERIAL PRIMARY KEY,
        //     name VARCHAR(255) NOT NULL,
        //     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        // )\";
        // \$db->execute(\$sql);
        // echo \"✅ Migration executada com sucesso!\\n\";
    },
    
    'down' => function(\$db) {
        // Código para reverter a migration
        // Exemplo:
        // \$sql = \"DROP TABLE IF EXISTS example\";
        // \$db->execute(\$sql);
        // echo \"✅ Migration revertida com sucesso!\\n\";
    }
];";
        } else {
            // MySQL
            return "<?php

/**
 * Migration: {$name}
 * Data: {$date}
 * Banco: MySQL
 * Descrição: [Descreva o que esta migration faz]
 */

return [
    'description' => '{$name}',
    
    'up' => function(\$db) {
        // Código para executar a migration
        // Exemplo para MySQL:
        // \$sql = \"CREATE TABLE IF NOT EXISTS example (
        //     id INT AUTO_INCREMENT PRIMARY KEY,
        //     name VARCHAR(255) NOT NULL,
        //     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        // ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4\";
        // \$db->execute(\$sql);
        // echo \"✅ Migration executada com sucesso!\\n\";
    },
    
    'down' => function(\$db) {
        // Código para reverter a migration
        // Exemplo:
        // \$sql = \"DROP TABLE IF EXISTS example\";
        // \$db->execute(\$sql);
        // echo \"✅ Migration revertida com sucesso!\\n\";
    }
];";
        }
    }

    /**
     * Obter migrations já executadas
     */
    private function getExecutedMigrations()
    {
        try {
            $results = $this->db->select("SELECT migration FROM migrations");
            return array_column($results, 'migration');
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Obter arquivos de migration
     */
    private function getMigrationFiles()
    {
        if (!is_dir($this->migrationsPath)) {
            return [];
        }
        
        $files = glob($this->migrationsPath . '/*.php');
        sort($files);
        
        return $files;
    }

    /**
     * Obter próximo número de batch
     */
    private function getNextBatch()
    {
        try {
            $result = $this->db->select("SELECT MAX(batch) as max_batch FROM migrations");
            return ($result[0]['max_batch'] ?? 0) + 1;
        } catch (Exception $e) {
            return 1;
        }
    }
}
