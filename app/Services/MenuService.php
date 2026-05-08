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
        $rows = array_map([$this, 'loadSubmenus'], $rows);
        $rows = array_map([$this, 'loadMenusAgrupados'], $rows);
        return $rows;
    }

    /**
     * Get menu principal by ID with submenus loaded
     */
    public function getById(int $id_mp_fk)
    {
        $row = $this->repository->findById($id_mp_fk);
        if (!$row) {
            return false;
        }
        $row = $this->loadSubmenus($row);
        return $this->loadMenusAgrupados($row);
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
     * Attach grouped menus to a menu principal row array
     */
    private function loadMenusAgrupados(array $row): array
    {
        $model = (new MenuPrincipalModel())->fill($row);
        $menusAgrupados = $model->menusAgrupados();
        $menusAgrupados = array_map([$this, 'loadSubmenus'], $menusAgrupados);
        $row['menus_agrupados'] = array_map(fn($m) => $m->toArray(), $menusAgrupados);
        return $row;
    }

    /**
     * Attach grouped submenus to a submenu row array
     */
    private function loadSubmenusAgrupados(array $row): array
    {
        $model = (new SubmenuModel())->fill($row);
        $submenusAgrupados = $model->submenusAgrupados();
        $row['submenus_agrupados'] = array_map(fn($s) => $s->toArray(), $submenusAgrupados);
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
    public function update(int $id_mp_fk, $data)
    {
        // Check if user exists
        $user = $this->repository->findById($id_mp_fk);
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
            if ($existing && $existing['id'] != $id_mp_fk) {
                throw new \Exception('A user with this email already exists');
            }
            $updateData['user_email'] = $data['user_email'];
        }

        if (isset($data['user_password'])) {
            $updateData['user_password'] = password_hash($data['user_password'], PASSWORD_DEFAULT);
        }

        if (empty($updateData)) {
            return $this->getById($id_mp_fk);
        }

        $this->repository->update($id_mp_fk, $updateData);

        return $this->getById($id_mp_fk);
    }

    /**
     * delete a menu
     */
    public function deleteMenu(int $id_mp_fk)
    {
        $menu = $this->repository->findById($id_mp_fk);
        if (!$menu) {
            return false;
        }

        return $this->repository->deleteMenu($id_mp_fk);
    }

    /**
     * delete a menu
     */
    public function deleteSubMenu(int $id_submenu_fk)
    {
        $menu = $this->repository->findById($id_submenu_fk);
        if (!$menu) {
            return false;
        }

        return $this->repository->deleteSubMenu($id_submenu_fk);
    }

    /**
     * Soft delete a menu
     */
    public function SoftDeleteMenu($id_mp_fk)
    {
        $menu = $this->repository->findById($id_mp_fk);
        if (!$menu) {
            return false;
        }

        return $this->repository->SoftDeleteMenu($id_mp_fk);
    }

    /**
     * Soft delete a submenu
     */
    public function SoftDeleteSubMenu(int $id_submenu_fk)
    {
        $submenu = $this->repository->findById($id_submenu_fk);
        if (!$submenu) {
            return false;
        }

        return $this->repository->SoftDeleteSubMenu($id_submenu_fk);
    }
}
