# 🪝 Ejemplos de EAPI usando Hooks

Esta carpeta contiene ejemplos de cómo crear endpoints de API usando el sistema de **Hooks de Dolibarr** tradicional.

## 📑 Índice de Ejemplos

| Archivo | Descripción |
|---------|-------------|
| [01_BasicHook.php](01_BasicHook.php) | Hook básico: GET, POST, parámetros query/path |
| [02_CrudHook.php](02_CrudHook.php) | CRUD completo: CREATE, READ, UPDATE, DELETE |
| [03_PermissionsHook.php](03_PermissionsHook.php) | Control de permisos manual |
| [04_DocumentationHook.php](04_DocumentationHook.php) | Documentación OpenAPI completa |
| [05_DolibarrObjectsHook.php](05_DolibarrObjectsHook.php) | Usar clases Dolibarr (Societe, Facture, Product) |

---

## 🔧 Cómo usar Hooks

### 1. Crear el archivo de clase

```php
// htdocs/custom/mimodulo/class/actions_mimodulo.class.php
class ActionsMimodulo
{
    public $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function easyApiRegisterRoutes($parameters, &$object, &$action)
    {
        $app = $parameters['app'];  // Instancia de Slim App
        $api = $object;             // Instancia de EasyAPI

        // Registrar rutas aquí...

        return 0; // Siempre devolver 0
    }
}
```

### 2. Activar el hook en modMimodulo.class.php

```php
$this->module_parts = array(
    'hooks' => array(
        'easyapi'  // Nombre del contexto del hook
    )
);
```

---

## 📌 Estructura básica de un endpoint

```php
$app->get('/mi-ruta/{id}', function ($request, $response, $args) use ($self) {
    // $request  = Petición PSR-7
    // $response = Respuesta PSR-7
    // $args     = Parámetros de ruta
    // $self     = $this (acceso a $self->db)

    // Obtener query params
    $params = $request->getQueryParams();

    // Obtener body (POST/PUT)
    $body = json_decode((string) $request->getBody(), true);

    // Construir respuesta
    $data = array('id' => $args['id']);

    $response = $response->withHeader('Content-Type', 'application/json');
    $response->getBody()->write(json_encode($data));
    return $response;
});
```

---

## 📖 Documentar endpoints para Swagger

Para que tus endpoints aparezcan en Swagger UI, debes añadir la documentación a `$api->hookRoutes[]`:

```php
$api->hookRoutes[] = array(
    'methods' => array('GET'),           // Métodos HTTP
    'path' => '/mi-ruta/{id}',           // Ruta
    'summary' => 'Mi endpoint',          // Título corto
    'description' => 'Descripción...',   // Descripción larga
    'tags' => array('Mi Tag'),           // Agrupación
    'security' => array(array('api_key' => array())), // Requiere DOLAPIKEY
    'parameters' => array(...),          // Params path/query
    'requestBody' => array(...),         // Body (POST/PUT)
    'responses' => array(...)            // Respuestas
);
```

---

## 📝 Ejemplo 01: Hook Básico

**Archivo:** `01_BasicHook.php`

Demuestra:
- ✅ Estructura básica de una clase de hooks
- ✅ Endpoint GET simple
- ✅ Endpoint POST con body
- ✅ Parámetros de query (`?limit=10&page=1`)
- ✅ Parámetros de ruta (`/items/{id}`)
- ✅ Documentación con hookRoutes

```php
// GET /basic/items?limit=10
$app->get('/basic/items', function ($request, $response) {
    $params = $request->getQueryParams();
    $limit = isset($params['limit']) ? (int) $params['limit'] : 10;
    // ...
});

// POST /basic/items
$app->post('/basic/items', function ($request, $response) {
    $body = json_decode((string) $request->getBody(), true);
    // ...
});

// GET /basic/items/{id}
$app->get('/basic/items/{id}', function ($request, $response, $args) {
    $id = (int) $args['id'];
    // ...
});
```

---

## 📝 Ejemplo 02: CRUD Completo

**Archivo:** `02_CrudHook.php`

Demuestra:
- ✅ CREATE: `POST /crud/productos`
- ✅ READ (lista): `GET /crud/productos`
- ✅ READ (uno): `GET /crud/productos/{id}`
- ✅ UPDATE: `PUT /crud/productos/{id}`
- ✅ DELETE: `DELETE /crud/productos/{id}`
- ✅ Validación manual de datos
- ✅ Paginación completa
- ✅ Consultas SQL directas

```php
// Listar con paginación
$app->get('/crud/productos', function ($request, $response) use ($self) {
    $params = $request->getQueryParams();
    $limit = min((int) ($params['limit'] ?? 25), 100);
    $page = max(1, (int) ($params['page'] ?? 1));
    $offset = ($page - 1) * $limit;
    
    $sql = "SELECT * FROM ... LIMIT $limit OFFSET $offset";
    // ...
});

// Crear
$app->post('/crud/productos', function ($request, $response) use ($self) {
    $body = json_decode((string) $request->getBody(), true);
    
    // Validación
    if (empty($body['nombre'])) {
        return errorResponse($response, 400, 'nombre requerido');
    }
    
    // INSERT...
});
```

---

## 📝 Ejemplo 03: Control de Permisos

**Archivo:** `03_PermissionsHook.php`

Demuestra:
- ✅ Verificar permisos manualmente
- ✅ Lógica OR (cualquier permiso)
- ✅ Lógica AND (todos los permisos)
- ✅ Verificar si es admin
- ✅ Helper function para permisos

```php
// Helper para verificar permisos
private function checkPermission($perms, $mode = 'any')
{
    global $user;
    
    foreach ($perms as $perm) {
        $parts = explode('.', $perm);
        $hasPermission = !empty($user->rights->{$parts[0]}->{$parts[1]});
        
        if ($mode === 'any' && $hasPermission) {
            return true;
        }
        if ($mode === 'all' && !$hasPermission) {
            return false;
        }
    }
    
    return ($mode === 'all');
}

// Uso en endpoint
$app->get('/permisos/terceros', function ($request, $response) use ($self) {
    if (!$self->checkPermission(array('societe.lire'))) {
        return unauthorized($response);
    }
    // ...
});
```

---

## 📝 Ejemplo 04: Documentación OpenAPI

**Archivo:** `04_DocumentationHook.php`

Demuestra:
- ✅ Documentación completa con todos los campos
- ✅ Parámetros de path con schema
- ✅ Parámetros de query con enum y default
- ✅ RequestBody completo con propiedades
- ✅ Respuestas documentadas
- ✅ Endpoint público (sin security)
- ✅ Múltiples métodos en mismo path

```php
// Documentación COMPLETA
$api->hookRoutes[] = array(
    'methods' => array('GET'),
    'path' => '/docs/completo/{id}',
    'summary' => 'Endpoint documentado completo',
    'description' => 'Descripción larga del endpoint...',
    'tags' => array('Documentación'),
    'security' => array(array('api_key' => array())),
    
    'parameters' => array(
        // Parámetro de path
        array(
            'name' => 'id',
            'in' => 'path',
            'required' => true,
            'description' => 'ID del recurso',
            'schema' => array(
                'type' => 'integer',
                'minimum' => 1,
                'example' => 123
            )
        ),
        // Parámetro de query
        array(
            'name' => 'formato',
            'in' => 'query',
            'required' => false,
            'schema' => array(
                'type' => 'string',
                'enum' => array('simple', 'detallado'),
                'default' => 'simple'
            )
        )
    ),
    
    'responses' => array(
        '200' => array(
            'description' => 'Éxito',
            'content' => array(
                'application/json' => array(
                    'schema' => array(
                        'type' => 'object',
                        'properties' => array(...)
                    )
                )
            )
        )
    )
);

// Endpoint PÚBLICO (sin security)
$api->hookRoutes[] = array(
    'methods' => array('GET'),
    'path' => '/docs/publico',
    'summary' => 'Endpoint público',
    // SIN 'security' = no requiere autenticación
);
```

---

## 📝 Ejemplo 05: Objetos Dolibarr

**Archivo:** `05_DolibarrObjectsHook.php`

Demuestra:
- ✅ Usar clase Societe (terceros)
- ✅ Usar clase Facture (facturas)
- ✅ Usar clase Product (productos)
- ✅ Acceder a $user, $conf, $langs
- ✅ Cargar clases con require_once
- ✅ Consultas SQL + fetch de objetos

```php
// Cargar clase de Dolibarr
require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

// Usar objeto Societe
$tercero = new Societe($self->db);
$result = $tercero->fetch($id);

if ($result > 0) {
    // Cargar datos adicionales
    $tercero->fetch_optionals();
    $tercero->fetch_thirdparty();
    
    $data = array(
        'id' => $tercero->id,
        'name' => $tercero->name,
        'email' => $tercero->email,
        // ...
    );
}

// Crear tercero
$tercero = new Societe($self->db);
$tercero->name = $body['name'];
$tercero->email = $body['email'];
$result = $tercero->create($user);

// Acceder a configuración global
global $conf, $langs;
$currency = $conf->currency;
$translation = $langs->trans('Customers');
```

---

## 🆚 Hooks vs Resources

| Característica | Hooks | Resources |
|----------------|-------|-----------|
| Configuración | Más código manual | Auto-configuración |
| Permisos | Verificación manual | `->requirePermission()` |
| Validación | Código manual | `validateBody()` |
| Documentación | `$api->hookRoutes[]` | Métodos fluidos |
| Middleware | No disponible | `->middleware()` |
| Endpoints públicos | Sin `security` | `->public()` |
| Recomendado para | Integraciones simples | Módulos completos |

---

## 🚀 Cuándo usar Hooks

✅ **Usa Hooks cuando:**
- Solo necesitas 1-2 endpoints simples
- Ya tienes un módulo existente y quieres añadir API
- Prefieres control total sobre la lógica
- No necesitas middleware complejo

❌ **Usa Resources cuando:**
- Creas un módulo nuevo desde cero
- Necesitas muchos endpoints organizados
- Quieres validación automática
- Necesitas middleware (cache, rate limit, etc.)

---

## 📁 Archivos de ejemplo

```
examples_hooks/
├── 01_BasicHook.php         # Hook básico
├── 02_CrudHook.php          # CRUD completo
├── 03_PermissionsHook.php   # Control de permisos
├── 04_DocumentationHook.php # Documentación OpenAPI
├── 05_DolibarrObjectsHook.php # Objetos Dolibarr
└── README.md                # Este archivo
```

---

## 💡 Tips importantes

1. **Siempre devuelve 0** al final del hook
2. **Usa `$self = $this`** para acceder a la clase dentro del closure
3. **Accede a la BD** con `$self->db`
4. **Variables globales**: `global $user, $conf, $langs;`
5. **Cargar clases**: `require_once DOL_DOCUMENT_ROOT . '/path/to/class.php';`
6. **Respuestas JSON**: Siempre añade `Content-Type: application/json`
7. **Documenta** con `$api->hookRoutes[]` para Swagger

---

## 📚 Recursos adicionales

- [Documentación EasyAPI](../README.md)
- [Ejemplos con Resources](../examples_resources/README.md)
- [Documentación Dolibarr Hooks](https://wiki.dolibarr.org/index.php/Hooks)
