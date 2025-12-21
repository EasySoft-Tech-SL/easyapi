# EasyAPI - Guía Completa de Implementación

## Índice

1. [Introducción](#introducción)
2. [Arquitectura del Sistema](#arquitectura-del-sistema)
3. [Cómo Extender desde Módulos de Terceros](#cómo-extender-desde-módulos-de-terceros)
4. [Ejemplos de Endpoints](#ejemplos-de-endpoints)
5. [Ejemplos de Middleware](#ejemplos-de-middleware)
6. [Ejemplos de Groups](#ejemplos-de-groups)
7. [Documentación OpenAPI](#documentación-openapi)
8. [Autenticación y Permisos](#autenticación-y-permisos)
9. [Helpers y Utilidades](#helpers-y-utilidades)
10. [Buenas Prácticas](#buenas-prácticas)
11. [Referencia Completa](#referencia-completa)

---

## Introducción

EasyAPI es un módulo de Dolibarr que proporciona una API REST dinámica y extensible usando **Slim Framework 4**. Permite que cualquier módulo de terceros añada sus propios endpoints, middleware y documentación OpenAPI mediante el sistema de hooks de Dolibarr.

### Características principales

- **Slim Framework 4.x**: Router moderno y eficiente
- **PSR-7/PSR-15**: Estándares de HTTP messages y middleware
- **OpenAPI 3.0**: Documentación automática con Swagger UI
- **DOLAPIKEY**: Compatible con la autenticación nativa de Dolibarr
- **Hooks**: Sistema extensible mediante hooks de Dolibarr
- **PHP 7.4+**: Compatible con versiones modernas de PHP

---

## Arquitectura del Sistema

```
┌─────────────────────────────────────────────────────────────┐
│                      Petición HTTP                          │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    api/index.php                            │
│                   (Entry Point)                             │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                  ApiEasyApi Class                           │
│  ┌────────────────────────────────────────────────────┐    │
│  │ 1. CorsMiddleware      (CORS Headers)              │    │
│  │ 2. RateLimitMiddleware (Rate Limiting)             │    │
│  │ 3. RequestLogger       (Auditoría)                 │    │
│  │ 4. DolibarrAuth        (Autenticación DOLAPIKEY)   │    │
│  └────────────────────────────────────────────────────┘    │
│                                                             │
│  ┌────────────────────────────────────────────────────┐    │
│  │              RUTAS CORE                             │    │
│  │  /status, /health, /login, /docs, /me, /openapi    │    │
│  └────────────────────────────────────────────────────┘    │
│                                                             │
│  ┌────────────────────────────────────────────────────┐    │
│  │            HOOK: easyapiRegisterRoutes              │    │
│  │   → Módulos externos añaden sus rutas aquí         │    │
│  └────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                    Respuesta JSON                           │
└─────────────────────────────────────────────────────────────┘
```

### Flujo de Middleware

```
Request → CORS → RateLimit → Logger → Auth → [Tu Middleware] → Route Handler
                                                                     │
Response ← CORS ← RateLimit ← Logger ← Auth ← [Tu Middleware] ←──────┘
```

---

## Cómo Extender desde Módulos de Terceros

### Paso 1: Configurar el módulo

En tu archivo `core/modules/modTuModulo.class.php`, añade el hook context `easyapi`:

```php
<?php
// core/modules/modMiModulo.class.php

class modMiModulo extends DolibarrModules
{
    public function __construct($db)
    {
        // ... otras configuraciones ...

        $this->module_parts = array(
            // Registrar el hook 'easyapi' para poder añadir endpoints
            'hooks' => array(
                'data' => array(
                    'easyapi',  // ← Esto es lo importante
                ),
                'entity' => '0',
            ),
        );
    }
}
```

### Paso 2: Crear la clase de acciones

Crea el archivo `class/actions_mimodulo.class.php`:

```php
<?php
// class/actions_mimodulo.class.php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ActionsMimodulo
{
    /** @var DoliDB */
    public $db;

    /** @var string */
    public $error = '';

    /** @var array */
    public $errors = array();

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Hook para registrar rutas en EasyAPI
     *
     * @param array $parameters Contiene 'app', 'db', 'api', 'config'
     * @param object $object
     * @param string $action
     * @param HookManager $hookmanager
     * @return int
     */
    public function easyapiRegisterRoutes($parameters, &$object, &$action, $hookmanager)
    {
        /** @var \Slim\App $app */
        $app = $parameters['app'];

        /** @var DoliDB $db */
        $db = $parameters['db'];

        /** @var ApiEasyApi $api */
        $api = $parameters['api'];

        // ¡Aquí registras tus rutas!
        // Ver ejemplos a continuación...

        return 0;
    }
}
```

---

## Ejemplos de Endpoints

### 1. Endpoint GET Simple

```php
public function easyapiRegisterRoutes($parameters, &$object, &$action, $hookmanager)
{
    $app = $parameters['app'];
    $api = $parameters['api'];

    // Endpoint simple que devuelve datos
    $app->get('/mimodulo/info', function (Request $request, Response $response) use ($api) {
        return $api->successResponse($response, array(
            'module' => 'MiModulo',
            'version' => '1.0.0',
            'timestamp' => time()
        ));
    });

    // Documentar en OpenAPI
    $api->addRoute(array(
        'method' => 'GET',
        'path' => '/mimodulo/info',
        'summary' => 'Información del módulo',
        'description' => 'Devuelve información básica del módulo',
        'tags' => array('MiModulo'),
        'public' => false  // Requiere autenticación
    ));

    return 0;
}
```

### 2. Endpoint GET con Parámetro en URL

```php
$app->get('/mimodulo/item/{id}', function (Request $request, Response $response, array $args) use ($db, $api) {
    $id = (int) $args['id'];

    // Buscar en base de datos
    $sql = "SELECT * FROM " . MAIN_DB_PREFIX . "mi_tabla WHERE rowid = " . $id;
    $result = $db->query($sql);

    if ($result && $db->num_rows($result) > 0) {
        $obj = $db->fetch_object($result);
        return $api->successResponse($response, array(
            'id' => (int) $obj->rowid,
            'name' => $obj->name,
            'created' => $obj->datec
        ));
    }

    return $api->errorResponse($response, 'Item not found', 404);
});

// Documentar en OpenAPI
$api->addRoute(array(
    'method' => 'GET',
    'path' => '/mimodulo/item/{id}',
    'summary' => 'Obtener un item',
    'description' => 'Obtiene un item por su ID',
    'tags' => array('MiModulo'),
    'parameters' => array(
        array(
            'name' => 'id',
            'in' => 'path',
            'required' => true,
            'description' => 'ID del item',
            'schema' => array('type' => 'integer')
        )
    ),
    'responses' => array(
        '200' => array('description' => 'Item encontrado'),
        '404' => array('description' => 'Item no encontrado')
    )
));
```

### 3. Endpoint GET con Query Parameters

```php
$app->get('/mimodulo/search', function (Request $request, Response $response) use ($db, $api) {
    // Obtener parámetros de la URL (?q=texto&limit=10&page=1)
    $params = $request->getQueryParams();
    $query = isset($params['q']) ? $params['q'] : '';
    $limit = isset($params['limit']) ? (int) $params['limit'] : 20;
    $page = isset($params['page']) ? (int) $params['page'] : 1;
    $offset = ($page - 1) * $limit;

    // Buscar en base de datos
    $sql = "SELECT * FROM " . MAIN_DB_PREFIX . "mi_tabla";
    $sql .= " WHERE name LIKE '%" . $db->escape($query) . "%'";
    $sql .= " LIMIT " . $limit . " OFFSET " . $offset;

    $items = array();
    $result = $db->query($sql);
    while ($obj = $db->fetch_object($result)) {
        $items[] = array(
            'id' => (int) $obj->rowid,
            'name' => $obj->name
        );
    }

    // Contar total para paginación
    $sqlCount = "SELECT COUNT(*) as total FROM " . MAIN_DB_PREFIX . "mi_tabla";
    $sqlCount .= " WHERE name LIKE '%" . $db->escape($query) . "%'";
    $resCount = $db->query($sqlCount);
    $total = $db->fetch_object($resCount)->total;

    // Usar respuesta paginada
    return $api->paginatedResponse($response, $items, (int) $total, $page, $limit);
});

$api->addRoute(array(
    'method' => 'GET',
    'path' => '/mimodulo/search',
    'summary' => 'Buscar items',
    'tags' => array('MiModulo'),
    'parameters' => array(
        array('name' => 'q', 'in' => 'query', 'schema' => array('type' => 'string')),
        array('name' => 'limit', 'in' => 'query', 'schema' => array('type' => 'integer', 'default' => 20)),
        array('name' => 'page', 'in' => 'query', 'schema' => array('type' => 'integer', 'default' => 1))
    )
));
```

### 4. Endpoint POST (Crear)

```php
$app->post('/mimodulo/item', function (Request $request, Response $response) use ($db, $api) {
    // Obtener body JSON
    $body = json_decode((string) $request->getBody(), true);

    // Validar campos requeridos
    if (empty($body['name'])) {
        return $api->errorResponse($response, 'El campo name es requerido', 400);
    }

    // Obtener usuario autenticado
    $user = $request->getAttribute('dolibarr_user');

    // Insertar en base de datos
    $sql = "INSERT INTO " . MAIN_DB_PREFIX . "mi_tabla (name, fk_user_creat, datec)";
    $sql .= " VALUES ('" . $db->escape($body['name']) . "', " . $user->id . ", NOW())";

    if ($db->query($sql)) {
        $newId = $db->last_insert_id(MAIN_DB_PREFIX . 'mi_tabla');

        return $api->successResponse($response, array(
            'id' => $newId,
            'name' => $body['name'],
            'message' => 'Item creado correctamente'
        ), 201);  // 201 = Created
    }

    return $api->errorResponse($response, 'Error al crear el item', 500);
});

$api->addRoute(array(
    'method' => 'POST',
    'path' => '/mimodulo/item',
    'summary' => 'Crear item',
    'tags' => array('MiModulo'),
    'requestBody' => array(
        'required' => true,
        'content' => array(
            'application/json' => array(
                'schema' => array(
                    'type' => 'object',
                    'required' => array('name'),
                    'properties' => array(
                        'name' => array('type' => 'string', 'example' => 'Mi nuevo item'),
                        'description' => array('type' => 'string', 'example' => 'Descripción opcional')
                    )
                )
            )
        )
    ),
    'responses' => array(
        '201' => array('description' => 'Item creado'),
        '400' => array('description' => 'Datos inválidos')
    )
));
```

### 5. Endpoint PUT (Actualizar)

```php
$app->put('/mimodulo/item/{id}', function (Request $request, Response $response, array $args) use ($db, $api) {
    $id = (int) $args['id'];
    $body = json_decode((string) $request->getBody(), true);
    $user = $request->getAttribute('dolibarr_user');

    // Verificar que existe
    $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "mi_tabla WHERE rowid = " . $id;
    $result = $db->query($sql);
    if (!$result || $db->num_rows($result) == 0) {
        return $api->errorResponse($response, 'Item no encontrado', 404);
    }

    // Actualizar
    $updates = array();
    if (isset($body['name'])) {
        $updates[] = "name = '" . $db->escape($body['name']) . "'";
    }
    if (isset($body['description'])) {
        $updates[] = "description = '" . $db->escape($body['description']) . "'";
    }

    if (empty($updates)) {
        return $api->errorResponse($response, 'No hay campos para actualizar', 400);
    }

    $sql = "UPDATE " . MAIN_DB_PREFIX . "mi_tabla SET ";
    $sql .= implode(', ', $updates);
    $sql .= ", fk_user_modif = " . $user->id;
    $sql .= ", tms = NOW()";
    $sql .= " WHERE rowid = " . $id;

    if ($db->query($sql)) {
        return $api->successResponse($response, array(
            'id' => $id,
            'message' => 'Item actualizado correctamente'
        ));
    }

    return $api->errorResponse($response, 'Error al actualizar', 500);
});

$api->addRoute(array(
    'method' => 'PUT',
    'path' => '/mimodulo/item/{id}',
    'summary' => 'Actualizar item',
    'tags' => array('MiModulo'),
    'parameters' => array(
        array('name' => 'id', 'in' => 'path', 'required' => true, 'schema' => array('type' => 'integer'))
    ),
    'requestBody' => array(
        'required' => true,
        'content' => array(
            'application/json' => array(
                'schema' => array(
                    'type' => 'object',
                    'properties' => array(
                        'name' => array('type' => 'string'),
                        'description' => array('type' => 'string')
                    )
                )
            )
        )
    )
));
```

### 6. Endpoint DELETE

```php
$app->delete('/mimodulo/item/{id}', function (Request $request, Response $response, array $args) use ($db, $api) {
    $id = (int) $args['id'];
    $user = $request->getAttribute('dolibarr_user');

    // Verificar permisos (solo admin puede eliminar)
    if (!$user->admin) {
        return $api->errorResponse($response, 'No tienes permisos para eliminar', 403);
    }

    // Verificar que existe
    $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "mi_tabla WHERE rowid = " . $id;
    $result = $db->query($sql);
    if (!$result || $db->num_rows($result) == 0) {
        return $api->errorResponse($response, 'Item no encontrado', 404);
    }

    // Eliminar
    $sql = "DELETE FROM " . MAIN_DB_PREFIX . "mi_tabla WHERE rowid = " . $id;

    if ($db->query($sql)) {
        return $api->successResponse($response, array(
            'deleted' => true,
            'id' => $id
        ));
    }

    return $api->errorResponse($response, 'Error al eliminar', 500);
});

$api->addRoute(array(
    'method' => 'DELETE',
    'path' => '/mimodulo/item/{id}',
    'summary' => 'Eliminar item',
    'tags' => array('MiModulo'),
    'parameters' => array(
        array('name' => 'id', 'in' => 'path', 'required' => true, 'schema' => array('type' => 'integer'))
    ),
    'responses' => array(
        '200' => array('description' => 'Item eliminado'),
        '403' => array('description' => 'Sin permisos'),
        '404' => array('description' => 'No encontrado')
    )
));
```

### 7. Endpoint usando Clases de Dolibarr

```php
$app->get('/mimodulo/factura/{id}', function (Request $request, Response $response, array $args) use ($db, $api) {
    global $conf;

    $id = (int) $args['id'];
    $user = $request->getAttribute('dolibarr_user');

    // Verificar permiso de lectura de facturas
    if (empty($user->rights->facture->lire)) {
        return $api->errorResponse($response, 'No tienes permiso para ver facturas', 403);
    }

    // Usar la clase Facture de Dolibarr
    require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';

    $facture = new Facture($db);
    $result = $facture->fetch($id);

    if ($result <= 0) {
        return $api->errorResponse($response, 'Factura no encontrada', 404);
    }

    // Devolver datos de la factura
    return $api->successResponse($response, array(
        'id' => (int) $facture->id,
        'ref' => $facture->ref,
        'ref_client' => $facture->ref_client,
        'total_ht' => (float) $facture->total_ht,
        'total_tva' => (float) $facture->total_tva,
        'total_ttc' => (float) $facture->total_ttc,
        'date' => dol_print_date($facture->date, 'dayrfc'),
        'status' => (int) $facture->statut,
        'status_label' => $facture->getLibStatut(0),
        'thirdparty' => array(
            'id' => (int) $facture->socid,
            'name' => $facture->thirdparty->name
        )
    ));
});

$api->addRoute(array(
    'method' => 'GET',
    'path' => '/mimodulo/factura/{id}',
    'summary' => 'Obtener factura',
    'description' => 'Obtiene los datos de una factura usando la clase nativa de Dolibarr',
    'tags' => array('MiModulo', 'Facturas'),
    'parameters' => array(
        array('name' => 'id', 'in' => 'path', 'required' => true, 'schema' => array('type' => 'integer'))
    )
));
```

### 8. Endpoint Público (Sin autenticación)

Para crear un endpoint público, añádelo a las rutas públicas en la configuración o documéntalo como público:

```php
// El endpoint público se define igual
$app->get('/mimodulo/public/status', function (Request $request, Response $response) use ($api) {
    return $api->successResponse($response, array(
        'available' => true,
        'message' => 'Servicio disponible'
    ));
});

// Documentar como público
$api->addRoute(array(
    'method' => 'GET',
    'path' => '/mimodulo/public/status',
    'summary' => 'Estado público',
    'tags' => array('MiModulo'),
    'public' => true,  // ← Marcar como público
    'security' => array()  // ← Sin seguridad en OpenAPI
));
```

> **Nota**: Debes añadir `/mimodulo/public/*` a la configuración de rutas públicas en el admin de EasyAPI.

---

## Ejemplos de Middleware

### 1. Middleware de Logging Personalizado

```php
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class MiModuloLogMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        $startTime = microtime(true);

        // Log antes de procesar
        dol_syslog("MiModulo API: " . $request->getMethod() . " " . $request->getUri()->getPath(), LOG_DEBUG);

        // Procesar la petición
        $response = $handler->handle($request);

        // Log después de procesar
        $duration = round((microtime(true) - $startTime) * 1000, 2);
        dol_syslog("MiModulo API: Completado en {$duration}ms - Status: " . $response->getStatusCode(), LOG_DEBUG);

        // Añadir header personalizado
        return $response->withHeader('X-MiModulo-Time', (string) $duration . 'ms');
    }
}

// En tu hook, añadir el middleware:
public function easyapiRegisterRoutes($parameters, &$object, &$action, $hookmanager)
{
    $app = $parameters['app'];

    // Añadir middleware global (se aplica a TODAS las rutas)
    $app->add(new MiModuloLogMiddleware());

    return 0;
}
```

### 2. Middleware de Validación de Header

```php
class RequireCustomHeaderMiddleware implements MiddlewareInterface
{
    private $headerName;
    private $expectedValue;

    public function __construct(string $headerName, string $expectedValue = null)
    {
        $this->headerName = $headerName;
        $this->expectedValue = $expectedValue;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $headerValue = $request->getHeaderLine($this->headerName);

        if (empty($headerValue)) {
            $response = new \Slim\Psr7\Response();
            $response = $response->withStatus(400);
            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode(array(
                'success' => false,
                'error' => array(
                    'code' => 400,
                    'message' => "Header {$this->headerName} es requerido"
                )
            )));
            return $response;
        }

        if ($this->expectedValue !== null && $headerValue !== $this->expectedValue) {
            $response = new \Slim\Psr7\Response();
            $response = $response->withStatus(403);
            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode(array(
                'success' => false,
                'error' => array(
                    'code' => 403,
                    'message' => "Valor inválido para header {$this->headerName}"
                )
            )));
            return $response;
        }

        return $handler->handle($request);
    }
}

// Usar en una ruta específica:
$app->get('/mimodulo/secure', function($req, $res) use ($api) {
    return $api->successResponse($res, array('secure' => true));
})->add(new RequireCustomHeaderMiddleware('X-Secret-Token', 'mi-token-secreto'));
```

### 3. Middleware de Verificación de Permisos

```php
class RequirePermissionMiddleware implements MiddlewareInterface
{
    private $module;
    private $permission;

    public function __construct(string $module, string $permission)
    {
        $this->module = $module;
        $this->permission = $permission;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $user = $request->getAttribute('dolibarr_user');

        if (!$user) {
            $response = new \Slim\Psr7\Response();
            $response = $response->withStatus(401);
            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode(array(
                'success' => false,
                'error' => array('code' => 401, 'message' => 'No autenticado')
            )));
            return $response;
        }

        // Admin tiene todos los permisos
        if (!$user->admin) {
            // Verificar permiso específico
            $hasPermission = !empty($user->rights->{$this->module}->{$this->permission});

            if (!$hasPermission) {
                $response = new \Slim\Psr7\Response();
                $response = $response->withStatus(403);
                $response = $response->withHeader('Content-Type', 'application/json');
                $response->getBody()->write(json_encode(array(
                    'success' => false,
                    'error' => array(
                        'code' => 403,
                        'message' => "Permiso requerido: {$this->module}->{$this->permission}"
                    )
                )));
                return $response;
            }
        }

        return $handler->handle($request);
    }
}

// Ejemplo de uso:
$app->delete('/mimodulo/item/{id}', $deleteHandler)
    ->add(new RequirePermissionMiddleware('mimodulo', 'delete'));
```

### 4. Middleware de Caché

```php
class CacheMiddleware implements MiddlewareInterface
{
    private $ttlSeconds;
    private $cacheDir;

    public function __construct(int $ttlSeconds = 300)
    {
        $this->ttlSeconds = $ttlSeconds;
        $this->cacheDir = sys_get_temp_dir() . '/easyapi_cache/';

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        // Solo cachear GET requests
        if ($request->getMethod() !== 'GET') {
            return $handler->handle($request);
        }

        $cacheKey = md5($request->getUri()->getPath() . '?' . $request->getUri()->getQuery());
        $cacheFile = $this->cacheDir . $cacheKey;

        // Verificar caché existente
        if (file_exists($cacheFile)) {
            $cached = json_decode(file_get_contents($cacheFile), true);

            if ($cached && $cached['expires'] > time()) {
                $response = new \Slim\Psr7\Response();
                $response = $response->withHeader('Content-Type', 'application/json');
                $response = $response->withHeader('X-Cache', 'HIT');
                $response->getBody()->write($cached['body']);
                return $response;
            }
        }

        // Ejecutar y cachear
        $response = $handler->handle($request);

        // Solo cachear respuestas exitosas
        if ($response->getStatusCode() === 200) {
            $body = (string) $response->getBody();
            $cacheData = array(
                'expires' => time() + $this->ttlSeconds,
                'body' => $body
            );
            file_put_contents($cacheFile, json_encode($cacheData));

            // Rebobinar el body
            $response->getBody()->rewind();
        }

        return $response->withHeader('X-Cache', 'MISS');
    }
}

// Usar en rutas específicas:
$app->get('/mimodulo/expensive-query', $handler)->add(new CacheMiddleware(600)); // 10 min
```

---

## Ejemplos de Groups

Los groups permiten agrupar rutas bajo un prefijo común y aplicar middleware a todas ellas.

### 1. Group Básico

```php
public function easyapiRegisterRoutes($parameters, &$object, &$action, $hookmanager)
{
    $app = $parameters['app'];
    $db = $parameters['db'];
    $api = $parameters['api'];

    // Grupo de rutas bajo /mimodulo
    $app->group('/mimodulo', function ($group) use ($db, $api) {

        // GET /mimodulo/items
        $group->get('/items', function (Request $request, Response $response) use ($db, $api) {
            // Listar items
            $items = array();
            $sql = "SELECT * FROM " . MAIN_DB_PREFIX . "mi_tabla LIMIT 100";
            $result = $db->query($sql);
            while ($obj = $db->fetch_object($result)) {
                $items[] = array('id' => (int) $obj->rowid, 'name' => $obj->name);
            }
            return $api->successResponse($response, $items);
        });

        // GET /mimodulo/items/{id}
        $group->get('/items/{id}', function (Request $request, Response $response, array $args) use ($db, $api) {
            $id = (int) $args['id'];
            // ... obtener item
            return $api->successResponse($response, array('id' => $id));
        });

        // POST /mimodulo/items
        $group->post('/items', function (Request $request, Response $response) use ($db, $api) {
            $body = json_decode((string) $request->getBody(), true);
            // ... crear item
            return $api->successResponse($response, array('created' => true), 201);
        });

        // PUT /mimodulo/items/{id}
        $group->put('/items/{id}', function (Request $request, Response $response, array $args) use ($db, $api) {
            $id = (int) $args['id'];
            $body = json_decode((string) $request->getBody(), true);
            // ... actualizar item
            return $api->successResponse($response, array('updated' => true));
        });

        // DELETE /mimodulo/items/{id}
        $group->delete('/items/{id}', function (Request $request, Response $response, array $args) use ($db, $api) {
            $id = (int) $args['id'];
            // ... eliminar item
            return $api->successResponse($response, array('deleted' => true));
        });

    });

    // Documentar todas las rutas
    $api->addRoute(array('method' => 'GET', 'path' => '/mimodulo/items', 'summary' => 'Listar items', 'tags' => array('MiModulo')));
    $api->addRoute(array('method' => 'GET', 'path' => '/mimodulo/items/{id}', 'summary' => 'Obtener item', 'tags' => array('MiModulo')));
    $api->addRoute(array('method' => 'POST', 'path' => '/mimodulo/items', 'summary' => 'Crear item', 'tags' => array('MiModulo')));
    $api->addRoute(array('method' => 'PUT', 'path' => '/mimodulo/items/{id}', 'summary' => 'Actualizar item', 'tags' => array('MiModulo')));
    $api->addRoute(array('method' => 'DELETE', 'path' => '/mimodulo/items/{id}', 'summary' => 'Eliminar item', 'tags' => array('MiModulo')));

    return 0;
}
```

### 2. Group con Middleware

```php
// Grupo con middleware de permisos
$app->group('/mimodulo/admin', function ($group) use ($db, $api) {

    $group->get('/stats', function (Request $request, Response $response) use ($db, $api) {
        // Estadísticas solo para admin
        return $api->successResponse($response, array(
            'total_items' => 1234,
            'active_users' => 56
        ));
    });

    $group->post('/settings', function (Request $request, Response $response) use ($api) {
        $body = json_decode((string) $request->getBody(), true);
        // Guardar configuración
        return $api->successResponse($response, array('saved' => true));
    });

})->add(new RequirePermissionMiddleware('mimodulo', 'admin')); // Middleware para todo el grupo
```

### 3. Groups Anidados

```php
$app->group('/mimodulo', function ($group) use ($db, $api) {

    // Grupo público: /mimodulo/public/*
    $group->group('/public', function ($publicGroup) use ($api) {
        $publicGroup->get('/info', function ($req, $res) use ($api) {
            return $api->successResponse($res, array('version' => '1.0'));
        });
    });

    // Grupo de items: /mimodulo/items/*
    $group->group('/items', function ($itemsGroup) use ($db, $api) {
        $itemsGroup->get('', function ($req, $res) use ($db, $api) {
            // Listar items
            return $api->successResponse($res, array());
        });

        $itemsGroup->get('/{id}', function ($req, $res, $args) use ($db, $api) {
            // Obtener item
            return $api->successResponse($res, array('id' => $args['id']));
        });
    });

    // Grupo admin: /mimodulo/admin/*
    $group->group('/admin', function ($adminGroup) use ($db, $api) {
        $adminGroup->get('/dashboard', function ($req, $res) use ($api) {
            return $api->successResponse($res, array('dashboard' => true));
        });
    })->add(new RequireAdminMiddleware());

});
```

---

## Documentación OpenAPI

### Estructura Completa de addRoute()

```php
$api->addRoute(array(
    // Método HTTP (requerido)
    'method' => 'POST',

    // Path de la ruta (requerido)
    'path' => '/mimodulo/items',

    // Resumen corto (recomendado)
    'summary' => 'Crear un nuevo item',

    // Descripción larga (opcional)
    'description' => 'Crea un nuevo item en el sistema. Requiere permisos de escritura.',

    // Tags para agrupar en Swagger UI
    'tags' => array('MiModulo', 'Items'),

    // Si es ruta pública (sin auth)
    'public' => false,

    // ID único de la operación
    'operationId' => 'createItem',

    // Parámetros (path, query, header)
    'parameters' => array(
        array(
            'name' => 'id',
            'in' => 'path',  // 'path', 'query', 'header', 'cookie'
            'required' => true,
            'description' => 'ID del item',
            'schema' => array(
                'type' => 'integer',
                'minimum' => 1
            )
        ),
        array(
            'name' => 'include_details',
            'in' => 'query',
            'required' => false,
            'schema' => array(
                'type' => 'boolean',
                'default' => false
            )
        )
    ),

    // Body de la petición (para POST, PUT, PATCH)
    'requestBody' => array(
        'required' => true,
        'description' => 'Datos del item a crear',
        'content' => array(
            'application/json' => array(
                'schema' => array(
                    'type' => 'object',
                    'required' => array('name'),
                    'properties' => array(
                        'name' => array(
                            'type' => 'string',
                            'minLength' => 1,
                            'maxLength' => 255,
                            'example' => 'Mi item'
                        ),
                        'description' => array(
                            'type' => 'string',
                            'nullable' => true,
                            'example' => 'Descripción opcional'
                        ),
                        'price' => array(
                            'type' => 'number',
                            'format' => 'float',
                            'minimum' => 0,
                            'example' => 99.99
                        ),
                        'active' => array(
                            'type' => 'boolean',
                            'default' => true
                        ),
                        'tags' => array(
                            'type' => 'array',
                            'items' => array('type' => 'string'),
                            'example' => array('tag1', 'tag2')
                        )
                    )
                )
            )
        )
    ),

    // Respuestas posibles
    'responses' => array(
        '201' => array(
            'description' => 'Item creado exitosamente',
            'content' => array(
                'application/json' => array(
                    'schema' => array(
                        'type' => 'object',
                        'properties' => array(
                            'success' => array('type' => 'boolean', 'example' => true),
                            'data' => array(
                                'type' => 'object',
                                'properties' => array(
                                    'id' => array('type' => 'integer', 'example' => 123),
                                    'name' => array('type' => 'string', 'example' => 'Mi item')
                                )
                            )
                        )
                    )
                )
            )
        ),
        '400' => array(
            'description' => 'Datos inválidos',
            'content' => array(
                'application/json' => array(
                    'schema' => array('$ref' => '#/components/schemas/ErrorResponse')
                )
            )
        ),
        '401' => array(
            'description' => 'No autenticado'
        ),
        '403' => array(
            'description' => 'Sin permisos'
        )
    ),

    // Configuración de seguridad específica
    'security' => array(
        array('DOLAPIKEY' => array())
    ),

    // Marcar como deprecado
    'deprecated' => false
));
```

### Registrar Schemas Personalizados

```php
// Obtener el generador OpenAPI
$openApi = $api->getOpenApiGenerator();

// Añadir un schema reutilizable
$openApi->addSchema('MiItem', array(
    'type' => 'object',
    'required' => array('id', 'name'),
    'properties' => array(
        'id' => array(
            'type' => 'integer',
            'description' => 'ID único del item',
            'example' => 1
        ),
        'name' => array(
            'type' => 'string',
            'description' => 'Nombre del item',
            'minLength' => 1,
            'maxLength' => 255,
            'example' => 'Mi item'
        ),
        'description' => array(
            'type' => 'string',
            'nullable' => true,
            'example' => 'Descripción del item'
        ),
        'price' => array(
            'type' => 'number',
            'format' => 'float',
            'example' => 99.99
        ),
        'created_at' => array(
            'type' => 'string',
            'format' => 'date-time',
            'example' => '2025-01-01T12:00:00Z'
        ),
        'status' => array(
            'type' => 'string',
            'enum' => array('active', 'inactive', 'deleted'),
            'example' => 'active'
        )
    )
));

// Usar el schema en una ruta
$api->addRoute(array(
    'method' => 'GET',
    'path' => '/mimodulo/items/{id}',
    'summary' => 'Obtener item',
    'tags' => array('MiModulo'),
    'responses' => array(
        '200' => array(
            'description' => 'Item encontrado',
            'content' => array(
                'application/json' => array(
                    'schema' => array(
                        'type' => 'object',
                        'properties' => array(
                            'success' => array('type' => 'boolean'),
                            'data' => array('$ref' => '#/components/schemas/MiItem')
                        )
                    )
                )
            )
        )
    )
));
```

### Registrar Tags Personalizados

```php
$openApi = $api->getOpenApiGenerator();

// Añadir tags con descripción
$openApi->addTag('MiModulo', 'Endpoints del módulo MiModulo');
$openApi->addTag('MiModulo - Items', 'Gestión de items');
$openApi->addTag('MiModulo - Admin', 'Endpoints de administración');
```

---

## Autenticación y Permisos

### Documentación Protegida

EasyAPI protege automáticamente la documentación según el estado de autenticación:

- **Sin autenticación**: `/docs` y `/openapi.json` solo muestran endpoints públicos
- **Con autenticación**: Muestra todos los endpoints según los permisos del usuario

```
GET /api/easyapi/docs              → Solo endpoints públicos
GET /api/easyapi/docs (DOLAPIKEY)  → Todos los endpoints permitidos
```

### Obtener el Usuario Autenticado

```php
$app->get('/mimodulo/profile', function (Request $request, Response $response) use ($api) {
    // El usuario está disponible como atributo de la request
    $user = $request->getAttribute('dolibarr_user');

    // También disponible la conexión a BD
    $db = $request->getAttribute('dolibarr_db');

    return $api->successResponse($response, array(
        'id' => (int) $user->id,
        'login' => $user->login,
        'fullname' => $user->getFullName(),
        'email' => $user->email,
        'is_admin' => (bool) $user->admin,
        'entity' => (int) $user->entity
    ));
});
```

### RequirePermission Middleware

El middleware `RequirePermission` proporciona una forma declarativa de proteger endpoints:

```php
use EasyAPI\Middleware\RequirePermission;

// Requiere UN permiso específico
$app->get('/facturas', function ($request, $response) use ($api) {
    // El usuario ya tiene el permiso verificado
    return $api->successResponse($response, $facturas);
})->add(RequirePermission::check('facture->lire'));

// Requiere TODOS los permisos (modo ALL - default)
$app->get('/full-report', function ($request, $response) use ($api) {
    // Usuario tiene facture->lire Y banque->lire
    return $api->successResponse($response, $report);
})->add(RequirePermission::all('facture->lire,banque->lire'));

// Requiere AL MENOS UNO de los permisos (modo ANY)
$app->get('/documents', function ($request, $response) use ($api) {
    // Usuario tiene facture->lire O commande->lire O propal->lire
    return $api->successResponse($response, $docs);
})->add(RequirePermission::any('facture->lire,commande->lire,propal->lire'));
```

### Documentar Permisos en OpenAPI

Al registrar la ruta con `addRoute()`, incluye el campo `permissions` para que aparezca en la documentación:

```php
$api->addRoute(array(
    'method' => 'GET',
    'path' => '/mimodulo/facturas',
    'summary' => 'Listar facturas',
    'description' => 'Obtiene las facturas del usuario',
    'tags' => array('Mi Módulo'),
    'permissions' => 'facture->lire'  // Aparece en la descripción de OpenAPI
));

// Para múltiples permisos
$api->addRoute(array(
    'method' => 'GET',
    'path' => '/mimodulo/report',
    'permissions' => 'facture->lire,banque->lire'
));
```

### Endpoints Públicos

Para marcar un endpoint como público (visible sin autenticación):

```php
// El endpoint estará visible en /docs aunque no estés autenticado
$api->addRoute(array(
    'method' => 'GET',
    'path' => '/mimodulo/public-info',
    'summary' => 'Información pública',
    'tags' => array('Mi Módulo'),
    'public' => true  // ← Marcado como público
));
```

### Verificar Permisos Manualmente

```php
$app->get('/mimodulo/facturas', function (Request $request, Response $response) use ($db, $api) {
    $user = $request->getAttribute('dolibarr_user');

    // Verificar permiso usando el método helper
    if (!$api->checkPermission($request, 'facture', 'lire')) {
        return $api->errorResponse($response, 'No tienes permiso para ver facturas', 403);
    }

    // O verificar manualmente
    if (empty($user->rights->facture->lire)) {
        return $api->errorResponse($response, 'No tienes permiso para ver facturas', 403);
    }

    // ... continuar con la lógica
    return $api->successResponse($response, array());
});
```

### Permisos Comunes de Dolibarr

```php
// Formato: modulo->permiso o modulo->submodulo->permiso
// Ejemplos de verificación de permisos
$user->rights->societe->lire       // Leer terceros
$user->rights->societe->creer      // Crear terceros
$user->rights->societe->supprimer  // Eliminar terceros

$user->rights->facture->lire       // Leer facturas
$user->rights->facture->creer      // Crear facturas
$user->rights->facture->supprimer  // Eliminar facturas

$user->rights->commande->lire      // Leer pedidos
$user->rights->produit->lire       // Leer productos
$user->rights->stock->lire         // Leer stock
$user->rights->banque->lire        // Leer cuentas bancarias
$user->rights->projet->lire        // Leer proyectos

// Admin tiene todos los permisos
if ($user->admin) {
    // Puede hacer todo
}
```

### Ejemplo Completo con Permisos

```php
<?php
// class/actions_mimodulo.class.php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ActionsMimodulo
{
    public function easyapiRegisterRoutes($parameters, &$object, &$action, $hookmanager)
    {
        $app = $parameters['app'];
        $api = $parameters['api'];
        $db = $parameters['db'];

        // Cargar middleware de permisos
        require_once DOL_DOCUMENT_ROOT . '/custom/easyapi/middleware/RequirePermission.php';

        // Endpoint público (no requiere auth)
        $app->get('/mimodulo/version', function ($request, $response) use ($api) {
            return $api->successResponse($response, array('version' => '1.0.0'));
        });
        $api->addRoute(array(
            'method' => 'GET',
            'path' => '/mimodulo/version',
            'summary' => 'Versión del módulo',
            'tags' => array('Mi Módulo'),
            'public' => true
        ));

        // Endpoint que requiere autenticación pero no permisos específicos
        $app->get('/mimodulo/me', function ($request, $response) use ($api) {
            $user = $request->getAttribute('dolibarr_user');
            return $api->successResponse($response, array('login' => $user->login));
        });
        $api->addRoute(array(
            'method' => 'GET',
            'path' => '/mimodulo/me',
            'summary' => 'Mi perfil',
            'tags' => array('Mi Módulo')
            // Sin 'public' ni 'permissions' = requiere auth pero no permisos especiales
        ));

        // Endpoint que requiere permiso específico
        $app->get('/mimodulo/facturas', function ($request, $response) use ($db, $api) {
            // Aquí ya sabemos que el usuario tiene facture->lire
            return $api->successResponse($response, array('facturas' => []));
        })->add(\EasyAPI\Middleware\RequirePermission::check('facture->lire'));

        $api->addRoute(array(
            'method' => 'GET',
            'path' => '/mimodulo/facturas',
            'summary' => 'Listar facturas',
            'tags' => array('Mi Módulo'),
            'permissions' => 'facture->lire'
        ));

        return 0;
    }
}
```

---

## Helpers y Utilidades

### Respuestas Disponibles

```php
// Respuesta JSON genérica
$api->jsonResponse($response, array('key' => 'value'), 200);

// Respuesta de éxito
$api->successResponse($response, $data, 200);
$api->successResponse($response, $data, 201);  // Created

// Respuesta de error
$api->errorResponse($response, 'Mensaje de error', 400);
$api->errorResponse($response, 'No encontrado', 404);
$api->errorResponse($response, 'Sin permisos', 403);
$api->errorResponse($response, 'Error interno', 500);

// Respuesta paginada (añade headers y meta de paginación)
$api->paginatedResponse($response, $items, $total, $page, $perPage);
```

### Headers de Paginación

La respuesta paginada añade automáticamente:

```
X-Total-Count: 100
X-Page: 1
X-Per-Page: 20
X-Last-Page: 5
```

Y en el body:

```json
{
    "success": true,
    "data": [...],
    "meta": {
        "pagination": {
            "total": 100,
            "page": 1,
            "per_page": 20,
            "last_page": 5,
            "has_more": true
        }
    }
}
```

### Validación de Request

```php
$app->post('/mimodulo/items', function (Request $request, Response $response) use ($api) {
    // Obtener body
    $body = json_decode((string) $request->getBody(), true);

    // Validaciones básicas
    $errors = array();

    if (empty($body['name'])) {
        $errors[] = 'name es requerido';
    } elseif (strlen($body['name']) > 255) {
        $errors[] = 'name no puede tener más de 255 caracteres';
    }

    if (isset($body['price']) && !is_numeric($body['price'])) {
        $errors[] = 'price debe ser numérico';
    }

    if (isset($body['email']) && !filter_var($body['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'email no es válido';
    }

    if (!empty($errors)) {
        return $api->errorResponse($response, 'Errores de validación', 400, array(
            'validation_errors' => $errors
        ));
    }

    // Continuar con la lógica...
});
```

---

## Buenas Prácticas

### 1. Usar variable estática para evitar registros duplicados

```php
class ActionsMimodulo
{
    private static $routesRegistered = false;

    public function easyapiRegisterRoutes($parameters, &$object, &$action, $hookmanager)
    {
        if (self::$routesRegistered) {
            return 0;
        }
        self::$routesRegistered = true;

        // ... registrar rutas

        return 0;
    }
}
```

### 2. Organizar rutas en métodos separados

```php
class ActionsMimodulo
{
    private static $routesRegistered = false;

    public function easyapiRegisterRoutes($parameters, &$object, &$action, $hookmanager)
    {
        if (self::$routesRegistered) {
            return 0;
        }
        self::$routesRegistered = true;

        $app = $parameters['app'];
        $db = $parameters['db'];
        $api = $parameters['api'];

        $this->registerItemRoutes($app, $db, $api);
        $this->registerAdminRoutes($app, $db, $api);
        $this->registerPublicRoutes($app, $db, $api);

        return 0;
    }

    private function registerItemRoutes($app, $db, $api)
    {
        $app->group('/mimodulo/items', function ($group) use ($db, $api) {
            // ... rutas de items
        });
    }

    private function registerAdminRoutes($app, $db, $api)
    {
        $app->group('/mimodulo/admin', function ($group) use ($db, $api) {
            // ... rutas de admin
        })->add(new RequireAdminMiddleware());
    }

    private function registerPublicRoutes($app, $db, $api)
    {
        // ... rutas públicas
    }
}
```

### 3. Siempre validar entrada del usuario

```php
// BIEN: Sanitizar y validar
$id = (int) $args['id'];
$name = $db->escape($body['name']);

// MAL: Usar directamente
$id = $args['id'];  // Podría ser "1; DROP TABLE users"
$name = $body['name'];  // SQL Injection
```

### 4. Manejar errores correctamente

```php
$app->get('/mimodulo/item/{id}', function (Request $request, Response $response, array $args) use ($db, $api) {
    try {
        $id = (int) $args['id'];

        $result = $db->query("SELECT * FROM " . MAIN_DB_PREFIX . "mi_tabla WHERE rowid = " . $id);

        if (!$result) {
            dol_syslog("EasyAPI MiModulo: Error en query - " . $db->lasterror(), LOG_ERR);
            return $api->errorResponse($response, 'Error de base de datos', 500);
        }

        if ($db->num_rows($result) == 0) {
            return $api->errorResponse($response, 'Item no encontrado', 404);
        }

        $obj = $db->fetch_object($result);
        return $api->successResponse($response, array('id' => (int) $obj->rowid));

    } catch (\Exception $e) {
        dol_syslog("EasyAPI MiModulo: Exception - " . $e->getMessage(), LOG_ERR);
        return $api->errorResponse($response, 'Error interno', 500);
    }
});
```

### 5. Documentar todas las rutas

```php
// Por cada ruta registrada, añadir documentación
$app->get('/mimodulo/items', $handler);
$api->addRoute(array(
    'method' => 'GET',
    'path' => '/mimodulo/items',
    'summary' => 'Listar items',
    'description' => 'Devuelve una lista paginada de items',
    'tags' => array('MiModulo')
));
```

---

## Referencia Completa

### Métodos de ApiEasyApi

| Método | Descripción |
|--------|-------------|
| `getApp()` | Obtiene la instancia de Slim\App |
| `getDb()` | Obtiene la conexión DoliDB |
| `addRoute(array $config)` | Añade una ruta a la documentación OpenAPI |
| `getOpenApiGenerator()` | Obtiene el generador OpenAPI |
| `getRegisteredRoutes()` | Obtiene todas las rutas registradas |
| `jsonResponse($response, $data, $status)` | Genera respuesta JSON |
| `successResponse($response, $data, $status, $meta)` | Genera respuesta de éxito |
| `errorResponse($response, $message, $code, $extra)` | Genera respuesta de error |
| `paginatedResponse($response, $items, $total, $page, $perPage)` | Genera respuesta paginada |
| `checkPermission($request, $module, $permission)` | Verifica permisos del usuario |
| `group($prefix, $callback)` | Crea un grupo de rutas |
| `addMiddleware($middleware)` | Añade un middleware global |

### Métodos de OpenApiGenerator

| Método | Descripción |
|--------|-------------|
| `addRoute(array $config)` | Añade una ruta a la especificación |
| `addSchema($name, $definition)` | Añade un schema reutilizable |
| `addTag($name, $description)` | Añade un tag |
| `setInfo($title, $version, $description)` | Configura la información de la API |
| `generate()` | Genera la especificación OpenAPI como array |
| `toJson()` | Genera la especificación como JSON |

### Atributos de Request

| Atributo | Tipo | Descripción |
|----------|------|-------------|
| `dolibarr_user` | User | Usuario autenticado de Dolibarr |
| `dolibarr_db` | DoliDB | Conexión a base de datos |

### Códigos HTTP Comunes

| Código | Significado |
|--------|-------------|
| 200 | OK - Petición exitosa |
| 201 | Created - Recurso creado |
| 204 | No Content - Éxito sin contenido |
| 400 | Bad Request - Datos inválidos |
| 401 | Unauthorized - No autenticado |
| 403 | Forbidden - Sin permisos |
| 404 | Not Found - No encontrado |
| 429 | Too Many Requests - Rate limit |
| 500 | Internal Server Error - Error del servidor |

---

## Soporte

- **Documentación**: `/docs` (Swagger UI)
- **OpenAPI JSON**: `/openapi.json`
- **GitHub**: [URL del repositorio]
- **Email**: aluquerivasdev@gmail.com

---

*EasyAPI v1.0.0 - Dolibarr Headless API*
*Copyright (C) 2025 Alberto Luque Rivas*
