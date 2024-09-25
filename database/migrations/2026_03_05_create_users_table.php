<?php

/**
 * Migration: Create users table
 * Date: 2026-03-05
 * Description: Creates the users table for the example CRUD
 */

return [
    'description' => 'Create users table',
    
    'up' => function($db) {
        $connection = getenv('DB_CONNECTION') ?: 'mysql';
        
        if ($connection === 'postgresql') {
            $sql = "CREATE TABLE IF NOT EXISTS users (
                id SERIAL PRIMARY KEY,
                user_name VARCHAR(100) NOT NULL,
                user_email VARCHAR(150) NOT NULL UNIQUE,
                user_password VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP,
                deleted_at TIMESTAMP
            )";
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_name VARCHAR(100) NOT NULL,
                user_email VARCHAR(150) NOT NULL UNIQUE,
                user_password VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        }
        
        $db->execute($sql);
        echo "   Table 'users' created successfully!\n";
    },
    
    'down' => function($db) {
        $db->execute("DROP TABLE IF EXISTS users");
        echo "   Table 'users' dropped successfully!\n";
    }
];
