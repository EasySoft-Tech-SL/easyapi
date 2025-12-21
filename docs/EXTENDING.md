# Cómo Extender EasyAPI

## Resumen

EasyAPI usa el sistema de **hooks** de Dolibarr. Tu módulo implementa un método que recibe la instancia de Slim y registra sus rutas. La documentación OpenAPI se genera automáticamente.

---

## Paso 1: Registrar el hook

En tu archivo `core/modules/modTuModulo.class.php`:

```php
$this->module_parts = array(
    'hooks' => array(
        'data' => array('easyapi'),
    ),
);
```

---

## Paso 2: Crear la clase de acciones

Crear archivo `class/actions_tumodulo.class.php`:

```php
<?php
class ActionsTumodulo
{
    public $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Hook para registrar rutas en EasyAPI
     */
    public function easyapiRegisterRoutes($parameters, &$object, &$action, $hookmanager)
    {
        $app = $parameters['app'];  // Slim App
        $db = $parameters['db'];    // DoliDB  
        $api = $parameters['api'];  // ApiEasyApi (helpers)

        // --- TUS RUTAS AQUÍ ---
        
        return 0;
    }
}
```

---

## Ejemplos de Rutas

### GET simple con documentación OpenAPI

```php
$app->get('/productos', function ($request, $response) use ($db, $api) {
    $items = array(); // tu consulta a BD
    
    return $api->successResponse($response, $items);
});

// Documentación OpenAPI completa
$api->addRoute(array(
    'method' => 'GET',
    'path' => '/productos',
    'summary' => 'Listar productos',
    'description' => 'Obtiene un listado de todos los productos',
    'tags' => array('Productos'),
    'public' => false, // requiere autenticación
    'parameters' => array(
        array(
            'name' => 'limit',
            'in' => 'query',
            'description' => 'Número máximo de resultados',
            'required' => false,
            'schema' => array('type' => 'integer', 'default' => 20)
        ),
        array(
            'name' => 'page',
            'in' => 'query',
            'description' => 'Número de página',
            'required' => false,
            'schema' => array('type' => 'integer', 'default' => 1)
        )
    )
));
```

### GET con parámetro en path

```php
$app->get('/productos/{id:[0-9]+}', function ($request, $response, $args) use ($db, $api) {
    $id = (int) $args['id'];
    
    // Tu lógica
    
    return $api->successResponse($response, array('id' => $id));
});

$api->addRoute(array(
    'method' => 'GET',
    'path' => '/productos/{id}',
    'summary' => 'Obtener producto',
    'description' => 'Obtiene un producto por su ID',
    'tags' => array('Productos'),
    'parameters' => array(
        array(
            'name' => 'id',
            'in' => 'path',
            'description' => 'ID del producto',
            'required' => true,
            'schema' => array('type' => 'integer')
        )
    )
));
```

### POST con body JSON

```php
$app->post('/productos', function ($request, $response) use ($db, $api) {
    $body = json_decode((string) $request->getBody(), true);
    
    // Validar
    if (empty($body['ref'])) {
        return $api->errorResponse($response, 'ref es requerido', 400);
    }
    
    // Crear...
    
    return $api->successResponse($response, array('created' => true), 201);
});

$api->addRoute(array(
    'method' => 'POST',
    'path' => '/productos',
    'summary' => 'Crear producto',
    'description' => 'Crea un nuevo producto',
    'tags' => array('Productos'),
    'requestBody' => array(
        'required' => true,
        'content' => array(
            'application/json' => array(
                'schema' => array(
                    'type' => 'object',
                    'required' => array('ref', 'label'),
                    'properties' => array(
                        'ref' => array('type' => 'string', 'example' => 'PROD001'),
                        'label' => array('type' => 'string', 'example' => 'Mi Producto'),
                        'price' => array('type' => 'number', 'example' => 99.99),
                        'description' => array('type' => 'string')
                    )
                )
            )
        )
    ),
    'responses' => array(
        '201' => array(
            'description' => 'Producto creado exitosamente'
        ),
        '400' => array(
            'description' => 'Datos inválidos'
        )
    )
));
```

### Grupo de rutas (prefijo común)

```php
$app->group('/facturas', function ($group) use ($db, $api) {
    
    $group->get('', function ($req, $res) use ($db, $api) {
        // GET /facturas
        return $api->successResponse($res, array());
    });
    
    $group->get('/{id}', function ($req, $res, $args) use ($db, $api) {
        // GET /facturas/123
        return $api->successResponse($res, array('id' => $args['id']));
    });
    
    $group->post('', function ($req, $res) use ($db, $api) {
        // POST /facturas
        return $api->successResponse($res, array(), 201);
    });
});

// Documentar cada ruta del grupo
$api->addRoute(array('method' => 'GET', 'path' => '/facturas', 'summary' => 'Listar facturas', 'tags' => array('Facturas')));
$api->addRoute(array('method' => 'GET', 'path' => '/facturas/{id}', 'summary' => 'Ver factura', 'tags' => array('Facturas')));
$api->addRoute(array('method' => 'POST', 'path' => '/facturas', 'summary' => 'Crear factura', 'tags' => array('Facturas')));
```

---

## Registrar Schemas OpenAPI

Puedes añadir schemas personalizados para reutilizar en tus respuestas:

```php
$openApi = $api->getOpenApiGenerator();

// Añadir schema personalizado
$openApi->addSchema('Producto', array(
    'type' => 'object',
    'properties' => array(
        'id' => array('type' => 'integer', 'example' => 1),
        'ref' => array('type' => 'string', 'example' => 'PROD001'),
        'label' => array('type' => 'string', 'example' => 'Mi Producto'),
        'price' => array('type' => 'number', 'format' => 'float', 'example' => 99.99),
        'status' => array('type' => 'integer', 'enum' => array(0, 1), 'example' => 1)
    )
));

// Añadir tag personalizado
$openApi->addTag('Productos', 'Gestión de productos del catálogo');

// Usar el schema en respuestas
$api->addRoute(array(
    'method' => 'GET',
    'path' => '/productos/{id}',
    'summary' => 'Obtener producto',
    'tags' => array('Productos'),
    'responses' => array(
        '200' => array(
            'description' => 'Producto encontrado',
            'content' => array(
                'application/json' => array(
                    'schema' => array(
                        'type' => 'object',
                        'properties' => array(
                            'success' => array('type' => 'boolean'),
                            'data' => array('$ref' => '#/components/schemas/Producto')
                        )
                    )
                )
            )
        )
    )
));
```

---

## Helpers Disponibles

### Respuestas

```php
// Éxito
$api->successResponse($response, $data, $status = 200);

// Error
$api->errorResponse($response, 'mensaje', $code = 400);

// Paginación automática
$api->paginatedResponse($response, $items, $total, $page, $perPage);
// Añade headers: X-Total-Count, X-Page, X-Per-Page, X-Last-Page
```

### Usuario autenticado

```php
$user = $request->getAttribute('dolibarr_user');

// Propiedades útiles:
$user->id
$user->login
$user->firstname
$user->lastname
$user->email
$user->admin        // bool
$user->entity
$user->rights->modulo->permiso
```

### Verificar permisos

```php
if (!$api->checkPermission($request, 'produit', 'lire')) {
    return $api->errorResponse($response, 'Sin permisos para leer productos', 403);
}
```

---

## Endpoints Disponibles

Una vez activado el módulo, accede a:

- **Swagger UI**: `/api/docs` - Documentación interactiva
- **OpenAPI JSON**: `/api/openapi.json` - Especificación OpenAPI 3.0
- **Status**: `/api/status` - Health check
- **Login**: `POST /api/login` - Obtener API key

---

## Autenticación

Todas las rutas (excepto las públicas) requieren autenticación. Métodos soportados:

1. **Header DOLAPIKEY** (recomendado):
   ```
   DOLAPIKEY: tu_api_key
   ```

2. **Bearer Token**:
   ```
   Authorization: Bearer tu_api_key
   ```

3. **Query Parameter**:
   ```
   /api/productos?DOLAPIKEY=tu_api_key
   ```
