<?php

namespace App\Services;

use App\Repository\UserRepository;

/**
 * User Service — Business Logic Layer
 *
 * Handles business rules: duplicate checks, password hashing,
 * stripping sensitive fields, etc. Delegates data access to the Repository.
 */
class UserService extends Service
{
    private $repository;

    public function __construct()
    {
        $this->repository = new UserRepository();
    }

    /**
     * Get all users (without passwords)
     */
    public function getAll()
    {
        $users = $this->repository->findAll();
        return array_map([$this, 'stripSensitiveFields'], $users);
    }

    /**
     * Get user by ID (without password)
     */
    public function getById($id)
    {
        $user = $this->repository->findById($id);
        return $user ? $this->stripSensitiveFields($user) : false;
    }

    /**
     * Create a new user
     */
    public function create($data)
    {
        // Check if email already exists
        $existing = $this->repository->findByEmail($data['user_email']);
        if ($existing) {
            throw new \Exception('A user with this email already exists');
        }

        // Hash the password
        $data['user_password'] = password_hash($data['user_password'], PASSWORD_DEFAULT);

        // Insert and return the created user
        $userId = $this->repository->create($data);
        if (!$userId) {
            throw new \Exception('Error creating user');
        }

        return $this->getById($userId);
    }

    /**
     * Update an existing user
     */
    public function update($id, $data)
    {
        // Check if user exists
        $user = $this->repository->findById($id);
        if (!$user) {
            return false;
        }

        // Build update data with only allowed fields
        $updateData = [];

        if (isset($data['user_name'])) {
            $updateData['user_name'] = $data['user_name'];
        }

        if (isset($data['user_email'])) {
            // Check if new email is already in use by another user
            $existing = $this->repository->findByEmail($data['user_email']);
            if ($existing && $existing['id'] != $id) {
                throw new \Exception('A user with this email already exists');
            }
            $updateData['user_email'] = $data['user_email'];
        }

        if (isset($data['user_password'])) {
            $updateData['user_password'] = password_hash($data['user_password'], PASSWORD_DEFAULT);
        }

        if (empty($updateData)) {
            return $this->getById($id);
        }

        $this->repository->update($id, $updateData);

        return $this->getById($id);
    }

    /**
     * Soft delete a user
     */
    public function delete($id)
    {
        $user = $this->repository->findById($id);
        if (!$user) {
            return false;
        }

        return $this->repository->softDelete($id);
    }

    /**
     * Remove sensitive fields from user data
     */
    private function stripSensitiveFields($user)
    {
        unset($user['user_password']);
        return $user;
    }
}
