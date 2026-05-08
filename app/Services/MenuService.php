<?php

namespace App\Services;

use App\Models\MenuPrincipalModel;
use App\Models\SubmenuModel;
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
    public function getMenuPrincipalById(int $id_mp_fk)
    {
        $row = $this->repository->findMenuPrincipalById($id_mp_fk);
        if (!$row) {
            return false;
        }
        $row = $this->loadSubmenus($row);
        return $this->loadMenusAgrupados($row);
    }

    /**
     * Get menu principal by ID with submenus loaded
     */
    public function getSubmenuById(int $id_mp_fk)
    {
        $row = $this->repository->findSubmenuById($id_mp_fk);
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

        $row['submenus'] = array_map(function ($submenu) {
            return $this->loadSubmenusAgrupados($submenu->toArray());
        }, $submenus);

        return $row;
    }

    /**
     * Attach grouped menus to a menu principal row array
     */
    private function loadMenusAgrupados(array $row): array
    {
        $model = (new MenuPrincipalModel())->fill($row);
        $menusAgrupados = $model->menusAgrupados();

        $row['menus_agrupados'] = array_map(function ($menuAgrupado) {
            return $this->loadSubmenus($menuAgrupado->toArray());
        }, $menusAgrupados);

        return $row;
    }

    /**
     * Attach grouped submenus to a submenu row array
     */
    private function loadSubmenusAgrupados(array $row): array
    {
        $model = (new SubmenuModel())->fill($row);
        $submenusAgrupados = $model->submenusAgrupados();

        $row['submenus_agrupados'] = array_map(function ($submenuAgrupado) {
            return $this->loadSubmenusAgrupados($submenuAgrupado->toArray());
        }, $submenusAgrupados);

        return $row;
    }

    /**
     * Create a new menu principal
     */
    public function createMenuPrincipal($data)
    {
        // Insert and return the created menu principal
        $menuPrincipalId = $this->repository->createMenuPrincipal($data);
        if (!$menuPrincipalId) {
            throw new \Exception('Error creating menu principal');
        }

        return $this->getMenuPrincipalById($menuPrincipalId);
    }

    /**
     * Create a new submenu
     */
    public function createSubmenu($data)
    {
        // Insert and return the created submenu
        $submenuId = $this->repository->createSubmenu($data);
        if (!$submenuId) {
            throw new \Exception('Error creating submenu');
        }

        return $this->getSubmenuById($submenuId);
    }

    /**
     * Update an existing menu principal
     */
    public function updateMenuPrincipal(int $id_mp_fk, $data)
    {
        // Check if menu principal exists
        $menuPrincipal = $this->repository->findMenuPrincipalById($id_mp_fk);
        if (!$menuPrincipal) {
            return false;
        }

        // Build update data with only allowed fields
        $updateData = [];

        if (empty($updateData)) {
            return $this->getMenuPrincipalById($id_mp_fk);
        }

        $this->repository->updateMenuPrincipal($id_mp_fk, $updateData);

        return $this->getMenuPrincipalById($id_mp_fk);
    }

    /**
     * Update an existing submenu
     */
    public function updateSubmenu(int $id_mp_fk, $data)
    {
        // Check if submenu exists
        $submenu = $this->repository->findSubmenuById($id_mp_fk);
        if (!$submenu) {
            return false;
        }

        // Build update data with only allowed fields
        $updateData = [];

        if (empty($updateData)) {
            return $this->getSubmenuById($id_mp_fk);
        }

        $this->repository->updateSubmenu($id_mp_fk, $updateData);

        return $this->getSubmenuById($id_mp_fk);
    }

    /**
     * delete a menu principal
     */
    public function deleteMenuPrincipal(int $id_mp_fk)
    {
        $menu = $this->repository->findMenuPrincipalById($id_mp_fk);
        if (!$menu) {
            return false;
        }

        return $this->repository->deleteMenuPrincipal($id_mp_fk);
    }

    /**
     * delete a menu
     */
    public function deleteSubMenu(int $id_submenu_fk)
    {
        $submenu = $this->repository->findSubmenuById($id_submenu_fk);
        if (!$submenu) {
            return false;
        }

        return $this->repository->deleteSubmenu($id_submenu_fk);
    }

    /**
     * Soft delete a menu
     */
    public function SoftDeleteMenuPrincipal(int $id_mp_fk)
    {
        $menu = $this->repository->findMenuPrincipalById($id_mp_fk);
        if (!$menu) {
            return false;
        }

        return $this->repository->SoftDeleteMenuPrincipal($id_mp_fk);
    }

    /**
     * Soft delete a submenu
     */
    public function SoftDeleteSubmenu(int $id_submenu_fk)
    {
        $submenu = $this->repository->findSubmenuById($id_submenu_fk);
        if (!$submenu) {
            return false;
        }

        return $this->repository->SoftDeleteSubmenu($id_submenu_fk);
    }
}
