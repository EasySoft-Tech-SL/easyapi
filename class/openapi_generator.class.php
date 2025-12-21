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
 * \file    easyapi/class/openapi_generator.class.php
 * \ingroup easyapi
 * \brief   EasyAPI - Generador de especificación OpenAPI 3.0
 */

/**
 * Generador de especificación OpenAPI 3.0 para EasyAPI
 */
class OpenApiGenerator
{
    /** @var array Rutas registradas */
    private $routes = array();

    /** @var array Schemas registrados */
    private $schemas = array();

    /** @var array Tags registrados */
    private $tags = array();

    /** @var string Título de la API */
    private $title = 'EasyAPI - Dolibarr Headless API';

    /** @var string Versión de la API */
    private $version = '1.0.0';

    /** @var string Descripción de la API */
    private $description = 'Dynamic REST API for Dolibarr ERP/CRM. Extensible via hooks.';

    /** @var string URL base de la API */
    private $baseUrl = '';

    /**
     * Constructor
     *
     * @param string $baseUrl URL base de la API
     */
    public function __construct(string $baseUrl = '')
    {
        $this->baseUrl = $baseUrl;
        $this->registerDefaultSchemas();
    }

    /**
     * Registra los schemas por defecto
     */
    private function registerDefaultSchemas(): void
    {
        // Schema de respuesta exitosa
        $this->addSchema('SuccessResponse', array(
            'type' => 'object',
            'properties' => array(
                'success' => array('type' => 'boolean', 'example' => true),
                'data' => array('type' => 'object')
            )
        ));

        // Schema de error
        $this->addSchema('ErrorResponse', array(
            'type' => 'object',
            'properties' => array(
                'success' => array('type' => 'boolean', 'example' => false),
                'error' => array(
                    'type' => 'object',
                    'properties' => array(
                        'code' => array('type' => 'integer', 'example' => 400),
                        'message' => array('type' => 'string', 'example' => 'Error message')
                    )
                )
            )
        ));

        // Schema de usuario
        $this->addSchema('User', array(
            'type' => 'object',
            'properties' => array(
                'id' => array('type' => 'integer', 'example' => 1),
                'login' => array('type' => 'string', 'example' => 'admin'),
                'firstname' => array('type' => 'string', 'example' => 'John'),
                'lastname' => array('type' => 'string', 'example' => 'Doe'),
                'email' => array('type' => 'string', 'format' => 'email', 'example' => 'admin@example.com'),
                'admin' => array('type' => 'boolean', 'example' => true)
            )
        ));

        // Schema de login request
        $this->addSchema('LoginRequest', array(
            'type' => 'object',
            'required' => array('login', 'password'),
            'properties' => array(
                'login' => array('type' => 'string', 'example' => 'admin'),
                'password' => array('type' => 'string', 'format' => 'password', 'example' => 'password123')
            )
        ));

        // Schema de login response
        $this->addSchema('LoginResponse', array(
            'type' => 'object',
            'properties' => array(
                'success' => array('type' => 'boolean', 'example' => true),
                'data' => array(
                    'type' => 'object',
                    'properties' => array(
                        'api_key' => array('type' => 'string', 'example' => 'abc123xyz789'),
                        'user' => array('$ref' => '#/components/schemas/User')
                    )
                )
            )
        ));

        // Schema paginación
        $this->addSchema('PaginationMeta', array(
            'type' => 'object',
            'properties' => array(
                'total' => array('type' => 'integer', 'example' => 100),
                'page' => array('type' => 'integer', 'example' => 1),
                'per_page' => array('type' => 'integer', 'example' => 20),
                'last_page' => array('type' => 'integer', 'example' => 5),
                'has_more' => array('type' => 'boolean', 'example' => true)
            )
        ));

        // Tags por defecto
        $this->addTag('Core', 'Endpoints básicos de la API');
        $this->addTag('Auth', 'Autenticación y gestión de sesión');
    }

    /**
     * Añade una ruta a la documentación OpenAPI
     *
     * @param array $routeConfig Configuración de la ruta
     */
    public function addRoute(array $routeConfig): void
    {
        $this->routes[] = $routeConfig;
    }

    /**
     * Añade un schema
     *
     * @param string $name Nombre del schema
     * @param array $definition Definición del schema
     */
    public function addSchema(string $name, array $definition): void
    {
        $this->schemas[$name] = $definition;
    }

    /**
     * Añade un tag
     *
     * @param string $name Nombre del tag
     * @param string $description Descripción del tag
     */
    public function addTag(string $name, string $description = ''): void
    {
        $this->tags[$name] = array(
            'name' => $name,
            'description' => $description
        );
    }

    /**
     * Configura la información de la API
     *
     * @param string $title Título
     * @param string $version Versión
     * @param string $description Descripción
     */
    public function setInfo(string $title, string $version, string $description): void
    {
        $this->title = $title;
        $this->version = $version;
        $this->description = $description;
    }

    /**
     * Genera la especificación OpenAPI 3.0
     *
     * @param bool $onlyPublic Si es true, solo incluye rutas públicas
     * @param \User|null $user Usuario autenticado (para filtrar por permisos)
     * @return array
     */
    public function generate(bool $onlyPublic = false, $user = null): array
    {
        $spec = array(
            'openapi' => '3.0.3',
            'info' => array(
                'title' => $this->title,
                'version' => $this->version,
                'description' => $this->description . ($onlyPublic ? ' (Public endpoints only - authenticate to see all)' : ''),
                'contact' => array(
                    'name' => 'EasyAPI Support',
                    'email' => 'support@example.com'
                ),
                'license' => array(
                    'name' => 'GPL-3.0',
                    'url' => 'https://www.gnu.org/licenses/gpl-3.0.html'
                )
            ),
            'servers' => array(
                array(
                    'url' => $this->baseUrl,
                    'description' => 'API Server'
                )
            ),
            'tags' => $this->filterTags($onlyPublic, $user),
            'paths' => $this->buildPaths($onlyPublic, $user),
            'components' => array(
                'securitySchemes' => array(
                    'ApiKeyHeader' => array(
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'DOLAPIKEY',
                        'description' => 'API key del usuario de Dolibarr'
                    ),
                    'ApiKeyQuery' => array(
                        'type' => 'apiKey',
                        'in' => 'query',
                        'name' => 'DOLAPIKEY',
                        'description' => 'API key como parámetro de consulta'
                    ),
                    'BearerAuth' => array(
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'description' => 'Bearer token con el API key'
                    )
                ),
                'schemas' => $this->schemas
            )
        );

        return $spec;
    }

    /**
     * Filtra los tags según las rutas visibles
     *
     * @param bool $onlyPublic
     * @param \User|null $user
     * @return array
     */
    private function filterTags(bool $onlyPublic, $user): array
    {
        $visibleTags = array();

        foreach ($this->routes as $route) {
            // Verificar si la ruta es visible
            if ($onlyPublic && empty($route['public'])) {
                continue;
            }

            // Verificar permisos si hay usuario
            if ($user && !$this->userHasRoutePermission($user, $route)) {
                continue;
            }

            // Añadir los tags de esta ruta
            $routeTags = isset($route['tags']) ? $route['tags'] : array('Core');
            foreach ($routeTags as $tag) {
                $visibleTags[$tag] = true;
            }
        }

        // Filtrar solo tags visibles
        $result = array();
        foreach ($this->tags as $tagName => $tagInfo) {
            if (isset($visibleTags[$tagName])) {
                $result[] = $tagInfo;
            }
        }

        return $result;
    }

    /**
     * Verifica si el usuario tiene permiso para una ruta
     *
     * @param \User $user
     * @param array $route
     * @return bool
     */
    private function userHasRoutePermission($user, array $route): bool
    {
        // Si no hay permisos definidos, la ruta es accesible
        if (empty($route['permissions'])) {
            return true;
        }

        // Admin tiene todos los permisos
        if ($user->admin) {
            return true;
        }

        // Verificar permisos (formato: 'modulo->permiso' o array de permisos)
        $permissions = $route['permissions'];
        if (!is_array($permissions)) {
            $permissions = array($permissions);
        }

        foreach ($permissions as $perm) {
            if (strpos($perm, '->') !== false) {
                list($module, $right) = explode('->', $perm, 2);
                if (!empty($user->rights->$module->$right)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Construye la sección paths
     *
     * @param bool $onlyPublic Si es true, solo incluye rutas públicas
     * @param \User|null $user Usuario para filtrar por permisos
     * @return array
     */
    private function buildPaths(bool $onlyPublic = false, $user = null): array
    {
        $paths = array();

        foreach ($this->routes as $route) {
            // Filtrar rutas privadas si onlyPublic
            if ($onlyPublic && empty($route['public'])) {
                continue;
            }

            // Filtrar por permisos si hay usuario
            if ($user && !$this->userHasRoutePermission($user, $route)) {
                continue;
            }

            $path = $route['path'];
            $method = strtolower($route['method']);

            if (!isset($paths[$path])) {
                $paths[$path] = array();
            }

            // Construir summary con indicador de origen
            $summary = isset($route['summary']) ? $route['summary'] : $route['description'];
            if (!empty($route['source'])) {
                // Añadir indicador con texto al final del summary
                if (strpos($route['source'], 'EAPI Resource') !== false) {
                    $summary .= ' 📦 Resource';
                } elseif (strpos($route['source'], 'Hook') !== false) {
                    $summary .= ' 🪝 Hook';
                }
            }

            $operation = array(
                'summary' => $summary,
                'description' => isset($route['description']) ? $route['description'] : '',
                'operationId' => isset($route['operationId']) ? $route['operationId'] : $this->generateOperationId($method, $path),
                'tags' => isset($route['tags']) ? $route['tags'] : array('Core'),
                'responses' => $this->buildResponses($route)
            );

            // Añadir source en la descripción
            if (!empty($route['source'])) {
                $operation['description'] .= "\n\n📦 **Source:** `" . $route['source'] . "`";
            }

            // Añadir info de permisos en la descripción
            if (!empty($route['permissions'])) {
                $perms = is_array($route['permissions']) ? implode(', ', $route['permissions']) : $route['permissions'];
                $operation['description'] .= "\n\n**Permisos requeridos:** `" . $perms . "`";
            }

            // Seguridad
            if (empty($route['public'])) {
                $operation['security'] = array(
                    array('ApiKeyHeader' => array()),
                    array('ApiKeyQuery' => array()),
                    array('BearerAuth' => array())
                );
            }

            // Parámetros
            if (!empty($route['parameters'])) {
                $operation['parameters'] = $route['parameters'];
            }

            // Request body
            if (!empty($route['requestBody'])) {
                $operation['requestBody'] = $route['requestBody'];
            }

            // Deprecated
            if (!empty($route['deprecated'])) {
                $operation['deprecated'] = true;
            }

            $paths[$path][$method] = $operation;
        }

        return $paths;
    }

    /**
     * Construye las respuestas de una operación
     *
     * @param array $route Configuración de la ruta
     * @return array
     */
    private function buildResponses(array $route): array
    {
        // Respuestas por defecto
        $responses = array(
            '200' => array(
                'description' => 'Successful operation',
                'content' => array(
                    'application/json' => array(
                        'schema' => array('$ref' => '#/components/schemas/SuccessResponse')
                    )
                )
            )
        );

        // Respuestas personalizadas
        if (!empty($route['responses'])) {
            $responses = $route['responses'];
        }

        // Añadir errores comunes si no es público
        if (empty($route['public'])) {
            $responses['401'] = array(
                'description' => 'Unauthorized - Invalid or missing API key',
                'content' => array(
                    'application/json' => array(
                        'schema' => array('$ref' => '#/components/schemas/ErrorResponse')
                    )
                )
            );
        }

        $responses['500'] = array(
            'description' => 'Internal server error',
            'content' => array(
                'application/json' => array(
                    'schema' => array('$ref' => '#/components/schemas/ErrorResponse')
                )
            )
        );

        return $responses;
    }

    /**
     * Genera un operationId único
     *
     * @param string $method Método HTTP
     * @param string $path Path de la ruta
     * @return string
     */
    private function generateOperationId(string $method, ?string $path): string
    {
        // Manejar path null o vacío
        if (empty($path)) {
            return $method . 'Unknown' . uniqid();
        }

        // Convertir /users/{id} a getUsersById
        $parts = explode('/', trim($path, '/'));
        $name = $method;

        foreach ($parts as $part) {
            if (strpos($part, '{') === 0) {
                $name .= 'By' . ucfirst(trim($part, '{}'));
            } else {
                $name .= ucfirst($part);
            }
        }

        return $name;
    }

    /**
     * Genera JSON de la especificación
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->generate(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Genera YAML de la especificación (básico sin dependencias)
     *
     * @return string
     */
    public function toYaml(): string
    {
        // Conversión básica a YAML sin dependencias externas
        return $this->arrayToYaml($this->generate());
    }

    /**
     * Convierte array a YAML básico
     *
     * @param array $data Datos
     * @param int $indent Nivel de indentación
     * @return string
     */
    private function arrayToYaml(array $data, int $indent = 0): string
    {
        $yaml = '';
        $prefix = str_repeat('  ', $indent);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (empty($value)) {
                    $yaml .= $prefix . $key . ": []\n";
                } elseif (array_keys($value) === range(0, count($value) - 1)) {
                    // Array indexado
                    $yaml .= $prefix . $key . ":\n";
                    foreach ($value as $item) {
                        if (is_array($item)) {
                            $yaml .= $prefix . "  -\n" . $this->arrayToYaml($item, $indent + 2);
                        } else {
                            $yaml .= $prefix . "  - " . $this->yamlValue($item) . "\n";
                        }
                    }
                } else {
                    // Array asociativo
                    $yaml .= $prefix . $key . ":\n" . $this->arrayToYaml($value, $indent + 1);
                }
            } else {
                $yaml .= $prefix . $key . ': ' . $this->yamlValue($value) . "\n";
            }
        }

        return $yaml;
    }

    /**
     * Formatea un valor para YAML
     *
     * @param mixed $value Valor
     * @return string
     */
    private function yamlValue($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_null($value)) {
            return 'null';
        }
        if (is_numeric($value)) {
            return (string) $value;
        }
        if (preg_match('/^[a-zA-Z0-9_\-\.\/]+$/', $value)) {
            return $value;
        }
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
