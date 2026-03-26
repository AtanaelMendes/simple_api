<?php

namespace App\Services;

use App\Repository\MenuPrincipalRepository;

/**
 * MenuPrincipal Service — Business Logic Layer
 *
 * Handles business rules: duplicate checks, password hashing,
 * stripping sensitive fields, etc. Delegates data access to the Repository.
 */
class MenuPrincipalService extends Service
{
    private $repository;

    public function __construct()
    {
        $this->repository = new MenuPrincipalRepository();
    }

    /**
     * Get all menu principals (without passwords)
     */
    public function getAll()
    {
        return $this->repository->findAll();
    }

    /**
     * Get menu principal by ID (without password)
     */
    public function getById($id)
    {
        return $this->repository->findById($id);
    }

    /**
     * Create a new menu principal
     */
    public function create($data)
    {
        // Check if email already exists
        $existing = $this->repository->findByEmail($data['user_email']);
        if ($existing) {
            throw new \Exception('A menu principal with this email already exists');
        }

        // Insert and return the created menu principal
        $menuPrincipalId = $this->repository->create($data);
        if (!$menuPrincipalId) {
            throw new \Exception('Error creating menu principal');
        }

        return $this->getById($menuPrincipalId);
    }

    /**
     * Update an existing menu principal
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
