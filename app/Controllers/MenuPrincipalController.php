<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Utils\Logger;
use App\Services\MenuPrincipalService;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\NestedValidationException;

class MenuPrincipalController extends Controller
{
    private $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new MenuPrincipalService();
    }

    /**
     * List all menu items
     * GET /menu-principal
     */
    public function index(Request $request, Response $response)
    {
        try {
            $menuItems = $this->service->getAll();
            return $response->success($menuItems, 'Menu items retrieved successfully');
        } catch (\Exception $e) {
            Logger::error($e->getMessage(), basename(__FILE__)." linha-> ".__LINE__, "MenuPrincipalController_");
            Logger::warning($e->getMessage(), basename(__FILE__)." linha-> ".__LINE__, "MenuPrincipalController_");
            Logger::info($e->getMessage(), basename(__FILE__)." linha-> ".__LINE__, "MenuPrincipalController_");
            return $response->error('Error retrieving menu items: ' . $e->getMessage());
        }
    }

    /**
     * Get menu item by ID
     * GET /menu-principal/{id}
     */
    public function show(Request $request, Response $response)
    {
        try {
            $id = $request->getRouteParam('id');

            if (empty($id)) {
                return $response->validationError('Menu item ID is required');
            }

            $menuItem = $this->service->getById($id);

            if (!$menuItem) {
                return $response->notFound('Menu item not found');
            }

            return $response->success($menuItem, 'Menu item retrieved successfully');
        } catch (\Exception $e) {
            Logger::error($e->getMessage(), basename(__FILE__)." linha-> ".__LINE__, "MenuPrincipalController_");
            return $response->error('Error retrieving menu item: ' . $e->getMessage());
        }
    }

    /**
     * Create a new menu item
     * POST /menu-principal
     */
    public function store(Request $request, Response $response)
    {
        try {
            $params = $request->getParams();

            // Validate input
            $validator = v::key('nm_menu', v::stringType()->notEmpty()->length(1, 50))
                ->key('ds_observacoes', v::stringType()->length(null, 255))
                ->key('is_public', v::intType()->between(0, 1));

            try {
                $validator->assert($params);
            } catch (NestedValidationException $e) {
                return $response->validationError($e->getMessages());
            }

            $data = [
                'nm_menu' => $request->getParam('nm_menu'),
                'ds_observacoes' => $request->getParam('ds_observacoes'),
                'is_public' => $request->getParam('is_public'),
            ];

            $menuItem = $this->service->create($data);

            return $response->created($menuItem, 'Menu item created successfully');
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                return $response->validationError($e->getMessage());
            }
            Logger::error($e->getMessage(), basename(__FILE__)." linha-> ".__LINE__, "MenuPrincipalController_");
            return $response->error('Error creating menu item: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing menu item
     * PUT /menu-principal/{id}
     */
    public function update(Request $request, Response $response)
    {
        try {
            $id = $request->getRouteParam('id');

            if (empty($id)) {
                return $response->validationError('Menu item ID is required');
            }

            $params = $request->getParams();

            if (empty($params)) {
                return $response->validationError('No data provided for update');
            }

            // Validate fields if provided
            if (isset($params['nm_menu'])) {
                $nameValidator = v::stringType()->notEmpty()->length(1, 50);
                if (!$nameValidator->validate($params['nm_menu'])) {
                    return $response->validationError('Menu name must be between 1 and 50 characters');
                }
            }

            if (isset($params['ds_observacoes'])) {
                $obsValidator = v::stringType()->length(null, 255);
                if (!$obsValidator->validate($params['ds_observacoes'])) {
                    return $response->validationError('Observations must be up to 255 characters');
                }
            }

            if (isset($params['is_public'])) {
                $publicValidator = v::intType()->between(0, 1);
                if (!$publicValidator->validate($params['is_public'])) {
                    return $response->validationError('is_public must be 0 or 1');
                }
            }

            $menuItem = $this->service->update($id, $params);

            if (!$menuItem) {
                return $response->notFound('Menu item not found');
            }

            return $response->success($menuItem, 'Menu item updated successfully');
        } catch (\Exception $e) {
            Logger::error($e->getMessage(), basename(__FILE__)." linha-> ".__LINE__, "MenuPrincipalController_");
            return $response->error('Error updating menu item: ' . $e->getMessage());
        }
    }

    /**
     * Soft delete a menu item
     * DELETE /menu-principal/{id}
     */
    public function destroy(Request $request, Response $response)
    {
        try {
            $id = $request->getRouteParam('id');

            if (empty($id)) {
                return $response->validationError('Menu item ID is required');
            }

            $deleted = $this->service->delete($id);

            if (!$deleted) {
                return $response->notFound('Menu item not found');
            }

            return $response->success(null, 'Menu item deleted successfully');
        } catch (\Exception $e) {
            Logger::error($e->getMessage(), basename(__FILE__)." linha-> ".__LINE__, "MenuPrincipalController_");
            return $response->error('Error deleting menu item: ' . $e->getMessage());
        }
    }
}
