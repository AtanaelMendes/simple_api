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
class NewMenuService extends Service
{
    private $newMenusRepository;

    public function __construct()
    {
        $this->newMenusRepository = new NewMenusRepository();
    }

    /**
     * Get all menu principals with submenus loaded
     */
    public function getAll()
    {
        $rows = $this->newMenusRepository->findAll();
        return array_map([$this, 'loadSubmenus'], $rows);
    }

    /**
     * Get menu principal by ID with submenus loaded
     */
    public function getById($id)
    {
        $row = $this->newMenusRepository->findById($id);
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
        $existing = $this->newMenusRepository->findByEmail($data['user_email']);
        if ($existing) {
            throw new \Exception('A menu principal with this email already exists');
        }

        // Insert and return the created menu principal
        $menuPrincipalId = $this->newMenusRepository->create($data);
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
        $user = $this->newMenusRepository->findById($id);
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
            $existing = $this->newMenusRepository->findByEmail($data['user_email']);
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

        $this->newMenusRepository->update($id, $updateData);

        return $this->getById($id);
    }

    /**
     * delete a user
     */
    public function deleteMenu(int $id_mp_fk)
    {
        $user = $this->newMenusRepository->findById($id_mp_fk);
        if (!$user) {
            return false;
        }
        return $this->newMenusRepository->deleteMenu($id_mp_fk);
    }
    
    /**
     * delete a user
     */
    public function deleteSubmenu(int $id_submenu_fk)
    {
        $user = $this->newMenusRepository->findById($id_submenu_fk);
        if (!$user) {
            return false;
        }
        return $this->newMenusRepository->deleteSubmenu($id_submenu_fk);
    }

    /**
     * Soft delete a user
     */
    public function SoftDeleteMenu(int $id_mp_fk)
    {
        $user = $this->newMenusRepository->findById($id_mp_fk);
        if (!$user) {
            return false;
        }
        return $this->newMenusRepository->SoftDeleteMenu($id_mp_fk);
    }
    
    /**
     * Soft delete a user
     */
    public function SoftDeleteSubmenu(int $id_submenu_fk)
    {
        $user = $this->newMenusRepository->findById($id_submenu_fk);
        if (!$user) {
            return false;
        }
        return $this->newMenusRepository->SoftDeleteSubmenu($id_submenu_fk);
    }

}
