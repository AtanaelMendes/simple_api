<?php

namespace App\Core;

use App\Config\Environment;

/**
 * Classe Database global singleton para conexões MySQL/PostgreSQL
 * Permite acesso global sem reinstanciação em todos os models
 * Detecta automaticamente o tipo de conexão via DB_CONNECTION no .env
 */
class Database
{
    private static $instance = null;
    private $connection;
    private $host;
    private $database;
    private $username;
    private $password;
    private $port;
    private $dbConnection;

    /**
     * Construtor privado para implementar Singleton
     */
    private function __construct()
    {
        // Carregar variáveis de ambiente
        if (!class_exists('Environment')) {
            require_once __DIR__ . '/../config/Environment.php';
        }

        Environment::load();

        // Check if all required environment variables are set
        if (!Environment::has('DB_HOST')) {
            throw new \Exception("DB_HOST não está definido no arquivo .env");
        }
        if (!Environment::has('DB_DATABASE')) {
            throw new \Exception("DB_DATABASE não está definido no arquivo .env");
        }
        if (!Environment::has('DB_USERNAME')) {
            throw new \Exception("DB_USERNAME não está definido no arquivo .env");
        }
        if (!Environment::has('DB_PASSWORD')) {
            throw new \Exception("DB_PASSWORD não está definido no arquivo .env");
        }
        if (!Environment::has('DB_PORT')) {
            throw new \Exception("DB_PORT não está definido no arquivo .env");
        }
        
        // Get values from environment without defaults
        $this->host = Environment::get('DB_HOST');
        $this->database = Environment::get('DB_DATABASE');
        $this->username = Environment::get('DB_USERNAME');
        $this->password = Environment::get('DB_PASSWORD');
        $this->port = Environment::get('DB_PORT');
        $this->dbConnection = Environment::get('DB_CONNECTION', 'mysql');

        $this->connect();
    }

    /**
     * Previne clonagem da instância
     */
    private function __clone() {}

    /**
     * Previne unserialize da instância
     */
    public function __wakeup() {}

    /**
     * Método estático para obter instância única
     * @param bool $refresh Force a refresh of the connection with current env values
     * @return Database
     */
    public static function getInstance($refresh = false)
    {
        if (self::$instance === null || $refresh) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Conecta ao banco de dados
     */
    private function connect()
    {
        try {
            if ($this->dbConnection === 'postgresql') {
                // PostgreSQL
                $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->database};";
                
                $options = [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                ];
            } else {
                // MySQL (padrão)
                $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->database};charset=utf8mb4";
                
                $options = [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES => false,
                    \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ];
            }

            $this->connection = new \PDO($dsn, $this->username, $this->password, $options);
            
        } catch (\PDOException $e) {
            throw new \Exception("Erro de conexão com banco de dados ({$this->dbConnection}): " . $e->getMessage());
        }
    }

    /**
     * Retorna a conexão PDO
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Executa uma query SELECT e retorna os resultados
     */
    public function select($query, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            throw new \Exception("Erro na consulta SELECT: " . $e->getMessage());
        }
    }

    /**
     * Executa uma query INSERT e retorna o ID inserido
     */
    public function insert($query, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $this->connection->lastInsertId();
        } catch (\PDOException $e) {
            throw new \Exception("Erro na operação INSERT: " . $e->getMessage());
        }
    }

    /**
     * Executa uma query UPDATE e retorna o número de linhas afetadas
     */
    public function update($query, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (\PDOException $e) {
            throw new \Exception("Erro na operação UPDATE: " . $e->getMessage());
        }
    }

    /**
     * Executa uma query DELETE e retorna o número de linhas afetadas
     */
    public function delete($query, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (\PDOException $e) {
            throw new \Exception("Erro na operação DELETE: " . $e->getMessage());
        }
    }

    /**
     * Executa qualquer query (CREATE, ALTER, etc.)
     */
    public function execute($query, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($query);
            return $stmt->execute($params);
        } catch (\PDOException $e) {
            throw new \Exception("Erro na execução da query: " . $e->getMessage());
        }
    }

    /**
     * Inicia uma transação
     */
    public function beginTransaction()
    {
        return $this->connection->beginTransaction();
    }

    /**
     * Confirma uma transação
     */
    public function commit()
    {
        return $this->connection->commit();
    }

    /**
     * Cancela uma transação
     */
    public function rollback()
    {
        return $this->connection->rollBack();
    }

    /**
     * Verifica se há uma transação ativa
     */
    public function inTransaction()
    {
        return $this->connection->inTransaction();
    }

    /**
     * Prepara uma query para execução
     */
    public function prepare($query)
    {
        return $this->connection->prepare($query);
    }

    /**
     * Retorna informações sobre a conexão
     */
    public function getConnectionInfo()
    {
        return [
            'host' => $this->host,
            'database' => $this->database,
            'username' => $this->username,
            'port' => $this->port,
            'connected' => $this->connection !== null
        ];
    }

    /**
     * Retorna informações detalhadas do banco
     */
    public function getDatabaseInfo()
    {
        try {
            $version = $this->connection->query("SELECT VERSION() as version")->fetch();
            $charset = $this->connection->query("SELECT @@character_set_database as charset")->fetch();
            
            return [
                'version' => $version['version'],
                'charset' => $charset['charset'],
                'database' => $this->database,
                'host' => $this->host,
                'port' => $this->port
            ];
        } catch (\PDOException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Lista todas as tabelas do banco
     */
    public function showTables()
    {
        try {
            $query = "SHOW TABLES";
            $stmt = $this->connection->query($query);
            return $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\PDOException $e) {
            throw new \Exception("Erro ao listar tabelas: " . $e->getMessage());
        }
    }

    /**
     * Descreve a estrutura de uma tabela
     */
    public function describeTable($tableName)
    {
        try {
            $query = "DESCRIBE `{$tableName}`";
            return $this->select($query);
        } catch (\PDOException $e) {
            throw new \Exception("Erro ao descrever tabela {$tableName}: " . $e->getMessage());
        }
    }

    /**
     * Testa a conexão com o banco
     */
    public function testConnection()
    {
        try {
            $result = $this->connection->query("SELECT 1 as test")->fetch();
            return [
                'success' => true,
                'message' => 'Conexão com banco de dados OK',
                'test_result' => $result['test']
            ];
        } catch (\PDOException $e) {
            return [
                'success' => false,
                'message' => 'Erro na conexão: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Método para fechar conexão (opcional)
     */
    public function close()
    {
        $this->connection = null;
    }
}
