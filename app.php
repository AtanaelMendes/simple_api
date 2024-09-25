<?php

class App
{
    private $routes = [];

    public function __call($method, $args)
    {
        // Resolver automaticamente o namespace do controller se necessário
        if (isset($args[1]) && is_array($args[1]) && count($args[1]) === 2) {
            $controllerClass = $args[1][0];
            
            // Se a classe não tem namespace completo, adicionar App\Controllers\
            if (strpos($controllerClass, '\\') === false && strpos($controllerClass, 'App\\') !== 0) {
                $args[1][0] = 'App\\Controllers\\' . $controllerClass;
            }
        }
        
        switch ($method) {
            case 'post':
                return $this->routes["POST"][$args[0]] = $args[1];
                break;
            case 'get':
                return $this->routes["GET"][$args[0]] = $args[1];
                break;
            case 'put':
                return $this->routes["PUT"][$args[0]] = $args[1];
                break;
            case 'delete':
                return $this->routes["DELETE"][$args[0]] = $args[1];
                break;
            case 'options':
                return $this->routes["OPTIONS"][$args[0]] = $args[1];
                break;
        }
    }

    public function getRoute(string $method, string $route)
    {
        // Direct match first (for performance)
        if (array_key_exists($method, $this->routes) && array_key_exists($route, $this->routes[$method])) {
            return ['handler' => $this->routes[$method][$route], 'params' => []];
        }

        // No direct match, try pattern matching for routes with parameters
        if (array_key_exists($method, $this->routes)) {
            foreach ($this->routes[$method] as $pattern => $handler) {
                // Check if this is a pattern route (contains {param})
                if (strpos($pattern, '{') !== false && strpos($pattern, '}') !== false) {
                    $patternParams = [];
                    
                    // Extract parameter names from pattern
                    preg_match_all('/{([^}]+)}/', $pattern, $matches);
                    $paramNames = $matches[1]; // Array of parameter names
                    
                    // Convert route pattern to regex
                    $regexPattern = preg_replace('/{[^}]+}/', '([^/]+)', $pattern);
                    $regexPattern = "#^" . $regexPattern . "$#";
                    
                    // Try to match the route against the pattern
                    if (preg_match($regexPattern, $route, $matches)) {
                        array_shift($matches); // Remove first match (full string)
                        
                        // Map parameter names to their values
                        $params = [];
                        foreach ($paramNames as $index => $name) {
                            if (isset($matches[$index])) {
                                $params[$name] = $matches[$index];
                            }
                        }
                        
                        return ['handler' => $handler, 'params' => $params];
                    }
                }
            }
        }

        return null;
    }

    public function executeRoute($routeResult)
    {
        if (!$routeResult) {
            return $this->jsonResponse(['error' => 'Route not found'], 404);
        }
        
        $routeHandler = $routeResult['handler'];
        $routeParams = $routeResult['params'] ?? [];

        if (is_array($routeHandler) && count($routeHandler) === 2) {
            $controllerClass = $routeHandler[0];
            $method = $routeHandler[1];

            if (class_exists($controllerClass)) {
                $controller = new $controllerClass();
                
                if (method_exists($controller, $method)) {
                    // Criar instâncias de Request e Response
                    $request = new \App\Core\Request();
                    
                    // Add route parameters to the request
                    foreach ($routeParams as $param => $value) {
                        $request->setParam($param, $value);
                    }
                    
                    $response = new \App\Core\Response();
                    
                    // Verificar se o método aceita parâmetros
                    $reflection = new ReflectionMethod($controller, $method);
                    $parameters = $reflection->getParameters();
                    
                    if (count($parameters) >= 2) {
                        // Método aceita Request e Response
                        $result = $controller->$method($request, $response);
                        
                        // Se o controller retornou um Response, enviá-lo
                        if ($result instanceof \App\Core\Response) {
                            return $result->send();
                        }
                        
                        // Se não retornou nada, assumir que Response já foi enviada
                        return;
                    } else {
                        // Método antigo sem parâmetros
                        $result = $controller->$method();
                        return $this->jsonResponse($result);
                    }
                } else {
                    return $this->jsonResponse(['error' => 'Method not found'], 404);
                }
            } else {
                return $this->jsonResponse(['error' => 'Controller not found: ' . $controllerClass], 404);
            }
        }

        return $this->jsonResponse(['error' => 'Invalid route handler'], 500);
    }

    private function jsonResponse($data, $statusCode = 200)
    {
        $response = new \App\Core\Response();
        return $response->json($data, $statusCode)->send();
    }
}