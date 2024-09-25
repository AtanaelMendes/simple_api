<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Utils\Logger;
use App\Services\UserService;
use Respect\Validation\Validator as v;
use Respect\Validation\Exceptions\NestedValidationException;

class UserController extends Controller
{
    private $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new UserService();
    }

    /**
     * List all users
     * GET /users
     */
    public function index(Request $request, Response $response)
    {
        try {
            $users = $this->service->getAll();
            return $response->success($users, 'Users retrieved successfully');
        } catch (\Exception $e) {
            Logger::error($e->getMessage(), basename(__FILE__)." linha-> ".__LINE__, "UserController_");
            Logger::warning($e->getMessage(), basename(__FILE__)." linha-> ".__LINE__, "UserController_");
            Logger::info($e->getMessage(), basename(__FILE__)." linha-> ".__LINE__, "UserController_");
            return $response->error('Error retrieving users: ' . $e->getMessage());
        }
    }

    /**
     * Get user by ID
     * GET /users/{id}
     */
    public function show(Request $request, Response $response)
    {
        try {
            $id = $request->getRouteParam('id');

            if (empty($id)) {
                return $response->validationError('User ID is required');
            }

            $user = $this->service->getById($id);

            if (!$user) {
                return $response->notFound('User not found');
            }

            return $response->success($user, 'User retrieved successfully');
        } catch (\Exception $e) {
            Logger::error($e->getMessage(), basename(__FILE__)." linha-> ".__LINE__, "UserController_");
            return $response->error('Error retrieving user: ' . $e->getMessage());
        }
    }

    /**
     * Create a new user
     * POST /users
     */
    public function store(Request $request, Response $response)
    {
        try {
            $params = $request->getParams();

            // Validate input
            $validator = v::key('user_name', v::stringType()->notEmpty()->length(1, 100))
                ->key('user_email', v::email()->length(null, 150))
                ->key('user_password', v::stringType()->notEmpty()->length(6, null));

            try {
                $validator->assert($params);
            } catch (NestedValidationException $e) {
                return $response->validationError($e->getMessages());
            }

            $data = [
                'user_name' => $request->getParam('user_name'),
                'user_email' => $request->getParam('user_email'),
                'user_password' => $request->getParam('user_password'),
            ];

            $user = $this->service->create($data);

            return $response->created($user, 'User created successfully');
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'already exists') !== false) {
                return $response->validationError($e->getMessage());
            }
            Logger::error($e->getMessage(), basename(__FILE__)." linha-> ".__LINE__, "UserController_");
            return $response->error('Error creating user: ' . $e->getMessage());
        }
    }

    /**
     * Update an existing user
     * PUT /users/{id}
     */
    public function update(Request $request, Response $response)
    {
        try {
            $id = $request->getRouteParam('id');

            if (empty($id)) {
                return $response->validationError('User ID is required');
            }

            $params = $request->getParams();

            if (empty($params)) {
                return $response->validationError('No data provided for update');
            }

            // Validate fields if provided
            if (isset($params['user_email'])) {
                $emailValidator = v::email()->length(null, 150);
                if (!$emailValidator->validate($params['user_email'])) {
                    return $response->validationError('Invalid email format');
                }
            }

            if (isset($params['user_name'])) {
                $nameValidator = v::stringType()->notEmpty()->length(1, 100);
                if (!$nameValidator->validate($params['user_name'])) {
                    return $response->validationError('Name must be between 1 and 100 characters');
                }
            }

            if (isset($params['user_password'])) {
                $passValidator = v::stringType()->notEmpty()->length(6, null);
                if (!$passValidator->validate($params['user_password'])) {
                    return $response->validationError('Password must be at least 6 characters');
                }
            }

            $user = $this->service->update($id, $params);

            if (!$user) {
                return $response->notFound('User not found');
            }

            return $response->success($user, 'User updated successfully');
        } catch (\Exception $e) {
            Logger::error($e->getMessage(), basename(__FILE__)." linha-> ".__LINE__, "UserController_");
            return $response->error('Error updating user: ' . $e->getMessage());
        }
    }

    /**
     * Soft delete a user
     * DELETE /users/{id}
     */
    public function destroy(Request $request, Response $response)
    {
        try {
            $id = $request->getRouteParam('id');

            if (empty($id)) {
                return $response->validationError('User ID is required');
            }

            $deleted = $this->service->delete($id);

            if (!$deleted) {
                return $response->notFound('User not found');
            }

            return $response->success(null, 'User deleted successfully');
        } catch (\Exception $e) {
            Logger::error($e->getMessage(), basename(__FILE__)." linha-> ".__LINE__, "UserController_");
            return $response->error('Error deleting user: ' . $e->getMessage());
        }
    }
}
