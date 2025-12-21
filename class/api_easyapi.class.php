<?php
/* Copyright (C) 2025 Alberto Luque Rivas <aluquerivasdev@gmail.com>
 *
 * EasyAPI - Clase Principal de la API
 *
 * Gestiona la instancia de Slim, middleware, rutas y hooks para
 * permitir que otros módulos registren sus propios endpoints.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;
use EasyApi\Middleware\DolibarrAuth;
use EasyApi\Middleware\CorsMiddleware;
use EasyApi\Middleware\RequestLogger;
use EasyApi\Middleware\RateLimitMiddleware;

// Cargar generador OpenAPI
require_once __DIR__ . '/openapi_generator.class.php';

/**
 * Clase principal de EasyAPI
 *
 * Crea una instancia de Slim y permite registrar rutas dinámicamente
 * mediante el sistema de hooks de Dolibarr.
 */
class ApiEasyApi
{
    /** @var \Slim\App */
    private $app;

    /** @var \DoliDB */
    private $db;

    /** @var array Configuración del módulo */
    private $config;

    /** @var array Rutas registradas para documentación */
    private $registeredRoutes = array();

    /** @var array Middleware personalizados registrados */
    private $customMiddleware = array();

    /** @var OpenApiGenerator Generador OpenAPI */
    private $openApiGenerator;

    /** @var array Información sobre recursos EAPI cargados */
    private $eapiLoadResult = array();

    /**
     * Constructor
     *
     * @param \DoliDB $db Conexión a base de datos de Dolibarr
     */
    public function __construct($db)
    {
        global $conf;

        $this->db = $db;
        $this->loadConfig();
        $this->initSlimApp();
        $this->initOpenApiGenerator();
        $this->registerMiddleware();
        $this->registerCoreRoutes();
        $this->loadEapiResources();  // Cargar recursos EAPI
        $this->executeHooks();
    }

    /**
     * Carga la configuración del módulo
     */
    private function loadConfig(): void
    {
        global $conf;

        $this->config = array(
            'debug' => !empty($conf->global->EASYAPI_DEBUG),
            'cors_enabled' => !empty($conf->global->EASYAPI_CORS_ENABLED) ? true : true, // Activado por defecto
            'cors_origin' => isset($conf->global->EASYAPI_CORS_ORIGIN) ? $conf->global->EASYAPI_CORS_ORIGIN : '*',
            'rate_limit_enabled' => !empty($conf->global->EASYAPI_RATE_LIMIT_ENABLED),
            'rate_limit_max' => (int) (isset($conf->global->EASYAPI_RATE_LIMIT_MAX) ? $conf->global->EASYAPI_RATE_LIMIT_MAX : 100),
            'rate_limit_window' => (int) (isset($conf->global->EASYAPI_RATE_LIMIT_WINDOW) ? $conf->global->EASYAPI_RATE_LIMIT_WINDOW : 60),
            'log_enabled' => !empty($conf->global->EASYAPI_LOG_ENABLED),
            'log_file' => isset($conf->global->EASYAPI_LOG_FILE) ? $conf->global->EASYAPI_LOG_FILE : null,
            'public_routes' => $this->parsePublicRoutes(isset($conf->global->EASYAPI_PUBLIC_ROUTES) ? $conf->global->EASYAPI_PUBLIC_ROUTES : ''),
        );
    }

    /**
     * Parsea las rutas públicas desde la configuración
     *
     * @param string $routes Rutas separadas por coma
     * @return array
     */
    private function parsePublicRoutes(string $routes): array
    {
        if (empty($routes)) {
            return array('/status', '/health', '/login', '/docs', '/openapi.json');
        }
        return array_filter(array_map('trim', explode(',', $routes)));
    }

    /**
     * Inicializa la aplicación Slim
     */
    private function initSlimApp(): void
    {
        $this->app = AppFactory::create();

        // Configurar base path basado en la URL actual
        $basePath = $this->detectBasePath();
        $this->app->setBasePath($basePath);

        // Error handling
        $this->configureErrorHandling();
    }

    /**
     * Inicializa el generador OpenAPI
     */
    private function initOpenApiGenerator(): void
    {
        $baseUrl = $this->getApiBaseUrl();
        $this->openApiGenerator = new OpenApiGenerator($baseUrl);
    }

    /**
     * Obtiene la URL base de la API
     *
     * @return string
     */
    public function getApiBaseUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $basePath = $this->detectBasePath();
        return $protocol . '://' . $host . $basePath;
    }

    /**
     * Detecta el base path de la API
     *
     * @return string
     */
    private function detectBasePath(): string
    {
        $scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
        $basePath = dirname($scriptName);

        // Limpiar el path
        $basePath = rtrim($basePath, '/');

        return $basePath;
    }

    /**
     * Configura el manejo de errores
     */
    private function configureErrorHandling(): void
    {
        $app = $this->app;
        $debug = $this->config['debug'];

        // Add Routing Middleware
        $app->addRoutingMiddleware();

        // Add Error Middleware
        $errorMiddleware = $app->addErrorMiddleware($debug, true, true);

        // Custom error handler
        $errorMiddleware->setDefaultErrorHandler(function (
            Request $request,
            \Throwable $exception,
            bool $displayErrorDetails,
            bool $logErrors,
            bool $logErrorDetails
        ) use ($app) {
            $statusCode = 500;

            if ($exception instanceof \Slim\Exception\HttpException) {
                $statusCode = $exception->getCode();
            }

            $error = array(
                'success' => false,
                'error' => array(
                    'code' => $statusCode,
                    'message' => $exception->getMessage()
                )
            );

            if ($displayErrorDetails) {
                $error['error']['file'] = $exception->getFile();
                $error['error']['line'] = $exception->getLine();
                $error['error']['trace'] = $exception->getTraceAsString();
            }

            $response = $app->getResponseFactory()->createResponse($statusCode);
            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($error, JSON_PRETTY_PRINT));

            return $response;
        });
    }

    /**
     * Registra los middleware base
     */
    private function registerMiddleware(): void
    {
        // CORS Middleware (primero para manejar preflight)
        if ($this->config['cors_enabled']) {
            $corsOptions = array(
                'origin' => $this->config['cors_origin'],
            );
            $this->app->add(new CorsMiddleware($corsOptions));
        }

        // Rate Limit Middleware
        if ($this->config['rate_limit_enabled']) {
            $this->app->add(new RateLimitMiddleware($this->db, array(
                'enabled' => true,
                'maxRequests' => $this->config['rate_limit_max'],
                'windowSeconds' => $this->config['rate_limit_window']
            )));
        }

        // Logger Middleware
        if ($this->config['log_enabled']) {
            $this->app->add(new RequestLogger($this->db, array(
                'enabled' => true,
                'logFile' => $this->config['log_file']
            )));
        }

        // Autenticación DOLAPIKEY
        // Usamos un callback para verificar rutas públicas dinámicamente
        $self = $this;
        $this->app->add(new DolibarrAuth($this->db, array(
            'publicRoutes' => $this->config['public_routes'],
            'publicRouteChecker' => function($path, $method) use ($self) {
                return $self->isRoutePublic($path, $method);
            }
        )));
    }

    /**
     * Verifica si una ruta está marcada como pública en las rutas registradas
     *
     * @param string $path
     * @param string $method
     * @return bool
     */
    public function isRoutePublic(string $path, string $method = 'GET'): bool
    {
        $method = strtoupper($method);

        foreach ($this->registeredRoutes as $route) {
            // Comparar método
            if (strtoupper($route['method']) !== $method) {
                continue;
            }

            // Comparar path (convertir parámetros de Slim a regex)
            $routePath = $route['path'];
            // Convertir {param} y {param:pattern} a regex
            $pattern = preg_replace('/\{[^}]+\}/', '[^/]+', $routePath);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $path)) {
                // Encontrada - verificar si es pública
                return !empty($route['public']);
            }
        }

        return false;
    }

    /**
     * Registra las rutas core de la API
     */
    private function registerCoreRoutes(): void
    {
        $app = $this->app;
        $db = $this->db;
        $self = $this;

        // Ruta de estado/health check
        $app->get('/status', function (Request $request, Response $response) use ($self) {
            $data = array(
                'success' => true,
                'data' => array(
                    'status' => 'ok',
                    'timestamp' => date('c'),
                    'version' => '1.0.0'
                )
            );
            return $self->jsonResponse($response, $data);
        })->setName('status');
        $this->addRoute(array(
            'method' => 'GET',
            'path' => '/status',
            'summary' => 'Health check',
            'description' => 'Returns the API status',
            'tags' => array('Core'),
            'public' => true
        ));

        // Alias health
        $app->get('/health', function (Request $request, Response $response) use ($self) {
            $data = array(
                'success' => true,
                'data' => array('status' => 'healthy')
            );
            return $self->jsonResponse($response, $data);
        })->setName('health');
        $this->addRoute(array(
            'method' => 'GET',
            'path' => '/health',
            'summary' => 'Health check alias',
            'description' => 'Returns healthy status',
            'tags' => array('Core'),
            'public' => true
        ));

        // =====================================================================
        // Debug endpoint - Información de recursos cargados
        // =====================================================================
        $app->get('/debug/eapi', function (Request $request, Response $response) use ($self) {
            $eapiResult = $self->getEapiLoadResult();
            $routes = $self->getRegisteredRoutes();

            return $self->successResponse($response, array(
                'eapi_modules' => $eapiResult['modules'],
                'eapi_resources' => $eapiResult['resources'],
                'eapi_errors' => $eapiResult['errors'],
                'total_routes' => count($routes),
                'routes_by_tag' => $self->groupRoutesByTag($routes)
            ));
        });
        $this->addRoute(array(
            'method' => 'GET',
            'path' => '/debug/eapi',
            'summary' => 'Debug EAPI Resources',
            'description' => 'Shows loaded EAPI resources and registered routes',
            'tags' => array('Core'),
            'public' => false
        ));

        // =====================================================================
        // Debug Slim Routes - Lista TODAS las rutas registradas en Slim
        // =====================================================================
        $app->get('/debug/slim-routes', function (Request $request, Response $response) use ($self, $app) {
            $routeCollector = $app->getRouteCollector();
            $slimRoutes = $routeCollector->getRoutes();

            $routeList = array();
            foreach ($slimRoutes as $route) {
                $routeList[] = array(
                    'methods' => $route->getMethods(),
                    'pattern' => $route->getPattern(),
                    'name' => $route->getName(),
                    'identifier' => $route->getIdentifier()
                );
            }

            // Obtener info de request actual
            $requestInfo = array(
                'uri' => (string) $request->getUri(),
                'path' => $request->getUri()->getPath(),
                'method' => $request->getMethod(),
                'basePath' => $app->getBasePath(),
                'script_name' => isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : 'N/A',
                'request_uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'N/A'
            );

            return $self->successResponse($response, array(
                'request_info' => $requestInfo,
                'base_path' => $app->getBasePath(),
                'slim_routes_count' => count($routeList),
                'slim_routes' => $routeList
            ));
        });
        $this->addRoute(array(
            'method' => 'GET',
            'path' => '/debug/slim-routes',
            'summary' => 'Debug Slim Routes',
            'description' => 'Shows all routes registered in Slim router',
            'tags' => array('Core'),
            'public' => false
        ));

        // =====================================================================
        // OpenAPI JSON Specification
        // =====================================================================
        $app->get('/openapi.json', function (Request $request, Response $response) use ($self) {
            // Verificar si hay usuario autenticado
            $user = $request->getAttribute('dolibarr_user');
            $onlyPublic = empty($user);

            $spec = $self->getOpenApiGenerator()->generate($onlyPublic, $user);
            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return $response;
        })->setName('openapi');
        $this->addRoute(array(
            'method' => 'GET',
            'path' => '/openapi.json',
            'summary' => 'OpenAPI 3.0 Specification',
            'description' => 'Returns the OpenAPI 3.0 specification in JSON format. Shows only public endpoints without authentication, all endpoints when authenticated.',
            'tags' => array('Core'),
            'public' => true
        ));

        // =====================================================================
        // Swagger UI - Documentación interactiva
        // =====================================================================
        $app->get('/docs', function (Request $request, Response $response) use ($self) {
            $user = $request->getAttribute('dolibarr_user');
            $baseUrl = $self->getApiBaseUrl();
            $html = $self->getSwaggerUIHtml($baseUrl, $user);
            $response = $response->withHeader('Content-Type', 'text/html');
            $response->getBody()->write($html);
            return $response;
        })->setName('docs');
        $this->addRoute(array(
            'method' => 'GET',
            'path' => '/docs',
            'summary' => 'Swagger UI Documentation',
            'description' => 'Interactive API documentation. Shows only public endpoints without authentication. Authenticate to see all available endpoints.',
            'tags' => array('Core'),
            'public' => true
        ));

        // =====================================================================
        // LOGIN - Obtener API Key con credenciales
        // =====================================================================
        $app->post('/login', function (Request $request, Response $response) use ($db, $self) {
            global $conf, $dolibarr_main_authentication;

            $body = json_decode((string) $request->getBody(), true);

            $login = isset($body['login']) ? $body['login'] : '';
            $password = isset($body['password']) ? $body['password'] : '';
            $entity = isset($body['entity']) ? $body['entity'] : '';

            if (empty($login) || empty($password)) {
                return $self->errorResponse($response, 'login and password are required', 400);
            }

            // Usar el método nativo de Dolibarr para verificar credenciales
            require_once DOL_DOCUMENT_ROOT . '/core/lib/security2.lib.php';

            // Determinar modos de autenticación disponibles
            $authmode = explode(',', (isset($dolibarr_main_authentication) ? $dolibarr_main_authentication : 'dolibarr'));

            // checkLoginPassEntity devuelve el login si es correcto, o '' si falla
            $resultLogin = checkLoginPassEntity($login, $password, $entity, $authmode, 'api');

            if (empty($resultLogin) || $resultLogin === '--bad-login-validity--') {
                return $self->errorResponse($response, 'Invalid credentials', 401);
            }

            // Cargar usuario
            require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
            $user = new \User($db);
            $result = $user->fetch('', $resultLogin);

            if ($result <= 0) {
                return $self->errorResponse($response, 'User not found', 401);
            }

            // Verificar que el usuario está activo
            if ($user->statut != 1) {
                return $self->errorResponse($response, 'User account is disabled', 403);
            }

            // Generar API key si no existe
            if (empty($user->api_key)) {
                $user->api_key = getRandomPassword(true, array('I'));
                $user->update($user);
            }

            $data = array(
                'success' => true,
                'data' => array(
                    'api_key' => $user->api_key,
                    'user' => array(
                        'id' => (int) $user->id,
                        'login' => $user->login,
                        'firstname' => $user->firstname,
                        'lastname' => $user->lastname,
                        'email' => $user->email,
                        'admin' => (bool) $user->admin
                    )
                )
            );
            return $self->jsonResponse($response, $data);
        })->setName('login');
        $this->addRoute(array(
            'method' => 'POST',
            'path' => '/login',
            'summary' => 'Authenticate user',
            'description' => 'Authenticate with login/password and get API key',
            'tags' => array('Auth'),
            'public' => true,
            'requestBody' => array(
                'required' => true,
                'content' => array(
                    'application/json' => array(
                        'schema' => array('$ref' => '#/components/schemas/LoginRequest')
                    )
                )
            ),
            'responses' => array(
                '200' => array(
                    'description' => 'Successful authentication',
                    'content' => array(
                        'application/json' => array(
                            'schema' => array('$ref' => '#/components/schemas/LoginResponse')
                        )
                    )
                ),
                '401' => array(
                    'description' => 'Invalid credentials',
                    'content' => array(
                        'application/json' => array(
                            'schema' => array('$ref' => '#/components/schemas/ErrorResponse')
                        )
                    )
                )
            )
        ));

        // =====================================================================
        // ME - Información del usuario autenticado
        // =====================================================================
        $app->get('/me', function (Request $request, Response $response) use ($self) {
            $user = $request->getAttribute('dolibarr_user');

            $data = array(
                'success' => true,
                'data' => array(
                    'id' => (int) $user->id,
                    'login' => $user->login,
                    'lastname' => $user->lastname,
                    'firstname' => $user->firstname,
                    'email' => $user->email,
                    'admin' => (bool) $user->admin,
                    'entity' => (int) $user->entity,
                    'statut' => (int) $user->statut
                )
            );
            return $self->jsonResponse($response, $data);
        })->setName('me');
        $this->addRoute(array(
            'method' => 'GET',
            'path' => '/me',
            'summary' => 'Get current user',
            'description' => 'Returns information about the authenticated user',
            'tags' => array('Auth'),
            'public' => false,
            'responses' => array(
                '200' => array(
                    'description' => 'User information',
                    'content' => array(
                        'application/json' => array(
                            'schema' => array(
                                'type' => 'object',
                                'properties' => array(
                                    'success' => array('type' => 'boolean'),
                                    'data' => array('$ref' => '#/components/schemas/User')
                                )
                            )
                        )
                    )
                )
            )
        ));
    }

    /**
     * Carga los recursos EAPI de todos los módulos
     *
     * Escanea automáticamente las carpetas 'eapi' de los módulos en custom/
     * y carga las clases que extienden EasyApiResource.
     */
    private function loadEapiResources(): void
    {
        require_once __DIR__ . '/EasyApiResourceLoader.class.php';

        $loader = new \EasyApiResourceLoader($this->app, $this->db, $this);
        $this->eapiLoadResult = $loader->loadAll();

        // Log de recursos cargados
        if (!empty($this->eapiLoadResult['resources'])) {
            dol_syslog('EasyAPI: Loaded ' . count($this->eapiLoadResult['resources']) . ' EAPI resources from ' . count($this->eapiLoadResult['modules']) . ' modules', LOG_INFO);
            foreach ($this->eapiLoadResult['resources'] as $res) {
                dol_syslog('EasyAPI: - ' . $res['class'] . ' => ' . $res['prefix'], LOG_DEBUG);
            }
        }

        // Log de errores si hay
        if (!empty($this->eapiLoadResult['errors'])) {
            foreach ($this->eapiLoadResult['errors'] as $error) {
                dol_syslog('EasyAPI Resource Error: ' . $error, LOG_WARNING);
            }
        }
    }

    /**
     * Obtiene información de recursos EAPI cargados
     *
     * @return array
     */
    public function getEapiLoadResult(): array
    {
        return $this->eapiLoadResult;
    }

    /**
     * Ejecuta los hooks para que otros módulos registren sus rutas
     */
    private function executeHooks(): void
    {
        global $hookmanager, $conf;

        if (!is_object($hookmanager)) {
            require_once DOL_DOCUMENT_ROOT . '/core/class/hookmanager.class.php';
            $hookmanager = new \HookManager($this->db);
        }

        // Inicializar hooks para el contexto easyapi
        $hookmanager->initHooks(array('easyapi'));

        // Parámetros que se pasan a los hooks
        $parameters = array(
            'app' => $this->app,
            'db' => $this->db,
            'api' => $this,
            'config' => $this->config
        );

        // El 4to parámetro debe ser una variable (se pasa por referencia)
        $action = 'addRoutes';
        $reshook = $hookmanager->executeHooks('easyapiRegisterRoutes', $parameters, $this, $action);

        // También ejecutar hook alternativo para compatibilidad
        $reshook = $hookmanager->executeHooks('headlessApiRegisterRoutes', $parameters, $this, $action);
    }

    /**
     * Ejecuta la aplicación Slim
     */
    public function run(): void
    {
        $this->app->run();
    }

    /**
     * Obtiene la instancia de Slim App
     *
     * @return \Slim\App
     */
    public function getApp(): \Slim\App
    {
        return $this->app;
    }

    /**
     * Obtiene la conexión a base de datos
     *
     * @return \DoliDB
     */
    public function getDb(): \DoliDB
    {
        return $this->db;
    }

    /**
     * Añade una ruta a la documentación OpenAPI
     *
     * @param array $routeConfig Configuración completa de la ruta OpenAPI
     */
    public function addRoute(array $routeConfig): void
    {
        // Añadir source si no está definido (viene de Hooks)
        if (!isset($routeConfig['source'])) {
            $routeConfig['source'] = 'Hook: actions_*.class.php';
        }
        $this->registeredRoutes[] = $routeConfig;
        $this->openApiGenerator->addRoute($routeConfig);
    }

    /**
     * Añade documentación de una ruta (método legacy - usar addRoute)
     *
     * @param string $method
     * @param string $path
     * @param string $description
     * @param bool $public
     * @param array $params
     * @deprecated Use addRoute() instead
     */
    public function addRouteDoc(string $method, string $path, string $description, bool $public = false, array $params = array()): void
    {
        $this->addRoute(array(
            'method' => $method,
            'path' => $path,
            'summary' => $description,
            'description' => $description,
            'public' => $public,
            'tags' => array('Core')
        ));
    }

    /**
     * Obtiene el generador OpenAPI
     *
     * @return OpenApiGenerator
     */
    public function getOpenApiGenerator(): OpenApiGenerator
    {
        return $this->openApiGenerator;
    }

    /**
     * Genera el HTML de Swagger UI
     *
     * @param string $baseUrl URL base de la API
     * @param object|null $user Usuario autenticado
     * @return string
     */
    public function getSwaggerUIHtml(string $baseUrl, $user = null): string
    {
        $openApiUrl = $baseUrl . '/openapi.json';
        $isAuthenticated = !empty($user);

        // Obtener la API key de la URL actual si existe
        $currentApiKey = isset($_GET['DOLAPIKEY']) ? $_GET['DOLAPIKEY'] : '';

        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EasyAPI - Swagger UI</title>
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui.css">
    <style>
        body { margin: 0; padding: 0; }
        .swagger-ui .topbar { display: none; }
        .swagger-ui .info .title { font-size: 2em; }
        .auth-box { font-family: sans-serif; font-size: 14px; padding: 10px 20px; }
        .auth-box.authenticated { background: #d4edda; color: #155724; border-bottom: 1px solid #c3e6cb; }
        .auth-box.not-authenticated { background: #fff3cd; color: #856404; border-bottom: 1px solid #ffeeba; }
        .auth-input { padding: 8px; width: 320px; border: 1px solid #ccc; border-radius: 4px; margin-right: 8px; }
        .auth-btn { padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .auth-btn:hover { background: #0056b3; }
        .auth-btn.logout { background: #dc3545; margin-left: 10px; }
        .auth-btn.logout:hover { background: #c82333; }
    </style>
</head>
<body>
    <div id="auth-box" class="auth-box ' . ($isAuthenticated ? 'authenticated' : 'not-authenticated') . '">
        ' . ($isAuthenticated
            ? '<strong>✓ Autenticado como: ' . htmlspecialchars($user->login) . '</strong> - Viendo todos los endpoints disponibles.
               <button class="auth-btn logout" onclick="logout()">Cerrar sesión</button>'
            : '<strong>⚠ No autenticado</strong> - Solo endpoints públicos visibles.
               <input type="text" id="apikey-input" class="auth-input" placeholder="Introduce tu DOLAPIKEY">
               <button class="auth-btn" onclick="authenticate()">Ver todos los endpoints</button>'
        ) . '
    </div>
    <div id="swagger-ui"></div>
    <script src="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.9.0/swagger-ui-standalone-preset.js"></script>
    <script>
        // Función para obtener API key de Swagger UI localStorage
        function getSwaggerApiKey() {
            try {
                var stored = localStorage.getItem("authorized");
                if (stored) {
                    var auth = JSON.parse(stored);
                    // Buscar en ApiKeyHeader, ApiKeyQuery o BearerAuth
                    if (auth.ApiKeyHeader && auth.ApiKeyHeader.value) return auth.ApiKeyHeader.value;
                    if (auth.ApiKeyQuery && auth.ApiKeyQuery.value) return auth.ApiKeyQuery.value;
                    if (auth.BearerAuth && auth.BearerAuth.value) return auth.BearerAuth.value;
                }
            } catch(e) {}
            return null;
        }

        // API Key: prioridad URL > mi localStorage > Swagger localStorage
        var urlParams = new URLSearchParams(window.location.search);
        var currentApiKey = urlParams.get("DOLAPIKEY")
                         || localStorage.getItem("easyapi_dolapikey")
                         || getSwaggerApiKey()
                         || "";
        var baseOpenApiUrl = "' . $openApiUrl . '";

        // Si encontramos API key de Swagger pero no está en URL, redirigir
        if (!urlParams.get("DOLAPIKEY") && currentApiKey) {
            window.location.href = window.location.pathname + "?DOLAPIKEY=" + encodeURIComponent(currentApiKey);
        }

        function authenticate() {
            var apiKey = document.getElementById("apikey-input").value.trim();
            if (!apiKey) {
                alert("Por favor, introduce tu DOLAPIKEY");
                return;
            }
            localStorage.setItem("easyapi_dolapikey", apiKey);
            window.location.href = window.location.pathname + "?DOLAPIKEY=" + encodeURIComponent(apiKey);
        }

        function logout() {
            localStorage.removeItem("easyapi_dolapikey");
            localStorage.removeItem("authorized"); // Limpiar también Swagger
            window.location.href = window.location.pathname;
        }

        // Listener para Enter en el input
        document.addEventListener("DOMContentLoaded", function() {
            var input = document.getElementById("apikey-input");
            if (input) {
                input.addEventListener("keypress", function(e) {
                    if (e.key === "Enter") authenticate();
                });
                // Pre-rellenar si hay guardada
                if (currentApiKey) input.value = currentApiKey;
            }
        });

        window.onload = function() {
            // Configurar Swagger UI
            var swaggerConfig = {
                url: baseOpenApiUrl + (currentApiKey ? "?DOLAPIKEY=" + encodeURIComponent(currentApiKey) : ""),
                dom_id: "#swagger-ui",
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout",
                persistAuthorization: true,
                displayRequestDuration: true,
                filter: true,
                showExtensions: true,
                showCommonExtensions: true
            };

            // Si hay API key, configurar interceptor para TODAS las peticiones
            if (currentApiKey) {
                swaggerConfig.requestInterceptor = function(req) {
                    req.headers["DOLAPIKEY"] = currentApiKey;
                    return req;
                };
            }

            window.ui = SwaggerUIBundle(swaggerConfig);

            // Monitorear cambios en localStorage (cuando Swagger guarda auth)
            window.addEventListener("storage", function(e) {
                if (e.key === "authorized" && e.newValue) {
                    var newApiKey = getSwaggerApiKey();
                    if (newApiKey && newApiKey !== currentApiKey) {
                        localStorage.setItem("easyapi_dolapikey", newApiKey);
                        window.location.href = window.location.pathname + "?DOLAPIKEY=" + encodeURIComponent(newApiKey);
                    }
                }
            });

            // Observar el botón Authorize para detectar cuando se cierra el modal
            setInterval(function() {
                var newApiKey = getSwaggerApiKey();
                if (newApiKey && newApiKey !== currentApiKey && !urlParams.get("DOLAPIKEY")) {
                    localStorage.setItem("easyapi_dolapikey", newApiKey);
                    window.location.href = window.location.pathname + "?DOLAPIKEY=" + encodeURIComponent(newApiKey);
                }
            }, 1000);
        };
    </script>
</body>
</html>';
    }

    /**
     * Obtiene las rutas registradas
     *
     * @return array
     */
    public function getRegisteredRoutes(): array
    {
        return $this->registeredRoutes;
    }

    /**
     * Agrupa rutas por tag para debug
     *
     * @param array $routes
     * @return array
     */
    public function groupRoutesByTag(array $routes): array
    {
        $grouped = array();
        foreach ($routes as $route) {
            $tags = isset($route['tags']) ? $route['tags'] : array('Untagged');
            foreach ($tags as $tag) {
                if (!isset($grouped[$tag])) {
                    $grouped[$tag] = array();
                }
                $grouped[$tag][] = $route['method'] . ' ' . $route['path'];
            }
        }
        return $grouped;
    }

    /**
     * Helper para generar respuesta JSON
     *
     * @param Response $response
     * @param array $data
     * @param int $status
     * @return Response
     */
    public function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response = $response->withHeader('Content-Type', 'application/json');
        $response = $response->withStatus($status);
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $response;
    }

    /**
     * Helper para respuesta de error
     *
     * @param Response $response
     * @param string $message
     * @param int $code
     * @param array $extra
     * @return Response
     */
    public function errorResponse(Response $response, string $message, int $code = 400, array $extra = array()): Response
    {
        $data = array(
            'success' => false,
            'error' => array_merge(array(
                'code' => $code,
                'message' => $message
            ), $extra)
        );
        return $this->jsonResponse($response, $data, $code);
    }

    /**
     * Helper para respuesta exitosa
     *
     * @param Response $response
     * @param mixed $data
     * @param int $status
     * @param array $meta
     * @return Response
     */
    public function successResponse(Response $response, $data, int $status = 200, array $meta = array()): Response
    {
        $result = array(
            'success' => true,
            'data' => $data
        );

        if (!empty($meta)) {
            $result['meta'] = $meta;
        }

        return $this->jsonResponse($response, $result, $status);
    }

    /**
     * Helper para respuesta paginada
     *
     * @param Response $response
     * @param array $items
     * @param int $total
     * @param int $page
     * @param int $perPage
     * @return Response
     */
    public function paginatedResponse(Response $response, array $items, int $total, int $page = 1, int $perPage = 20): Response
    {
        $lastPage = (int) ceil($total / $perPage);

        $response = $response->withHeader('X-Total-Count', (string) $total);
        $response = $response->withHeader('X-Page', (string) $page);
        $response = $response->withHeader('X-Per-Page', (string) $perPage);
        $response = $response->withHeader('X-Last-Page', (string) $lastPage);

        return $this->successResponse($response, $items, 200, array(
            'pagination' => array(
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => $lastPage,
                'has_more' => $page < $lastPage
            )
        ));
    }

    /**
     * Verifica permisos del usuario
     *
     * @param Request $request
     * @param string $module
     * @param string $permission
     * @return bool
     */
    public function checkPermission(Request $request, string $module, string $permission): bool
    {
        $user = $request->getAttribute('dolibarr_user');

        if (!$user) {
            return false;
        }

        // Admin tiene todos los permisos
        if ($user->admin) {
            return true;
        }

        // Verificar permiso específico
        return !empty($user->rights->$module->$permission);
    }

    /**
     * Registra un grupo de rutas con prefijo
     *
     * @param string $prefix
     * @param callable $callback
     * @return \Slim\Routing\RouteGroupInterface
     */
    public function group(string $prefix, callable $callback)
    {
        return $this->app->group($prefix, $callback);
    }

    /**
     * Añade un middleware personalizado
     *
     * @param mixed $middleware
     */
    public function addMiddleware($middleware): void
    {
        $this->app->add($middleware);
    }
}

// Función global helper para respuestas JSON (usada en rutas)
if (!function_exists('jsonResponse')) {
    function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response = $response->withHeader('Content-Type', 'application/json');
        $response = $response->withStatus($status);
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $response;
    }
}
