<?php
/* Copyright (C) 2025 Alberto Luque Rivas <aluquerivasdev@gmail.com> | EasySoft Tech S.L <info@easysoft.es>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    easyapi/middleware/DolibarrAuth.php
 * \ingroup easyapi
 * \brief   EasyAPI - Middleware de Autenticación DOLAPIKEY
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
        $method = $request->getMethod();

        // Las peticiones preflight CORS (OPTIONS) no llevan credenciales: se dejan
        // pasar para que CorsMiddleware responda con las cabeceras adecuadas.
        if ($method === 'OPTIONS') {
            return $handler->handle($request);
        }

        $fullPath = $request->getUri()->getPath();

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

        // Determinar una sola vez si la ruta es pública
        $isPublic = $this->isPublicRoute($path, $method);

        // Obtener API Key (header, Bearer o query param)
        $apiKey = $this->extractApiKey($request);

        // Si hay API key, intentar autenticar
        if (!empty($apiKey)) {
            $user = $this->authenticateApiKey($apiKey);

            if ($user) {
                // Verificar que el usuario está activo
                if ($user->statut != 1) {
                    // En rutas públicas se sirve igualmente como anónimo
                    if ($isPublic) {
                        return $handler->handle($request);
                    }
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
            }

            // API key inválida: si la ruta es pública, continuar sin usuario
            if ($isPublic) {
                return $handler->handle($request);
            }
            return $this->unauthorizedResponse('Invalid API key');
        }

        // No hay API key - verificar si es una ruta pública
        if ($isPublic) {
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
     * Soporta api_key almacenada en texto plano y cifrada con dolEncrypt()
     * (Dolibarr 17+). Replica la lógica del core (api/class/api_access.class.php):
     * una única consulta indexada que compara contra el valor plano y contra el
     * cifrado con semilla determinista, evitando recorrer toda la tabla de usuarios.
     *
     * @param string $apiKey
     * @return \User|null
     */
    private function authenticateApiKey(string $apiKey): ?\User
    {
        global $conf;

        // Si la clave entrante ya viene cifrada (formato dolcrypt:...), descifrarla
        if (preg_match('/^dolcrypt:/i', $apiKey) && function_exists('dolDecrypt')) {
            $apiKey = dolDecrypt($apiKey);
        }

        if ($apiKey === '') {
            return null;
        }

        // Comparar contra texto plano y, si está disponible, contra el cifrado
        // determinista (misma semilla 'dolibarr' que usa el core de Dolibarr).
        $sql = "SELECT rowid, api_key FROM " . MAIN_DB_PREFIX . "user";
        $sql .= " WHERE (api_key = '" . $this->db->escape($apiKey) . "'";
        if (function_exists('dolEncrypt')) {
            $sql .= " OR api_key = '" . $this->db->escape(dolEncrypt($apiKey, '', '', 'dolibarr')) . "'";
        }
        $sql .= ")";
        $sql .= " AND statut = 1"; // Solo usuarios activos
        $sql .= " AND entity IN (0, " . ((int) $conf->entity) . ")";

        $result = $this->db->query($sql);

        if ($result && $this->db->num_rows($result) > 0) {
            $obj = $this->db->fetch_object($result);

            // Verificación final: la clave almacenada (descifrada si procede) debe
            // coincidir con la enviada.
            $storedKey = function_exists('dolDecrypt') ? dolDecrypt($obj->api_key) : $obj->api_key;
            if ($storedKey !== $apiKey && $obj->api_key !== $apiKey) {
                return null;
            }

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
