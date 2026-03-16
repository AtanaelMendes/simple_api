<?php

namespace App\Core;

use App\Core\Database;
use PDO;

/**
 * Sistema de Seeders - Classe Core
 * Gerencia a execução de seeders para popular o banco de dados
 */
class Seeder
{
    private $db;
    private $seedersPath;
    private $tableName = 'seeders';
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->seedersPath = __DIR__ . '/../../database/seeds';
        
        // Garantir que a tabela de controle existe
        $this->ensureSeedersTable();
    }
    
    /**
     * Cria a tabela de controle de seeders se não existir
     */
    private function ensureSeedersTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->tableName}` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `seeder` VARCHAR(255) NOT NULL,
            `batch` INT NOT NULL,
            `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_seeder` (`seeder`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $this->db->execute($sql);
    }
    
    /**
     * Executa todos os seeders pendentes
     */
    public function seed()
    {
        echo "🌱 Iniciando seeders...\n\n";
        
        $seeders = $this->getPendingSeeders();
        
        if (empty($seeders)) {
            echo "✅ Nenhum seeder pendente para executar.\n";
            return;
        }
        
        $batch = $this->getNextBatchNumber();
        $executed = 0;
        
        foreach ($seeders as $seeder) {
            try {
                echo "🌱 Executando: {$seeder}\n";
                
                $seederFile = $this->seedersPath . '/' . $seeder;
                $seederData = require $seederFile;
                
                if (!is_callable($seederData)) {
                    throw new \Exception("Seeder deve retornar uma função callable");
                }
                
                // Executar o seeder
                $seederData($this->db);
                
                // Registrar execução
                $this->markAsExecuted($seeder, $batch);
                
                echo "   ✅ Concluído\n\n";
                $executed++;
                
            } catch (\Exception $e) {
                echo "   ❌ Erro: " . $e->getMessage() . "\n\n";
                throw $e;
            }
        }
        
        echo "✅ {$executed} seeder(s) executado(s) com sucesso!\n";
    }
    
    /**
     * Executa um seeder específico
     */
    public function run($seederName)
    {
        echo "🌱 Executando seeder: {$seederName}\n\n";
        
        // Adicionar extensão .php se não tiver
        if (substr($seederName, -4) !== '.php') {
            $seederName .= '.php';
        }
        
        $seederFile = $this->seedersPath . '/' . $seederName;
        
        if (!file_exists($seederFile)) {
            throw new \Exception("Seeder não encontrado: {$seederName}");
        }
        
        try {
            $seederData = require $seederFile;
            
            if (!is_callable($seederData)) {
                throw new \Exception("Seeder deve retornar uma função callable");
            }
            
            // Executar o seeder
            $seederData($this->db);
            
            // Registrar execução se ainda não foi executado
            if (!$this->wasExecuted($seederName)) {
                $batch = $this->getNextBatchNumber();
                $this->markAsExecuted($seederName, $batch);
            }
            
            echo "✅ Seeder executado com sucesso!\n";
            
        } catch (\Exception $e) {
            echo "❌ Erro: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
    
    /**
     * Limpa os registros de seeders executados
     */
    public function reset()
    {
        echo "🗑️  Limpando registros de seeders...\n\n";
        
        $sql = "TRUNCATE TABLE `{$this->tableName}`";
        $this->db->execute($sql);
        
        echo "✅ Registros limpos! Os seeders podem ser executados novamente.\n";
    }
    
    /**
     * Mostra o status dos seeders
     */
    public function status()
    {
        echo "📊 Status dos Seeders\n";
        echo str_repeat("=", 60) . "\n\n";
        
        $allSeeders = $this->getAllSeeders();
        $executedSeeders = $this->getExecutedSeeders();
        
        if (empty($allSeeders)) {
            echo "⚠️  Nenhum seeder encontrado em: {$this->seedersPath}\n";
            return;
        }
        
        echo "Total de seeders: " . count($allSeeders) . "\n";
        echo "Executados: " . count($executedSeeders) . "\n";
        echo "Pendentes: " . (count($allSeeders) - count($executedSeeders)) . "\n\n";
        
        foreach ($allSeeders as $seeder) {
            $status = in_array($seeder, $executedSeeders) ? '✅ Executado' : '⏳ Pendente';
            echo "{$status} - {$seeder}\n";
        }
    }
    
    /**
     * Retorna todos os arquivos de seeders
     */
    private function getAllSeeders()
    {
        if (!is_dir($this->seedersPath)) {
            return [];
        }
        
        $files = scandir($this->seedersPath);
        $seeders = [];
        
        foreach ($files as $file) {
            if (substr($file, -4) === '.php') {
                $seeders[] = $file;
            }
        }
        
        sort($seeders);
        return $seeders;
    }
    
    /**
     * Retorna seeders já executados
     */
    private function getExecutedSeeders()
    {
        $sql = "SELECT seeder FROM `{$this->tableName}` ORDER BY id";
        $result = $this->db->select($sql);
        
        return array_column($result, 'seeder');
    }
    
    /**
     * Retorna seeders pendentes
     */
    private function getPendingSeeders()
    {
        $allSeeders = $this->getAllSeeders();
        $executedSeeders = $this->getExecutedSeeders();
        
        return array_diff($allSeeders, $executedSeeders);
    }
    
    /**
     * Verifica se um seeder já foi executado
     */
    private function wasExecuted($seeder)
    {
        $sql = "SELECT COUNT(*) as count FROM `{$this->tableName}` WHERE seeder = :seeder";
        $result = $this->db->select($sql, ['seeder' => $seeder]);
        
        return $result[0]['count'] > 0;
    }
    
    /**
     * Marca um seeder como executado
     */
    private function markAsExecuted($seeder, $batch)
    {
        $sql = "INSERT INTO `{$this->tableName}` (seeder, batch) VALUES (:seeder, :batch)";
        $this->db->insert($sql, [
            'seeder' => $seeder,
            'batch' => $batch
        ]);
    }
    
    /**
     * Obtém o próximo número de batch
     */
    private function getNextBatchNumber()
    {
        $sql = "SELECT MAX(batch) as max_batch FROM `{$this->tableName}`";
        $result = $this->db->select($sql);
        
        $maxBatch = $result[0]['max_batch'] ?? 0;
        return $maxBatch + 1;
    }
}
