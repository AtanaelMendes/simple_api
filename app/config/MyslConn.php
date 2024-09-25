<?php

namespace App\Config;

class MySqlConn
{
    private static $instance = null;
    private $connection;
    private $host;
    private $database;
    private $username;
    private $password;
    private $port;

    /**
     * Construtor privado para implementar Singleton
     */
    private function __construct()
    {
        // Carregar variáveis de ambiente
        Environment::load();
        
        $this->host = Environment::get('DB_HOST', 'localhost');
        $this->database = Environment::get('DB_DATABASE', 'tickflow');
        $this->username = Environment::get('DB_USERNAME', 'root');
        $this->password = Environment::get('DB_PASSWORD', '');
        $this->port = Environment::get('DB_PORT', '3306');

        $this->connect();
    }

    /**
     * Obtém a instância única da conexão (Singleton)
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
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
            $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->database};charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];

            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch (PDOException $e) {
            throw new Exception("Erro na conexão com o banco de dados: " . $e->getMessage());
        }
    }
    
    /**
     * Obtém a conexão PDO (alias para getConnection para compatibilidade)
     */
    public function getPdo()
    {
        return $this->connection;
    }

    /**
     * Executa uma query SQL
     */
    public function query($sql, $params = [])
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new Exception("Erro ao executar query: " . $e->getMessage());
        }
    }

    /**
     * INSERT - Insere dados na tabela
     */
    public function insert($table, $data)
    {
        $columns = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        
        $stmt = $this->query($sql, $data);
        
        return [
            'success' => true,
            'id' => $this->connection->lastInsertId(),
            'affected_rows' => $stmt->rowCount()
        ];
    }

    /**
     * SELECT - Busca dados na tabela
     */
    public function select($table, $conditions = [], $columns = '*', $orderBy = null, $limit = null)
    {
        $sql = "SELECT {$columns} FROM {$table}";
        $params = [];

        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $column => $value) {
                $whereClause[] = "{$column} = :{$column}";
                $params[$column] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }

        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }

        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }

        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * SELECT - Busca um único registro
     */
    public function selectOne($table, $conditions = [], $columns = '*')
    {
        $result = $this->select($table, $conditions, $columns, null, 1);
        return $result ? $result[0] : null;
    }

    /**
     * UPDATE - Atualiza dados na tabela
     */
    public function update($table, $data, $conditions)
    {
        $setClause = [];
        $params = [];

        foreach ($data as $column => $value) {
            $setClause[] = "{$column} = :set_{$column}";
            $params["set_{$column}"] = $value;
        }

        $whereClause = [];
        foreach ($conditions as $column => $value) {
            $whereClause[] = "{$column} = :where_{$column}";
            $params["where_{$column}"] = $value;
        }

        $sql = "UPDATE {$table} SET " . implode(', ', $setClause) . " WHERE " . implode(' AND ', $whereClause);
        
        $stmt = $this->query($sql, $params);
        
        return [
            'success' => true,
            'affected_rows' => $stmt->rowCount()
        ];
    }

    /**
     * DELETE - Remove dados da tabela
     */
    public function delete($table, $conditions)
    {
        $whereClause = [];
        $params = [];

        foreach ($conditions as $column => $value) {
            $whereClause[] = "{$column} = :{$column}";
            $params[$column] = $value;
        }

        $sql = "DELETE FROM {$table} WHERE " . implode(' AND ', $whereClause);
        
        $stmt = $this->query($sql, $params);
        
        return [
            'success' => true,
            'affected_rows' => $stmt->rowCount()
        ];
    }

    /**
     * Conta registros na tabela
     */
    public function count($table, $conditions = [])
    {
        $sql = "SELECT COUNT(*) as total FROM {$table}";
        $params = [];

        if (!empty($conditions)) {
            $whereClause = [];
            foreach ($conditions as $column => $value) {
                $whereClause[] = "{$column} = :{$column}";
                $params[$column] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $whereClause);
        }

        $stmt = $this->query($sql, $params);
        $result = $stmt->fetch();
        return (int) $result['total'];
    }

    /**
     * Executa query personalizada e retorna resultado
     */
    public function customQuery($sql, $params = [])
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
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
     * Desfaz uma transação
     */
    public function rollback()
    {
        return $this->connection->rollback();
    }

    /**
     * Verifica se está em uma transação
     */
    public function inTransaction()
    {
        return $this->connection->inTransaction();
    }

    /**
     * Teste de conexão
     */
    public function testConnection()
    {
        try {
            $stmt = $this->connection->query("SELECT 1 as test");
            $result = $stmt->fetch();
            
            return [
                'success' => true,
                'message' => 'Conexão estabelecida com sucesso',
                'host' => $this->host,
                'database' => $this->database,
                'test_result' => $result['test']
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Erro na conexão: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Obtém informações do banco
     */
    public function getDatabaseInfo()
    {
        try {
            $version = $this->connection->query("SELECT VERSION() as version")->fetch();
            $charset = $this->connection->query("SELECT @@character_set_database as charset")->fetch();
            
            return [
                'host' => $this->host,
                'database' => $this->database,
                'port' => $this->port,
                'version' => $version['version'],
                'charset' => $charset['charset']
            ];
        } catch (Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Destrutor - fecha a conexão
     */
    public function __destruct()
    {
        $this->connection = null;
    }

    /**
     * Previne clonagem da instância
     */
    private function __clone() {}

    /**
     * Previne deserialização da instância
     */
    private function __wakeup() {}
}