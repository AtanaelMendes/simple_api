<?php

namespace App\Repository;

use App\Core\Database;
use App\Models\MenuPrincipalModel;

/**
 * Menus Repository — Data Access Layer
 *
 * All SQL queries for the menu_principal table live here.
 * Receives and returns raw data arrays — no business logic.
 */
class NewMenusRepository extends Repository
{

    public function getSubmenusList(int $idMenuPrincipal)
    {
        // todo validar is admin

        $query = (
            "SELECT
                    *
            FROM
                    sysfat_submenus ss
            WHERE
                    ss.id_mp_fk = {$idMenuPrincipal}
            AND     ss.id_submenu_fk IS null
            ORDER BY ss.nm_submenu ASC"
        );

        try {
            $result = $this->DAO->select($query);
            return $result;
        } catch (SqlException $th) {
            throw new SqlException($th->getMessage());
        }
    }

    public function updateTreeVisibility(Request $request)
    {
        if (!$request->has('changes')) {
            throw new Exception('Parâmetros inválidos', 400);
        }

        $changes = json_decode($request->getParam('changes', '[]'), true);

        if (!is_array($changes) || empty($changes)) {
            throw new Exception('Nenhuma alteração fornecida', 400);
        }

        $menuActivate   = [];
        $menuDeactivate = [];
        $subActivate    = [];
        $subDeactivate  = [];

        foreach ($changes as $change) {
            $id     = (int) ($change['id']     ?? 0);
            $table  = $change['table']  ?? '';
            $active = (bool) ($change['active'] ?? false);

            if ($id <= 0) continue;

            if ($table === 'menu') {
                $active ? $menuActivate[] = $id : $menuDeactivate[] = $id;
            } elseif ($table === 'submenu') {
                $active ? $subActivate[] = $id : $subDeactivate[] = $id;
            }
        }

        $this->batchUpdateDeletedAt('sysfat_menu_principal', $menuActivate,   null);
        $this->batchUpdateDeletedAt('sysfat_menu_principal', $menuDeactivate,  date('Y-m-d H:i:s'));
        $this->batchUpdateDeletedAt('sysfat_submenus',       $subActivate,     null);
        $this->batchUpdateDeletedAt('sysfat_submenus',       $subDeactivate,   date('Y-m-d H:i:s'));

        return ['updated' => count($changes)];
    }

    private function batchUpdateDeletedAt(string $table, array $ids, ?string $deletedAt): void
    {
        if (empty($ids)) return;

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql    = "UPDATE {$table} SET deleted_at = ? WHERE id IN ({$placeholders})";
        $params = array_merge([$deletedAt], $ids);

        $this->DAO->update($sql, $params);
    }

    public function getSubmenusListAgrupados(int $idSubmenu)
    {
        // todo validar is admin

        $query = (
            "SELECT
                    *
            FROM
                    sysfat_submenus ss
            WHERE
                    ss.id_submenu_fk = {$idSubmenu}
            ORDER BY ss.nm_submenu ASC"
        );

        try {
            $result = $this->DAO->select($query);
            return $result;
        } catch (SqlException $th) {
            throw new SqlException($th->getMessage());
        }
    }

    public function getMenusList(Request $request)
    {
        // todo validar is admin

        $query = (
            "SELECT
                    *
            FROM
                    sysfat_menu_principal smp
            WHERE
                    smp.id_mp_fk IS null
            ORDER BY smp.nm_menu ASC"
        );

        try {
            $result = $this->DAO->select($query);
            return $result;
        } catch (SqlException $th) {
            throw new SqlException($th->getMessage());
        }
    }

    public function getMenusListAgrupados(int $idMenuPrincipal)
    {
        // todo validar is admin

        $query = (
            "SELECT
                    *
            FROM
                    sysfat_menu_principal smp
            WHERE
                    smp.id_mp_fk = {$idMenuPrincipal}
            ORDER BY smp.nm_menu ASC"
        );

        try {
            $result = $this->DAO->select($query);
            return $result;
        } catch (SqlException $th) {
            throw new SqlException($th->getMessage());
        }
    }



    /**
     * Método responsável por retornar a função solicitada pelo front-end
     * @param string $route
     * @return void
     */
    private function route(string $route): void
    {
        try {
            $middleware = new AuthenticationMiddleware;

            match ($route) {
                'menus_list_tree' => $middleware->handle(function (Request $request) {
                    Helper::jsonResponse([
                        'success' => true,
                        'data'    => $this->getMenusListTree($request)
                    ]);
                }),
                'update_tree_visibility' => $middleware->handle(function (Request $request) {
                    Helper::jsonResponse([
                        'success' => true,
                        'data'    => $this->updateTreeVisibility($request)
                    ]);
                }),
                default => throw new Exception("Erro ao processar rota", 400)
            };
        } catch (\Throwable $e) {
            Logger::error("Erro ao processar rota configuracoes", basename(__FILE__) . " linha-> " . __LINE__, "Configuracoes_");
            http_response_code();
            Helper::jsonResponse(['error' => $e->getMessage()], $e->getCode() ?? 400);
        }
    }

    public function setRoute(string $route): void
    {
        $this->route($route);
    }

    public function __destruct()
    {
        $this->DAO = null;
    }
}

if ((isset($_POST['action']) || isset($_GET['action'])) && Helper::validateRequest($_SERVER['REQUEST_URI']) == 'Configuracoes') {
    $configuracoes = new Configuracoes();
    $action = $_POST['action'] ?? $_GET['action'];
    $configuracoes->setRoute($action);
}
