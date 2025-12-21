<?php
/* Copyright (C) 2025 Alberto Luque Rivas <aluquerivasdev@gmail.com>
 *
 * EAPI Resource - Clase base para crear endpoints de forma simple
 *
 * Esta clase facilita la creación de APIs REST de manera extremadamente
 * sencilla para desarrolladores de módulos de terceros.
 *
 * GUÍA RÁPIDA:
 * -----------
 * 1. Crear carpeta: custom/tumodulo/eapi/
 * 2. Crear archivo: custom/tumodulo/eapi/MiRecursoResource.php
 * 3. Extender esta clase e implementar registerRoutes()
 *
 * ¡Y LISTO! Los endpoints se registran automáticamente.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

namespace EasyApi;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Routing\RouteCollectorProxy;

/**
 * Clase base para crear recursos EAPI
 *
 * Extiende esta clase para crear tus propios endpoints de forma simple.
 *
 * EJEMPLO MÍNIMO:
 * ```php
 * class ProductosResource extends EasyApiResource
 * {
 *     protected function registerRoutes(): void
 *     {
 *         // GET /tumodulo/productos
 *         $this->get('/', 'Listar productos', function($req, $res) {
 *             return $this->ok($res, ['productos' => []]);
 *         });
 *
 *         // GET /tumodulo/productos/{id}
 *         $this->get('/{id}', 'Ver producto', function($req, $res, $args) {
 *             return $this->ok($res, ['id' => $args['id']]);
 *         });
 *     }
 * }
 * ```
 */
abstract class EasyApiResource
{
    // =========================================================================
    // PROPIEDADES PROTEGIDAS - Disponibles en tus clases hijas
    // =========================================================================

    /** @var \Slim\App Instancia de Slim */
    protected $app;

    /** @var \DoliDB Conexión a base de datos */
    protected $db;

    /** @var \ApiEasyApi Instancia principal de EasyAPI */
    protected $api;

    /** @var \User|null Usuario autenticado (null si no autenticado) */
    protected $user;

    /** @var string Prefijo de ruta calculado automáticamente */
    protected $prefix = '';

    /** @var string Nombre del módulo padre */
    protected $moduleName = '';

    /** @var string Nombre del recurso (sin 'Resource') */
    protected $resourceName = '';

    /** @var \Slim\Routing\RouteCollectorProxy Grupo de rutas actual */
    protected $group;

    /** @var array Tags por defecto para documentación */
    protected $defaultTags = array();

    // =========================================================================
    // PROPIEDADES CONFIGURABLES - Sobrescribe en tu clase
    // =========================================================================

    /**
     * Descripción del recurso para documentación
     * @var string
     */
    protected $description = '';

    /**
     * ¿Todas las rutas son públicas por defecto?
     * @var bool
     */
    protected $publicByDefault = false;

    /**
     * Permiso requerido por defecto para todas las rutas
     * Formato: 'modulo->permiso' ej: 'facture->lire'
     * @var string|null
     */
    protected $defaultPermission = null;

    // =========================================================================
    // ESTADO INTERNO
    // =========================================================================

    /** @var array Rutas pendientes de registrar */
    private $pendingRoutes = array();

    /** @var array Parámetros de la ruta actual */
    private $currentRouteParams = array();

    // =========================================================================
    // CONSTRUCTOR Y MÉTODOS INTERNOS
    // =========================================================================

    /**
     * Constructor - NO SOBRESCRIBIR
     *
     * @param \Slim\App $app
     * @param \DoliDB $db
     * @param \ApiEasyApi $api
     * @param string $moduleName
     * @param string $prefix
     */
    final public function __construct($app, $db, $api, $moduleName, $prefix)
    {
        $this->app = $app;
        $this->db = $db;
        $this->api = $api;
        $this->moduleName = $moduleName;
        $this->prefix = $prefix;

        // Extraer nombre del recurso desde el nombre de la clase
        $className = (new \ReflectionClass($this))->getShortName();
        $this->resourceName = preg_replace('/Resource$/', '', $className);

        // Tag por defecto = ModuleName / ResourceName (único por recurso)
        $this->defaultTags = array(ucfirst($moduleName) . ' - ' . $this->resourceName);

        // Llamar inicialización personalizada
        $this->init();
    }

    /**
     * Método de inicialización - SOBRESCRIBIR si necesitas inicializar algo
     */
    protected function init(): void
    {
        // Sobrescribir en tu clase si necesitas inicialización
    }

    /**
     * Registrar las rutas - DEBES IMPLEMENTAR ESTE MÉTODO
     */
    abstract protected function registerRoutes(): void;

    /**
     * Ejecuta el registro de rutas - llamado por EasyAPI
     * @internal
     */
    final public function register(): void
    {
        $self = $this;
        $prefix = $this->prefix;

        $this->app->group($prefix, function (RouteCollectorProxy $group) use ($self) {
            $self->group = $group;
            $self->registerRoutes();
        });

        // Registrar tag si hay descripción
        if (!empty($this->description)) {
            $this->api->getOpenApiGenerator()->addTag(
                $this->defaultTags[0],
                $this->description
            );
        }
    }

    // =========================================================================
    // MÉTODOS PARA REGISTRAR RUTAS - Usa estos en registerRoutes()
    // =========================================================================

    /**
     * Registra un GET endpoint
     *
     * @param string $path Ruta relativa (ej: '/', '/{id}', '/buscar')
     * @param string $summary Descripción corta para documentación
     * @param callable $handler Función que maneja la petición
     * @return self Para encadenamiento fluido
     *
     * @example
     * $this->get('/', 'Listar productos', function($req, $res) {
     *     return $this->ok($res, ['productos' => $this->listarProductos()]);
     * });
     */
    public function get(string $path, string $summary, callable $handler): self
    {
        return $this->route('GET', $path, $summary, $handler);
    }

    /**
     * Registra un POST endpoint
     *
     * @param string $path Ruta relativa
     * @param string $summary Descripción corta
     * @param callable $handler Función handler
     * @return self
     *
     * @example
     * $this->post('/', 'Crear producto', function($req, $res) {
     *     $data = $this->getBody($req);
     *     return $this->created($res, ['id' => 123]);
     * });
     */
    public function post(string $path, string $summary, callable $handler): self
    {
        return $this->route('POST', $path, $summary, $handler);
    }

    /**
     * Registra un PUT endpoint
     *
     * @param string $path Ruta relativa
     * @param string $summary Descripción corta
     * @param callable $handler Función handler
     * @return self
     */
    public function put(string $path, string $summary, callable $handler): self
    {
        return $this->route('PUT', $path, $summary, $handler);
    }

    /**
     * Registra un PATCH endpoint
     *
     * @param string $path Ruta relativa
     * @param string $summary Descripción corta
     * @param callable $handler Función handler
     * @return self
     */
    public function patch(string $path, string $summary, callable $handler): self
    {
        return $this->route('PATCH', $path, $summary, $handler);
    }

    /**
     * Registra un DELETE endpoint
     *
     * @param string $path Ruta relativa
     * @param string $summary Descripción corta
     * @param callable $handler Función handler
     * @return self
     */
    public function delete(string $path, string $summary, callable $handler): self
    {
        return $this->route('DELETE', $path, $summary, $handler);
    }

    // =========================================================================
    // MODIFICADORES DE RUTA - Encadenar después de get/post/etc
    // =========================================================================

    /**
     * Marca la ruta como pública (no requiere autenticación)
     *
     * @return self
     *
     * @example
     * $this->get('/info', 'Información pública')->public();
     */
    public function public(): self
    {
        $this->currentRouteParams['public'] = true;
        return $this;
    }

    /**
     * Marca la ruta como privada (requiere autenticación)
     *
     * @return self
     */
    public function private(): self
    {
        $this->currentRouteParams['public'] = false;
        return $this;
    }

    /**
     * Establece los permisos requeridos para la ruta
     *
     * @param string|array $permissions Permiso(s) requeridos
     * @param string $mode 'all' = todos requeridos, 'any' = al menos uno
     * @return self
     *
     * @example
     * // Requiere permiso de lectura de facturas
     * $this->get('/', 'Listar')
     *      ->requirePermission('facture->lire');
     *
     * // Requiere TODOS los permisos
     * $this->post('/', 'Crear')
     *      ->requirePermission(['facture->creer', 'produit->lire'], 'all');
     *
     * // Requiere AL MENOS UNO de los permisos
     * $this->get('/report', 'Reporte')
     *      ->requirePermission(['facture->lire', 'commande->lire'], 'any');
     */
    public function requirePermission($permissions, string $mode = 'all'): self
    {
        $this->currentRouteParams['permissions'] = is_array($permissions) ? $permissions : array($permissions);
        $this->currentRouteParams['permissionMode'] = $mode;
        return $this;
    }

    /**
     * Añade un middleware personalizado a la ruta
     *
     * @param mixed $middleware Middleware PSR-15 o callable
     * @return self
     *
     * @example
     * $this->get('/cached', 'Datos cacheados')
     *      ->middleware(new CacheMiddleware(3600));
     */
    public function middleware($middleware): self
    {
        if (!isset($this->currentRouteParams['middlewares'])) {
            $this->currentRouteParams['middlewares'] = array();
        }
        $this->currentRouteParams['middlewares'][] = $middleware;
        return $this;
    }

    /**
     * Define los tags de documentación para esta ruta
     *
     * @param string|array $tags Tag(s) para documentación OpenAPI
     * @return self
     */
    public function tags($tags): self
    {
        $this->currentRouteParams['tags'] = is_array($tags) ? $tags : array($tags);
        return $this;
    }

    /**
     * Añade descripción detallada a la documentación
     *
     * @param string $description Descripción larga
     * @return self
     */
    public function describe(string $description): self
    {
        $this->currentRouteParams['description'] = $description;
        return $this;
    }

    /**
     * Define parámetros de path para documentación
     *
     * @param array $params Array de definiciones de parámetros
     * @return self
     *
     * @example
     * $this->get('/{id}', 'Ver producto')
     *      ->pathParams([
     *          'id' => ['type' => 'integer', 'description' => 'ID del producto']
     *      ]);
     */
    public function pathParams(array $params): self
    {
        $this->currentRouteParams['pathParams'] = $params;
        return $this;
    }

    /**
     * Define parámetros de query para documentación
     *
     * @param array $params Array de definiciones de parámetros
     * @return self
     *
     * @example
     * $this->get('/', 'Listar productos')
     *      ->queryParams([
     *          'limit' => ['type' => 'integer', 'description' => 'Límite de resultados'],
     *          'search' => ['type' => 'string', 'description' => 'Búsqueda por nombre']
     *      ]);
     */
    public function queryParams(array $params): self
    {
        $this->currentRouteParams['queryParams'] = $params;
        return $this;
    }

    /**
     * Define el schema del body de la petición
     *
     * @param array $schema Schema JSON para documentación
     * @return self
     *
     * @example
     * $this->post('/', 'Crear producto')
     *      ->body([
     *          'required' => ['nombre', 'precio'],
     *          'properties' => [
     *              'nombre' => ['type' => 'string'],
     *              'precio' => ['type' => 'number'],
     *              'descripcion' => ['type' => 'string']
     *          ]
     *      ]);
     */
    public function body(array $schema): self
    {
        $this->currentRouteParams['requestBody'] = $schema;
        return $this;
    }

    // =========================================================================
    // MÉTODOS DE RESPUESTA - Usa estos en tus handlers
    // =========================================================================

    /**
     * Respuesta exitosa (200 OK)
     *
     * @param Response $response
     * @param mixed $data Datos a devolver
     * @return Response
     */
    protected function ok(Response $response, $data): Response
    {
        return $this->api->successResponse($response, $data);
    }

    /**
     * Recurso creado (201 Created)
     *
     * @param Response $response
     * @param mixed $data Datos del recurso creado
     * @return Response
     */
    protected function created(Response $response, $data): Response
    {
        return $this->api->successResponse($response, $data, 201);
    }

    /**
     * Sin contenido (204 No Content)
     *
     * @param Response $response
     * @return Response
     */
    protected function noContent(Response $response): Response
    {
        return $response->withStatus(204);
    }

    /**
     * Error de cliente (400 Bad Request)
     *
     * @param Response $response
     * @param string $message Mensaje de error
     * @return Response
     */
    protected function badRequest(Response $response, string $message = 'Bad request'): Response
    {
        return $this->api->errorResponse($response, $message, 400);
    }

    /**
     * No autorizado (401 Unauthorized)
     *
     * @param Response $response
     * @param string $message
     * @return Response
     */
    protected function unauthorized(Response $response, string $message = 'Unauthorized'): Response
    {
        return $this->api->errorResponse($response, $message, 401);
    }

    /**
     * Prohibido (403 Forbidden)
     *
     * @param Response $response
     * @param string $message
     * @return Response
     */
    protected function forbidden(Response $response, string $message = 'Forbidden'): Response
    {
        return $this->api->errorResponse($response, $message, 403);
    }

    /**
     * No encontrado (404 Not Found)
     *
     * @param Response $response
     * @param string $message
     * @return Response
     */
    protected function notFound(Response $response, string $message = 'Not found'): Response
    {
        return $this->api->errorResponse($response, $message, 404);
    }

    /**
     * Error interno (500 Internal Server Error)
     *
     * @param Response $response
     * @param string $message
     * @return Response
     */
    protected function error(Response $response, string $message = 'Internal error'): Response
    {
        return $this->api->errorResponse($response, $message, 500);
    }

    /**
     * Respuesta JSON personalizada
     *
     * @param Response $response
     * @param mixed $data
     * @param int $status Código HTTP
     * @return Response
     */
    protected function json(Response $response, $data, int $status = 200): Response
    {
        return $this->api->jsonResponse($response->withStatus($status), array(
            'success' => $status >= 200 && $status < 300,
            'data' => $data
        ));
    }

    // =========================================================================
    // MÉTODOS HELPER - Utilidades para tus handlers
    // =========================================================================

    /**
     * Obtiene el body de la petición como array
     *
     * @param Request $request
     * @return array
     */
    protected function getBody(Request $request): array
    {
        $body = json_decode((string) $request->getBody(), true);
        return is_array($body) ? $body : array();
    }

    /**
     * Valida el body de la petición según un schema
     *
     * EJEMPLO DE USO:
     * ```php
     * $validation = $this->validateBody($req, array(
     *     'required' => array('name', 'email'),
     *     'properties' => array(
     *         'name' => array('type' => 'string', 'minLength' => 2, 'maxLength' => 100),
     *         'email' => array('type' => 'string', 'format' => 'email'),
     *         'age' => array('type' => 'integer', 'min' => 0, 'max' => 150),
     *         'active' => array('type' => 'boolean'),
     *         'tags' => array('type' => 'array')
     *     )
     * ));
     *
     * if (!$validation['valid']) {
     *     return $this->badRequest($res, $validation['error']);
     * }
     *
     * $data = $validation['data']; // Body validado y saneado
     * ```
     *
     * @param Request $request La petición
     * @param array $schema Schema de validación con 'required' y 'properties'
     * @return array ['valid' => bool, 'data' => array, 'error' => string|null, 'errors' => array]
     */
    protected function validateBody(Request $request, array $schema): array
    {
        $body = $this->getBody($request);
        $errors = array();
        $validatedData = array();

        $required = isset($schema['required']) ? $schema['required'] : array();
        $properties = isset($schema['properties']) ? $schema['properties'] : array();

        // Validar campos requeridos
        foreach ($required as $field) {
            if (!isset($body[$field]) || $body[$field] === '' || $body[$field] === null) {
                $errors[] = "El campo '$field' es obligatorio";
            }
        }

        // Validar cada propiedad definida
        foreach ($properties as $field => $rules) {
            // Si el campo no está presente y no es requerido, ignorar
            if (!isset($body[$field])) {
                continue;
            }

            $value = $body[$field];
            $type = isset($rules['type']) ? $rules['type'] : 'string';

            // Validar tipo
            $typeError = $this->validateType($value, $type, $field);
            if ($typeError) {
                $errors[] = $typeError;
                continue;
            }

            // Validaciones específicas por tipo
            switch ($type) {
                case 'string':
                    // minLength
                    if (isset($rules['minLength']) && strlen($value) < $rules['minLength']) {
                        $errors[] = "El campo '$field' debe tener al menos {$rules['minLength']} caracteres";
                    }
                    // maxLength
                    if (isset($rules['maxLength']) && strlen($value) > $rules['maxLength']) {
                        $errors[] = "El campo '$field' no debe exceder {$rules['maxLength']} caracteres";
                    }
                    // format
                    if (isset($rules['format'])) {
                        $formatError = $this->validateFormat($value, $rules['format'], $field);
                        if ($formatError) {
                            $errors[] = $formatError;
                        }
                    }
                    // enum
                    if (isset($rules['enum']) && !in_array($value, $rules['enum'])) {
                        $errors[] = "El campo '$field' debe ser uno de: " . implode(', ', $rules['enum']);
                    }
                    // pattern (regex)
                    if (isset($rules['pattern']) && !preg_match('/' . $rules['pattern'] . '/', $value)) {
                        $errors[] = "El campo '$field' no tiene el formato válido";
                    }
                    break;

                case 'integer':
                case 'number':
                    // min
                    if (isset($rules['min']) && $value < $rules['min']) {
                        $errors[] = "El campo '$field' debe ser mayor o igual a {$rules['min']}";
                    }
                    // max
                    if (isset($rules['max']) && $value > $rules['max']) {
                        $errors[] = "El campo '$field' debe ser menor o igual a {$rules['max']}";
                    }
                    break;

                case 'array':
                    // minItems
                    if (isset($rules['minItems']) && count($value) < $rules['minItems']) {
                        $errors[] = "El campo '$field' debe tener al menos {$rules['minItems']} elementos";
                    }
                    // maxItems
                    if (isset($rules['maxItems']) && count($value) > $rules['maxItems']) {
                        $errors[] = "El campo '$field' no debe exceder {$rules['maxItems']} elementos";
                    }
                    break;
            }

            // Agregar al data validado
            $validatedData[$field] = $value;
        }

        // Agregar campos no definidos en properties pero presentes en body (flexibilidad)
        foreach ($body as $field => $value) {
            if (!isset($validatedData[$field]) && !isset($properties[$field])) {
                $validatedData[$field] = $value;
            }
        }

        if (!empty($errors)) {
            return array(
                'valid' => false,
                'data' => array(),
                'error' => $errors[0], // Primer error para badRequest simple
                'errors' => $errors    // Todos los errores para respuesta detallada
            );
        }

        return array(
            'valid' => true,
            'data' => $validatedData,
            'error' => null,
            'errors' => array()
        );
    }

    /**
     * Valida el tipo de un valor
     *
     * @param mixed $value
     * @param string $type
     * @param string $field
     * @return string|null Error message or null if valid
     */
    private function validateType($value, string $type, string $field): ?string
    {
        switch ($type) {
            case 'string':
                if (!is_string($value)) {
                    return "El campo '$field' debe ser texto";
                }
                break;

            case 'integer':
                if (!is_int($value) && !ctype_digit(strval($value))) {
                    return "El campo '$field' debe ser un número entero";
                }
                break;

            case 'number':
                if (!is_numeric($value)) {
                    return "El campo '$field' debe ser un número";
                }
                break;

            case 'boolean':
                if (!is_bool($value) && $value !== 0 && $value !== 1 && $value !== '0' && $value !== '1') {
                    return "El campo '$field' debe ser verdadero o falso";
                }
                break;

            case 'array':
                if (!is_array($value)) {
                    return "El campo '$field' debe ser un array";
                }
                break;

            case 'object':
                if (!is_array($value) && !is_object($value)) {
                    return "El campo '$field' debe ser un objeto";
                }
                break;
        }

        return null;
    }

    /**
     * Valida formatos especiales de string
     *
     * @param string $value
     * @param string $format
     * @param string $field
     * @return string|null Error message or null if valid
     */
    private function validateFormat(string $value, string $format, string $field): ?string
    {
        switch ($format) {
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    return "El campo '$field' debe ser un email válido";
                }
                break;

            case 'url':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    return "El campo '$field' debe ser una URL válida";
                }
                break;

            case 'date':
                // Formato YYYY-MM-DD
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    return "El campo '$field' debe tener formato de fecha (YYYY-MM-DD)";
                }
                $parts = explode('-', $value);
                if (!checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0])) {
                    return "El campo '$field' no es una fecha válida";
                }
                break;

            case 'datetime':
                // Formato ISO 8601
                if (!preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}/', $value)) {
                    return "El campo '$field' debe tener formato datetime (YYYY-MM-DD HH:MM:SS)";
                }
                break;

            case 'time':
                if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) {
                    return "El campo '$field' debe tener formato de hora (HH:MM:SS)";
                }
                break;

            case 'uuid':
                if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
                    return "El campo '$field' debe ser un UUID válido";
                }
                break;

            case 'phone':
                // Acepta formatos internacionales básicos
                if (!preg_match('/^[\d\s\-\+\(\)]{7,20}$/', $value)) {
                    return "El campo '$field' debe ser un teléfono válido";
                }
                break;

            case 'ip':
                if (!filter_var($value, FILTER_VALIDATE_IP)) {
                    return "El campo '$field' debe ser una IP válida";
                }
                break;
        }

        return null;
    }

    /**
     * Obtiene un parámetro del query string
     *
     * @param Request $request
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    protected function query(Request $request, string $name, $default = null)
    {
        $params = $request->getQueryParams();
        return isset($params[$name]) ? $params[$name] : $default;
    }

    /**
     * Obtiene el usuario autenticado de la petición
     *
     * @param Request $request
     * @return \User|null
     */
    protected function getUser(Request $request): ?\User
    {
        return $request->getAttribute('dolibarr_user');
    }

    /**
     * Verifica si el usuario tiene un permiso específico
     *
     * @param Request $request
     * @param string $permission Formato: 'modulo->permiso'
     * @return bool
     */
    protected function hasPermission(Request $request, string $permission): bool
    {
        $user = $this->getUser($request);
        if (!$user) {
            return false;
        }

        if (strpos($permission, '->') === false) {
            return false;
        }

        list($module, $perm) = explode('->', $permission, 2);

        // Verificar subpermisos (ej: 'facture->creer' => $user->rights->facture->creer)
        $parts = explode('->', $perm);
        $rights = $user->rights;

        if (!isset($rights->$module)) {
            return false;
        }

        $current = $rights->$module;
        foreach ($parts as $part) {
            if (!isset($current->$part)) {
                return false;
            }
            $current = $current->$part;
        }

        return !empty($current);
    }

    /**
     * Ejecuta una consulta SQL y devuelve los resultados
     *
     * @param string $sql
     * @return array
     */
    protected function fetchAll(string $sql): array
    {
        $result = array();
        $resql = $this->db->query($sql);

        if ($resql) {
            while ($row = $this->db->fetch_array($resql)) {
                $result[] = $row;
            }
            $this->db->free($resql);
        }

        return $result;
    }

    /**
     * Ejecuta una consulta SQL y devuelve una sola fila
     *
     * @param string $sql
     * @return array|null
     */
    protected function fetchOne(string $sql): ?array
    {
        $resql = $this->db->query($sql);

        if ($resql && $this->db->num_rows($resql) > 0) {
            $row = $this->db->fetch_array($resql);
            $this->db->free($resql);
            return $row;
        }

        return null;
    }

    /**
     * Carga una clase de Dolibarr
     *
     * @param string $path Path relativo desde DOL_DOCUMENT_ROOT
     * @return void
     *
     * @example
     * $this->loadClass('/product/class/product.class.php');
     * $product = new \Product($this->db);
     */
    protected function loadClass(string $path): void
    {
        require_once DOL_DOCUMENT_ROOT . $path;
    }

    /**
     * Log de debug
     *
     * @param string $message
     * @param int $level LOG_DEBUG, LOG_INFO, LOG_WARNING, LOG_ERR
     */
    protected function log(string $message, int $level = LOG_DEBUG): void
    {
        dol_syslog('EAPI [' . $this->moduleName . '/' . $this->resourceName . '] ' . $message, $level);
    }

    // =========================================================================
    // MÉTODOS INTERNOS
    // =========================================================================

    /**
     * Registra una ruta internamente
     */
    private function route(string $method, string $path, string $summary, callable $handler): self
    {
        // Si hay una ruta pendiente sin finalizar, finalizarla primero
        if (!empty($this->currentRouteParams)) {
            $this->finalize();
        }

        $self = $this;

        // Normalizar path: '/' se convierte en '' para evitar trailing slash
        $slimPath = ($path === '/') ? '' : $path;
        $fullPath = $this->prefix . $slimPath;

        // Log detallado del registro de ruta
        dol_syslog("EAPI Resource [{$this->moduleName}/{$this->resourceName}]: Registering $method '$path' -> slimPath='$slimPath', fullPath='$fullPath' (prefix={$this->prefix})", LOG_INFO);

        // Crear wrapper del handler para inyectar el usuario
        $wrappedHandler = function (Request $request, Response $response, array $args = array()) use ($self, $handler) {
            $self->user = $request->getAttribute('dolibarr_user');
            return call_user_func($handler, $request, $response, $args);
        };

        // Registrar ruta en Slim (usar slimPath sin trailing slash)
        $route = $this->group->map(array(strtoupper($method)), $slimPath, $wrappedHandler);

        // Guardar parámetros actuales
        $this->currentRouteParams = array(
            'slimRoute' => $route,
            'method' => $method,
            'path' => $path,
            'fullPath' => $fullPath,
            'summary' => $summary,
            'handler' => $handler
        );

        return $this;
    }

    /**
     * Finaliza el registro de la ruta actual
     */
    private function finalize(): self
    {
        if (empty($this->currentRouteParams)) {
            return $this;
        }

        $params = $this->currentRouteParams;
        $route = isset($params['slimRoute']) ? $params['slimRoute'] : null;

        // Aplicar middlewares si hay
        if ($route && !empty($params['middlewares'])) {
            foreach ($params['middlewares'] as $mw) {
                $route->add($mw);
            }
        }

        // Aplicar middleware de permisos si hay
        if ($route && !empty($params['permissions'])) {
            $mode = isset($params['permissionMode']) ? $params['permissionMode'] : 'all';
            $perms = $params['permissions'];

            require_once __DIR__ . '/../middleware/RequirePermission.php';

            // Convertir array a string separado por comas para el middleware
            $permsString = is_array($perms) ? implode(',', $perms) : $perms;

            if ($mode === 'any') {
                $route->add(\EasyApi\Middleware\RequirePermission::any($permsString));
            } else {
                if (is_array($perms) && count($perms) === 1) {
                    $route->add(\EasyApi\Middleware\RequirePermission::check($perms[0]));
                } else {
                    $route->add(\EasyApi\Middleware\RequirePermission::all($permsString));
                }
            }
        }

        // Determinar si es pública
        $isPublic = isset($params['public']) ? $params['public'] : $this->publicByDefault;

        // Construir definición para OpenAPI
        $routeDefinition = array(
            'method' => isset($params['method']) ? $params['method'] : 'GET',
            'path' => isset($params['fullPath']) ? $params['fullPath'] : $this->prefix,
            'summary' => isset($params['summary']) ? $params['summary'] : '',
            'description' => isset($params['description']) ? $params['description'] : $params['summary'],
            'tags' => isset($params['tags']) ? $params['tags'] : $this->defaultTags,
            'public' => $isPublic,
            'source' => 'EAPI Resource: ' . $this->moduleName . '/' . $this->resourceName
        );

        // Añadir permisos para documentación
        if (!empty($params['permissions'])) {
            $routeDefinition['permissions'] = $params['permissions'];
        } elseif (!$isPublic && !empty($this->defaultPermission)) {
            $routeDefinition['permissions'] = array($this->defaultPermission);
        }

        // Añadir parámetros
        $parameters = array();

        // Path params
        if (!empty($params['pathParams'])) {
            foreach ($params['pathParams'] as $name => $def) {
                $parameters[] = array(
                    'name' => $name,
                    'in' => 'path',
                    'required' => true,
                    'description' => isset($def['description']) ? $def['description'] : '',
                    'schema' => array('type' => isset($def['type']) ? $def['type'] : 'string')
                );
            }
        }

        // Query params
        if (!empty($params['queryParams'])) {
            foreach ($params['queryParams'] as $name => $def) {
                $parameters[] = array(
                    'name' => $name,
                    'in' => 'query',
                    'required' => !empty($def['required']),
                    'description' => isset($def['description']) ? $def['description'] : '',
                    'schema' => array('type' => isset($def['type']) ? $def['type'] : 'string')
                );
            }
        }

        if (!empty($parameters)) {
            $routeDefinition['parameters'] = $parameters;
        }

        // Request body
        if (!empty($params['requestBody'])) {
            $schema = $params['requestBody'];
            $routeDefinition['requestBody'] = array(
                'required' => true,
                'content' => array(
                    'application/json' => array(
                        'schema' => array(
                            'type' => 'object',
                            'required' => isset($schema['required']) ? $schema['required'] : array(),
                            'properties' => isset($schema['properties']) ? $schema['properties'] : $schema
                        )
                    )
                )
            );
        }

        // Registrar en OpenAPI
        $this->api->addRoute($routeDefinition);

        // Limpiar estado
        $this->currentRouteParams = array();

        return $this;
    }

    /**
     * Destructor - finaliza rutas pendientes
     */
    public function __destruct()
    {
        if (!empty($this->currentRouteParams)) {
            $this->finalize();
        }
    }
}
