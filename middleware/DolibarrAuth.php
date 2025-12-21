<?php
/* Copyright (C) 2025 Alberto Luque Rivas <aluquerivasdev@gmail.com>
 *
 * EasyAPI - Middleware de Autenticación DOLAPIKEY
 *
 * Este middleware valida la cabecera DOLAPIKEY y carga el usuario
 * correspondiente con todos sus permisos.
 */

namespace EasyApi\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Middleware de autenticación para Dolibarr usando DOLAPIKEY
 *
 * Busca el API key en:
 * 1. Header 'DOLAPIKEY'
 * 2. Header 'Authorization: Bearer <key>'
 * 3. Query param 'api_key' (menos seguro, solo para debugging)
 */
class DolibarrAuth implements MiddlewareInterface
{
    /** @var \DoliDB */
    private $db;

    /** @var array Rutas públicas estáticas que no requieren autenticación */
    private $publicRoutes = array();

    /** @var bool Modo estricto (rechaza si no hay API key) */
    private $strictMode = true;

    /** @var callable|null Callback para verificar rutas públicas dinámicamente */
    private $publicRouteChecker = null;

    /**
     * Constructor
     *
     * @param \DoliDB $db Conexión a base de datos
     * @param array $options Opciones de configuración
     *                       - publicRoutes: array de rutas públicas estáticas
     *                       - strictMode: si rechaza cuando no hay API key
     *                       - publicRouteChecker: callable que recibe (path, method) y retorna bool
     */
    public function __construct($db, array $options = array())
    {
        $this->db = $db;

        if (isset($options['publicRoutes'])) {
            $this->publicRoutes = $options['publicRoutes'];
        }

        if (isset($options['strictMode'])) {
            $this->strictMode = $options['strictMode'];
        }

        if (isset($options['publicRouteChecker']) && is_callable($options['publicRouteChecker'])) {
            $this->publicRouteChecker = $options['publicRouteChecker'];
        }
    }

    /**
     * Procesa la petición
     *
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $fullPath = $request->getUri()->getPath();
        $method = $request->getMethod();

        // Extraer solo la parte relativa de la ruta (sin el base path)
        $scriptName = isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '';
        $basePath = rtrim(dirname($scriptName), '/');
        $path = $fullPath;
        if (!empty($basePath) && strpos($fullPath, $basePath) === 0) {
            $path = substr($fullPath, strlen($basePath));
        }
        if (empty($path)) {
            $path = '/';
        }

        // Obtener API Key (header, Bearer o query param)
        $apiKey = $this->extractApiKey($request);

        // Si hay API key, intentar autenticar
        if (!empty($apiKey)) {
            $user = $this->authenticateApiKey($apiKey);

            if ($user) {
                // Verificar que el usuario está activo
                if ($user->statut != 1) {
                    return $this->unauthorizedResponse('User account is disabled');
                }

                // Cargar permisos del usuario
                $user->getrights();

                // Añadir usuario a la request
                $request = $request->withAttribute('dolibarr_user', $user);
                $request = $request->withAttribute('dolibarr_db', $this->db);

                // También disponible globalmente (compatibilidad con código Dolibarr)
                global $globalUser;
                $globalUser = $user;

                return $handler->handle($request);
            } else {
                return $this->unauthorizedResponse('Invalid API key');
            }
        }

        // No hay API key - verificar si es una ruta pública
        if ($this->isPublicRoute($path, $method)) {
            // Ruta pública - continuar sin usuario
            return $handler->handle($request);
        }

        // No hay API key y no es ruta pública
        return $this->unauthorizedResponse('DOLAPIKEY header or api_key parameter is required');
    }

    /**
     * Extrae el API Key de la petición
     *
     * Busca en este orden:
     * 1. Header DOLAPIKEY (método preferido - compatibilidad Dolibarr)
     * 2. Header Authorization: Bearer <key>
     * 3. Query param DOLAPIKEY
     * 4. Query param api_key
     *
     * @param Request $request
     * @return string|null
     */
    private function extractApiKey(Request $request): ?string
    {
        // 1. Header DOLAPIKEY (método preferido - compatibilidad con API nativa de Dolibarr)
        $apiKey = $request->getHeaderLine('DOLAPIKEY');
        if (!empty($apiKey)) {
            return $apiKey;
        }

        // 2. Header Authorization: Bearer
        $authHeader = $request->getHeaderLine('Authorization');
        if (!empty($authHeader) && preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // 3. Query params (DOLAPIKEY o api_key)
        $queryParams = $request->getQueryParams();

        if (!empty($queryParams['DOLAPIKEY'])) {
            return $queryParams['DOLAPIKEY'];
        }

        if (!empty($queryParams['api_key'])) {
            return $queryParams['api_key'];
        }

        return null;
    }

    /**
     * Autentica el API Key y devuelve el usuario
     *
     * @param string $apiKey
     * @return \User|null
     */
    private function authenticateApiKey(string $apiKey): ?\User
    {
        global $conf;

        // Buscar en llx_user el usuario con este api_key
        $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "user";
        $sql .= " WHERE api_key = '" . $this->db->escape($apiKey) . "'";
        $sql .= " AND statut = 1"; // Solo usuarios activos
        $sql .= " AND entity IN (0, " . ((int) $conf->entity) . ")";

        $result = $this->db->query($sql);

        if ($result && $this->db->num_rows($result) > 0) {
            $obj = $this->db->fetch_object($result);

            require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';
            $user = new \User($this->db);

            if ($user->fetch($obj->rowid) > 0) {
                return $user;
            }
        }

        return null;
    }

    /**
     * Verifica si la ruta es pública
     *
     * @param string $path
     * @param string $method
     * @return bool
     */
    private function isPublicRoute(string $path, string $method = 'GET'): bool
    {
        // Primero verificar con el checker dinámico si está configurado
        if ($this->publicRouteChecker !== null) {
            $checker = $this->publicRouteChecker;
            if ($checker($path, $method)) {
                return true;
            }
        }

        // Luego verificar en las rutas estáticas
        foreach ($this->publicRoutes as $route) {
            // Soporte para wildcards simples
            $pattern = str_replace('*', '.*', $route);
            if (preg_match('#^' . $pattern . '$#', $path)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Genera respuesta 401 Unauthorized
     *
     * @param string $message
     * @return Response
     */
    private function unauthorizedResponse(string $message): Response
    {
        $response = new SlimResponse();
        $response = $response->withStatus(401);
        $response = $response->withHeader('Content-Type', 'application/json');
        $response = $response->withHeader('WWW-Authenticate', 'DOLAPIKEY');

        $body = json_encode(array(
            'success' => false,
            'error' => array(
                'code' => 401,
                'message' => $message
            )
        ), JSON_PRETTY_PRINT);

        $response->getBody()->write($body);
        return $response;
    }

    /**
     * Añadir una ruta pública
     *
     * @param string $route
     * @return self
     */
    public function addPublicRoute(string $route): self
    {
        $this->publicRoutes[] = $route;
        return $this;
    }
}
