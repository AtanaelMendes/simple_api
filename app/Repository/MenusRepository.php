<?php

namespace App\Repository;

use App\Core\Database;
use App\Models\MenuPrincipalModel;
use App\Models\SubmenuModel;

/**
 * Menus Repository — Data Access Layer
 *
 * All SQL queries for the menu_principal table live here.
 * Receives and returns raw data arrays — no business logic.
 */
class MenusRepository
{
    private $db;
    private $menuPrincipalModel;
    private $submenuModel;

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
        $table = $this->menuPrincipalModel->getTable();
        $sql = "SELECT * FROM {$table} WHERE deleted_at IS NULL AND id_mp_fk IS NULL ORDER BY id DESC";
        return $this->db->select($sql);
    }

    /**
     * Find menu principal by ID
     */
    public function findById($id)
    {
        $table = $this->menuPrincipalModel->getTable();
        $pk = $this->menuPrincipalModel->getPrimaryKey();
        $sql = "SELECT * FROM {$table} WHERE {$pk} = :id AND deleted_at IS NULL LIMIT 1";
        $result = $this->db->select($sql, ['id' => $id]);
        return !empty($result) ? $result[0] : false;
    }

    /**
     * Find menu principal by email
     */
    public function findByEmail($email)
    {
        $table = $this->menuPrincipalModel->getTable();
        $sql = "SELECT * FROM {$table} WHERE user_email = :email AND deleted_at IS NULL LIMIT 1";
        $result = $this->db->select($sql, ['email' => $email]);
        return !empty($result) ? $result[0] : false;
    }

    /**
     * Insert a new menu principal
     */
    public function create($data)
    {
        $table = $this->menuPrincipalModel->getTable();
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

        $sql = "INSERT INTO {$table} ({$fieldsStr}) VALUES ({$placeholdersStr})";
        return $this->db->insert($sql, $values);
    }

    /**
     * Update an existing menu principal
     */
    public function update($menuPrincipalId, $data)
    {
        $table = $this->menuPrincipalModel->getTable();
        $pk = $this->menuPrincipalModel->getPrimaryKey();
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
        $sql = "UPDATE {$table} SET {$updatesStr}, updated_at = NOW() WHERE {$pk} = :id AND deleted_at IS NULL";

        return $this->db->update($sql, $params);
    }

    /**
     * delete a menu principal
     */
    public function deleteMenu($menuPrincipalId)
    {
        $table = $this->menuPrincipalModel->getTable();
        $pk = $this->menuPrincipalModel->getPrimaryKey();
        $sql = "UPDATE {$table} SET deleted_at = NOW() WHERE {$pk} = :id AND deleted_at IS NULL";
        return $this->db->update($sql, ['id' => $menuPrincipalId]);
    }

    /**
     * Soft delete a menu principal
     */
    public function SoftDeleteMenu($menuPrincipalId)
    {
        $table = $this->menuPrincipalModel->getTable();
        $pk = $this->menuPrincipalModel->getPrimaryKey();
        $sql = "UPDATE {$table} SET deleted_at = NOW() WHERE {$pk} = :id AND deleted_at IS NULL";
        return $this->db->update($sql, ['id' => $menuPrincipalId]);
    }

    /**
     * delete a submenu
     */
    public function deleteSubMenu($menuPrincipalId)
    {
        $table = $this->submenuModel->getTable();
        $pk = $this->submenuModel->getPrimaryKey();
        $sql = "UPDATE {$table} SET deleted_at = NOW() WHERE {$pk} = :id AND deleted_at IS NULL";
        return $this->db->update($sql, ['id' => $menuPrincipalId]);
    }

    /**
     * Soft delete a submenu
     */
    public function SoftDeleteSubMenu($menuPrincipalId)
    {
        $table = $this->submenuModel->getTable();
        $pk = $this->submenuModel->getPrimaryKey();
        $sql = "UPDATE {$table} SET deleted_at = NOW() WHERE {$pk} = :id AND deleted_at IS NULL";
        return $this->db->update($sql, ['id' => $menuPrincipalId]);
    }
}
