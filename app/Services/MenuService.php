<?php

namespace App\Services;

use App\Models\MenuPrincipalModel;
use App\Repository\MenusRepository;

/**
 * Menus Service — Business Logic Layer
 *
 * Handles business rules: duplicate checks, password hashing,
 * stripping sensitive fields, etc. Delegates data access to the Repository.
 */
class MenuService extends Service
{
    private $repository;

    public function __construct()
    {
        $this->repository = new MenusRepository();
    }

    /**
     * Get all menu principals with submenus loaded
     */
    public function getAll()
    {
        $rows = $this->repository->FindAll();
        return array_map([$this, 'loadSubmenus'], $rows);
    }

    /**
     * Get menu principal by ID with submenus loaded
     */
    public function getById($id)
    {
        $row = $this->repository->findById($id);
        if (!$row) {
            return false;
        }
        return $this->loadSubmenus($row);
    }

    /**
     * Attach submenus to a menu principal row array
     */
    private function loadSubmenus(array $row): array
    {
        $model = (new MenuPrincipalModel())->fill($row);
        $submenus = $model->submenus();
        $row['submenus'] = array_map(fn($s) => $s->toArray(), $submenus);
        return $row;
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
     * delete a menu
     */
    public function deleteMenu($id)
    {
        $menu = $this->repository->findById($id);
        if (!$menu) {
            return false;
        }

        return $this->repository->deleteMenu($id);
    }

    /**
     * delete a menu
     */
    public function deleteSubMenu($id)
    {
        $menu = $this->repository->findById($id);
        if (!$menu) {
            return false;
        }

        return $this->repository->deleteMenu($id);
    }

    /**
     * Soft delete a menu
     */
    public function SoftDeleteMenu($id)
    {
        $menu = $this->repository->findById($id);
        if (!$menu) {
            return false;
        }

        return $this->repository->SoftDeleteMenu($id);
    }

    /**
     * Soft delete a submenu
     */
    public function SoftDeleteSubMenu($id)
    {
        $user = $this->repository->findById($id);
        if (!$user) {
            return false;
        }

        return $this->repository->SoftDeleteSubMenu($id);
    }
}
