<?php

namespace App\Repository;

use App\Core\Database;
use App\Models\MenuPrincipalModel;
use App\Models\SubmenuModel;
use App\Exceptions\SqlException;

/**
 * Menus Repository — Data Access Layer
 *
 * All SQL queries for the menu_principal table live here.
 * Receives and returns raw data arrays — no business logic.
 */
class MenusRepository
{
    private Database $db;
    private MenuPrincipalModel $menuPrincipalModel;
    private SubmenuModel $submenuModel;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->menuPrincipalModel = new MenuPrincipalModel();
        $this->submenuModel = new SubmenuModel();
    }

    /**
     * Find all active menu principals
     */
    public function findAll()
    {
        $sql = "SELECT * FROM sysfat_menu_principal WHERE deleted_at IS NULL AND id_mp_fk IS NULL ORDER BY id DESC";
        return $this->db->select($sql);
    }

    /**
     * Find menu principal by ID
     */
    public function findMenuPrincipalById(int $id_mp_fk)
    {
        $sql = "SELECT * FROM sysfat_menu_principal WHERE id = :id AND deleted_at IS NULL LIMIT 1";
        $result = $this->db->select($sql, ['id' => $id_mp_fk]);
        return !empty($result) ? $result[0] : false;
    }
    
    /**
     * Find submenu by ID
     */
    public function findSubmenuById(int $id_submenu_fk)
    {
        $sql = "SELECT * FROM sysfat_submenus WHERE id = :id AND deleted_at IS NULL LIMIT 1";
        $result = $this->db->select($sql, ['id' => $id_submenu_fk]);
        return !empty($result) ? $result[0] : false;
    }

    /**
     * Insert a new menu principal
     */
    public function createMenuPrincipal(array $data)
    {
        $fields = [];
        $placeholders = [];
        $values = [];

        foreach ($data as $field => $value) {
            $fields[] = $field;
            $placeholders[] = ":{$field}";
            $values[$field] = $value;
        }

        $fields[] = 'created_at';
        $placeholders[] = 'NOW()';

        $fieldsStr = implode(', ', $fields);
        $placeholdersStr = implode(', ', $placeholders);

        $sql = "INSERT INTO sysfat_menu_principal ({$fieldsStr}) VALUES ({$placeholdersStr})";
        return $this->db->insert($sql, $values);
    }

    /**
     * Insert a new submenu
     */
    public function createSubmenu(array $data)
    {
        $fields = [];
        $placeholders = [];
        $values = [];

        foreach ($data as $field => $value) {
            $fields[] = $field;
            $placeholders[] = ":{$field}";
            $values[$field] = $value;
        }

        $fields[] = 'created_at';
        $placeholders[] = 'NOW()';

        $fieldsStr = implode(', ', $fields);
        $placeholdersStr = implode(', ', $placeholders);

        $sql = "INSERT INTO sysfat_submenus ({$fieldsStr}) VALUES ({$placeholdersStr})";
        return $this->db->insert($sql, $values);
    }

    /**
     * Update an existing menu principal
     */
    public function updateMenuPrincipal(int $menuPrincipalId, array $data)
    {
        $updates = [];
        $params = ['id' => $menuPrincipalId];

        foreach ($data as $field => $value) {
            $updates[] = "{$field} = :{$field}";
            $params[$field] = $value;
        }

        if (empty($updates)) {
            return false;
        }

        $updatesStr = implode(', ', $updates);
        $sql = "UPDATE sysfat_menu_principal SET {$updatesStr}, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL";

        return $this->db->update($sql, $params);
    }

        /**
     * Update an existing submenu
     */
    public function updateSubmenu(int $submenuId, array $data)
    {
        $updates = [];
        $params = ['id' => $submenuId];

        foreach ($data as $field => $value) {
            $updates[] = "{$field} = :{$field}";
            $params[$field] = $value;
        }

        if (empty($updates)) {
            return false;
        }

        $updatesStr = implode(', ', $updates);
        $sql = "UPDATE sysfat_submenus SET {$updatesStr}, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL";

        return $this->db->update($sql, $params);
    }

    /**
     * delete a menu principal
     */
    public function deleteMenuPrincipal(int $menuPrincipalId)
    {
        $sql = "UPDATE sysfat_menu_principal SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL";
        return $this->db->update($sql, ['id' => $menuPrincipalId]);
    }

    /**
     * Soft delete a menu principal
     */
    public function SoftDeleteMenuPrincipal(int $menuPrincipalId)
    {
        $sql = "UPDATE sysfat_menu_principal SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL";
        return $this->db->update($sql, ['id' => $menuPrincipalId]);
    }

    /**
     * delete a submenu
     */
    public function deleteSubmenu(int $submenuId)
    {
        $sql = "UPDATE sysfat_submenus SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL";
        return $this->db->update($sql, ['id' => $submenuId]);
    }

    /**
     * Soft delete a submenu
     */
    public function SoftDeleteSubmenu(int $submenuId)
    {
        $sql = "UPDATE sysfat_submenus SET deleted_at = NOW() WHERE id = :id AND deleted_at IS NULL";
        return $this->db->update($sql, ['id' => $submenuId]);
    }

    public function getMenusListAgrupados(int $id_mp_fk, bool $trashed = false)
    {
        $trashedCondition = $trashed ? "IS NOT NULL" : "IS NULL";

        $query = (
            "SELECT
                    *
            FROM
                    sysfat_menu_principal smp
            WHERE
                    smp.id_mp_fk = :id_mp_fk
            AND     smp.deleted_at {$trashedCondition}
            ORDER BY smp.nm_menu ASC"
        );

        try {
            $result = $this->db->select($query, ['id_mp_fk' => $id_mp_fk]);
            return $result;
        } catch (SqlException $th) {
            throw new SqlException($th->getMessage());
        }
    }

    public function getSubmenusList(int $id_mp_fk, bool $trashed = false)
    {
        $trashedCondition = $trashed ? "IS NOT NULL" : "IS NULL";

        $query = (
            "SELECT
                    *
            FROM
                    sysfat_submenus ss
            WHERE
                    ss.id_mp_fk = :id_mp_fk
            AND     ss.id_submenu_fk IS null
            AND     ss.deleted_at {$trashedCondition}
            ORDER BY ss.nm_submenu ASC"
        );

        try {
            $result = $this->db->select($query, ['id_mp_fk' => $id_mp_fk]);
            return $result;
        } catch (SqlException $th) {
            throw new SqlException($th->getMessage());
        }
    }

    public function getSubmenusListAgrupados(int $idSubmenuFk, bool $trashed = false)
    {
        $trashedCondition = $trashed ? "IS NOT NULL" : "IS NULL";

        $query = (
            "SELECT
                    *
            FROM
                    sysfat_submenus ss
            WHERE
                    ss.id_submenu_fk = :id_submenu_fk
            AND     ss.deleted_at {$trashedCondition}
            ORDER BY ss.nm_submenu ASC"
        );

        try {
            $result = $this->db->select($query, ['id_submenu_fk' => $idSubmenuFk]);
            return $result;
        } catch (SqlException $th) {
            throw new SqlException($th->getMessage());
        }
    }
}
