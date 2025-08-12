<?php
/**
 * Database Connection and Management Class
 * Handles all database operations with security and error handling
 */

class Database {
    private static $instance = null;
    private $connection;
    private $host;
    private $database;
    private $username;
    private $password;
    
    private function __construct() {
        $this->host = DB_HOST;
        $this->database = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;
        
        $this->connect();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->database};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            
            if (DEBUG_MODE) {
                die("Database connection failed: " . $e->getMessage());
            } else {
                die("Database connection failed. Please try again later.");
            }
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Execute a prepared statement with parameters
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query error: " . $e->getMessage() . " | SQL: " . $sql);
            throw new Exception("Database query failed");
        }
    }
    
    /**
     * Fetch a single row
     */
    public function fetch($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Fetch all rows
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get single value
     */
    public function fetchColumn($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn();
    }
    
    /**
     * Insert record and return ID
     */
    public function insert($table, $data) {
        $columns = implode(',', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, $data);
        
        return $this->connection->lastInsertId();
    }
    
    /**
     * Update records
     */
    public function update($table, $data, $where, $whereParams = []) {
        $setClause = [];
        foreach (array_keys($data) as $key) {
            $setClause[] = "{$key} = :{$key}";
        }
        $setClause = implode(', ', $setClause);
        
        $sql = "UPDATE {$table} SET {$setClause} WHERE {$where}";
        $params = array_merge($data, $whereParams);
        
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * Delete records
     */
    public function delete($table, $where, $whereParams = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $whereParams);
        return $stmt->rowCount();
    }
    
    /**
     * Check if record exists
     */
    public function exists($table, $where, $whereParams = []) {
        $sql = "SELECT 1 FROM {$table} WHERE {$where} LIMIT 1";
        $result = $this->fetch($sql, $whereParams);
        return !empty($result);
    }
    
    /**
     * Get record count
     */
    public function count($table, $where = '1=1', $whereParams = []) {
        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
        return $this->fetchColumn($sql, $whereParams);
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->connection->commit();
    }
    
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->connection->rollback();
    }
    
    /**
     * Execute multiple queries in a transaction
     */
    public function transaction($callback) {
        $this->beginTransaction();
        
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * Get table structure for admin purposes
     */
    public function getTableStructure($table) {
        $sql = "DESCRIBE {$table}";
        return $this->fetchAll($sql);
    }
    
    /**
     * Get database statistics
     */
    public function getStats() {
        $stats = [];
        
        // Get table counts
        $tables = ['users', 'posts', 'comments', 'subscriptions', 'payments'];
        foreach ($tables as $table) {
            $stats[$table . '_count'] = $this->count($table);
        }
        
        // Get recent activity
        $stats['recent_users'] = $this->count('users', 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
        $stats['recent_posts'] = $this->count('posts', 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
        $stats['active_subscriptions'] = $this->count('subscriptions', 'status = "active"');
        
        // Get revenue stats
        $revenue = $this->fetch(
            "SELECT 
                SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_revenue,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_payments,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_payments
            FROM payments 
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        
        $stats = array_merge($stats, $revenue ?: []);
        
        return $stats;
    }
    
    /**
     * Optimize database tables
     */
    public function optimizeTables() {
        $tables = $this->fetchAll("SHOW TABLES");
        $optimized = [];
        
        foreach ($tables as $table) {
            $tableName = current($table);
            $this->query("OPTIMIZE TABLE {$tableName}");
            $optimized[] = $tableName;
        }
        
        return $optimized;
    }
    
    /**
     * Backup database (basic structure only)
     */
    public function backupStructure() {
        $backup = "-- Chloe Belle Database Backup\n";
        $backup .= "-- Generated on " . date('Y-m-d H:i:s') . "\n\n";
        
        $tables = $this->fetchAll("SHOW TABLES");
        
        foreach ($tables as $table) {
            $tableName = current($table);
            $createTable = $this->fetch("SHOW CREATE TABLE {$tableName}");
            $backup .= "\n-- Table: {$tableName}\n";
            $backup .= $createTable['Create Table'] . ";\n\n";
        }
        
        return $backup;
    }
    
    /**
     * Search functionality with full-text search
     */
    public function search($query, $table = 'posts', $fields = ['title', 'content'], $limit = 20) {
        $searchFields = implode(',', $fields);
        $sql = "SELECT *, MATCH({$searchFields}) AGAINST(? IN BOOLEAN MODE) as relevance 
                FROM {$table} 
                WHERE MATCH({$searchFields}) AGAINST(? IN BOOLEAN MODE)
                AND status = 'published'
                ORDER BY relevance DESC 
                LIMIT ?";
        
        return $this->fetchAll($sql, [$query, $query, $limit]);
    }
    
    /**
     * Get paginated results
     */
    public function paginate($sql, $params = [], $page = 1, $perPage = 20) {
        $offset = ($page - 1) * $perPage;
        
        // Get total count
        $countSql = preg_replace('/SELECT.*?FROM/i', 'SELECT COUNT(*) as total FROM', $sql);
        $countSql = preg_replace('/ORDER BY.*$/i', '', $countSql);
        $total = $this->fetchColumn($countSql, $params);
        
        // Get paginated results
        $sql .= " LIMIT {$offset}, {$perPage}";
        $results = $this->fetchAll($sql, $params);
        
        return [
            'data' => $results,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }
    
    /**
     * Log database queries for debugging
     */
    public function enableQueryLogging() {
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    /**
     * Prevent cloning
     */
    private function __clone() {}
    
    /**
     * Prevent unserialization
     */
    private function __wakeup() {}
}

// Global database functions for convenience
function db() {
    return Database::getInstance();
}

function dbQuery($sql, $params = []) {
    return db()->query($sql, $params);
}

function dbFetch($sql, $params = []) {
    return db()->fetch($sql, $params);
}

function dbFetchAll($sql, $params = []) {
    return db()->fetchAll($sql, $params);
}

function dbInsert($table, $data) {
    return db()->insert($table, $data);
}

function dbUpdate($table, $data, $where, $whereParams = []) {
    return db()->update($table, $data, $where, $whereParams);
}

function dbDelete($table, $where, $whereParams = []) {
    return db()->delete($table, $where, $whereParams);
}

function dbExists($table, $where, $whereParams = []) {
    return db()->exists($table, $where, $whereParams);
}

function dbCount($table, $where = '1=1', $whereParams = []) {
    return db()->count($table, $where, $whereParams);
}

// Initialize database connection
try {
    Database::getInstance();
} catch (Exception $e) {
    error_log("Database initialization failed: " . $e->getMessage());
    if (DEBUG_MODE) {
        die("Database initialization failed: " . $e->getMessage());
    }
}
?>