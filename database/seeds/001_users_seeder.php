<?php

/**
 * Seeder: Example users
 * Description: Seeds the users table with sample data
 */

return function($db) {
    $users = [
        [
            'user_name' => 'Admin User',
            'user_email' => 'admin@example.com',
            'user_password' => password_hash('password123', PASSWORD_DEFAULT),
        ],
        [
            'user_name' => 'John Doe',
            'user_email' => 'john@example.com',
            'user_password' => password_hash('password123', PASSWORD_DEFAULT),
        ],
    ];

    foreach ($users as $user) {
        $db->insert(
            "INSERT INTO users (user_name, user_email, user_password, created_at) VALUES (:user_name, :user_email, :user_password, NOW())",
            $user
        );
        echo "   User '{$user['user_name']}' created.\n";
    }
};
