# 📦 Ejemplos de EAPI Resources

Esta carpeta contiene ejemplos completos de cómo crear endpoints usando el sistema **EAPI Resources**.

## 🚀 ¿Cómo usar estos ejemplos?

1. Copia el archivo PHP que necesites a tu módulo: `custom/tumodulo/eapi/`
2. Renombra la clase (ej: `ProductosResource`)
3. ¡Listo! Los endpoints se registran automáticamente

## 📚 Lista de Ejemplos

| # | Archivo | Descripción | Conceptos |
|---|---------|-------------|-----------|
| 01 | `01_BasicCrudResource.php` | CRUD completo | GET, POST, PUT, DELETE, fetchAll, fetchOne |
| 02 | `02_PublicEndpointsResource.php` | Endpoints públicos | `->public()`, webhooks, health checks |
| 03 | `03_PermissionsResource.php` | Control de permisos | `->requirePermission()`, modo 'any', modo 'all' |
| 04 | `04_MiddlewareResource.php` | Middleware personalizado | `->middleware()`, logging, caché, rate limiting |
| 05 | `05_ValidationResource.php` | Validación de datos | `validateBody()`, tipos, formatos, enum, regex |
| 06 | `06_DatabaseQueriesResource.php` | Consultas a BD | `fetchAll()`, `fetchOne()`, `$this->db`, transacciones |
| 07 | `07_ResponseTypesResource.php` | Tipos de respuesta | `ok()`, `created()`, `badRequest()`, `notFound()`, etc. |
| 08 | `08_TagsDocumentationResource.php` | Documentación | `->tags()`, `->describe()`, `->pathParams()`, `->queryParams()`, `->body()` |
| 09 | `09_DolibarrIntegrationResource.php` | Integración Dolibarr | `loadClass()`, Societe, Facture, User, conf, langs |
| 10 | `10_FileUploadResource.php` | Archivos | Upload Base64, download, adjuntar a objetos |

---

## 📖 Ejemplo 01: CRUD Básico

```php
class ProductosResource extends EasyApiResource
{
    protected $description = 'Gestión de productos';

    protected function registerRoutes(): void
    {
        // GET /tumodulo/productos
        $this->get('/', 'Listar', function($req, $res) {
            $productos = $this->fetchAll("SELECT * FROM llx_product LIMIT 10");
            return $this->ok($res, ['productos' => $productos]);
        });

        // GET /tumodulo/productos/{id}
        $this->get('/{id}', 'Ver', function($req, $res, $args) {
            $id = $args['id'];
            $producto = $this->fetchOne("SELECT * FROM llx_product WHERE rowid = $id");
            return $producto ? $this->ok($res, $producto) : $this->notFound($res);
        });

        // POST /tumodulo/productos
        $this->post('/', 'Crear', function($req, $res) {
            $body = $this->getBody($req);
            // ... crear producto
            return $this->created($res, ['id' => $newId]);
        });
    }
}
```

---

## 📖 Ejemplo 02: Endpoints Públicos

```php
// Endpoint SIN autenticación
$this->get('/ping', 'Health check', function($req, $res) {
    return $this->ok($res, ['status' => 'ok']);
})
->public()  // <-- NO requiere DOLAPIKEY
->describe('Endpoint público');

// Webhook receptor
$this->post('/webhook', 'Webhook', function($req, $res) {
    $body = $this->getBody($req);
    // Procesar webhook...
    return $this->ok($res, ['received' => true]);
})
->public();
```

---

## 📖 Ejemplo 03: Control de Permisos

```php
// Permiso simple
$this->get('/facturas', 'Ver facturas', function($req, $res) {
    // Solo llega aquí si tiene el permiso
})
->requirePermission('facture->lire');

// Cualquiera de los permisos (OR)
$this->get('/documentos', 'Ver documentos', function($req, $res) {
    // ...
})
->requirePermission(['facture->lire', 'commande->lire'], 'any');

// Todos los permisos (AND)
$this->get('/admin', 'Admin', function($req, $res) {
    // ...
})
->requirePermission(['facture->lire', 'facture->creer'], 'all');

// Verificar permiso manualmente
if ($this->hasPermission($req, 'facture->creer')) {
    // Puede crear facturas
}
```

---

## 📖 Ejemplo 04: Middleware

```php
// Middleware de logging
$loggingMiddleware = function($request, $handler) {
    $start = microtime(true);
    $response = $handler->handle($request);
    $duration = microtime(true) - $start;
    return $response->withHeader('X-Response-Time', $duration . 's');
};

$this->get('/datos', 'Con logging', function($req, $res) {
    return $this->ok($res, ['data' => '...']);
})
->middleware($loggingMiddleware);
```

---

## 📖 Ejemplo 05: Validación

```php
$this->post('/usuario', 'Crear usuario', function($req, $res) {
    $validation = $this->validateBody($req, [
        'required' => ['nombre', 'email'],
        'properties' => [
            'nombre' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 100],
            'email' => ['type' => 'string', 'format' => 'email'],
            'edad' => ['type' => 'integer', 'min' => 18, 'max' => 120],
            'pais' => ['type' => 'string', 'enum' => ['ES', 'FR', 'DE']],
            'telefono' => ['type' => 'string', 'pattern' => '^[0-9]{9}$']
        ]
    ]);

    if (!$validation['valid']) {
        return $this->badRequest($res, $validation['error']);
    }

    $data = $validation['data'];
    // ... crear usuario
});
```

### Opciones de Validación

| Opción | Aplica a | Ejemplo |
|--------|----------|---------|
| `type` | Todos | `string`, `integer`, `number`, `boolean`, `array`, `object` |
| `minLength` | string | `'minLength' => 2` |
| `maxLength` | string | `'maxLength' => 100` |
| `min` | integer/number | `'min' => 0` |
| `max` | integer/number | `'max' => 999` |
| `format` | string | `email`, `url`, `date`, `datetime`, `time`, `uuid`, `phone`, `ip` |
| `enum` | string | `'enum' => ['A', 'B', 'C']` |
| `pattern` | string | `'pattern' => '^[A-Z]{2}[0-9]{4}$'` |
| `minItems` | array | `'minItems' => 1` |
| `maxItems` | array | `'maxItems' => 10` |

---

## 📖 Ejemplo 06: Base de Datos

```php
// Múltiples registros
$productos = $this->fetchAll("SELECT * FROM llx_product LIMIT 10");

// Un registro
$producto = $this->fetchOne("SELECT * FROM llx_product WHERE rowid = 1");

// Escapar valores
$search = $this->db->escape($userInput);
$sql = "SELECT * FROM llx_product WHERE label LIKE '%$search%'";

// Transacciones
$this->db->begin();
try {
    $this->db->query("INSERT INTO ...");
    $this->db->commit();
} catch (Exception $e) {
    $this->db->rollback();
}
```

---

## 📖 Ejemplo 07: Respuestas

```php
return $this->ok($res, $data);           // 200 OK
return $this->created($res, $data);      // 201 Created
return $this->noContent($res);           // 204 No Content
return $this->badRequest($res, $msg);    // 400 Bad Request
return $this->unauthorized($res, $msg);  // 401 Unauthorized
return $this->forbidden($res, $msg);     // 403 Forbidden
return $this->notFound($res, $msg);      // 404 Not Found
return $this->error($res, $msg);         // 500 Error
return $this->json($res, $data, 418);    // Custom status
```

---

## 📖 Ejemplo 08: Documentación

```php
$this->get('/items', 'Listar items', function($req, $res) {
    // ...
})
->tags('Items - Listado')
->queryParams([
    'limit' => ['type' => 'integer', 'description' => 'Límite'],
    'search' => ['type' => 'string', 'description' => 'Buscar']
])
->describe('Obtiene un listado paginado de items.');

$this->post('/items', 'Crear item', function($req, $res) {
    // ...
})
->body([
    'required' => ['name'],
    'properties' => [
        'name' => ['type' => 'string', 'description' => 'Nombre'],
        'price' => ['type' => 'number', 'description' => 'Precio']
    ]
])
->describe('Crea un nuevo item.');
```

---

## 📖 Ejemplo 09: Integración Dolibarr

```php
// Cargar clase
$this->loadClass('/societe/class/societe.class.php');

// Usar clase
$societe = new Societe($this->db);
$societe->fetch($id);

// Usuario autenticado
$user = $this->getUser($req);

// Configuración global
$moneda = $this->conf->currency;

// Traducciones
$this->langs->load('main');
$texto = $this->langs->trans('Invoice');
```

---

## 📖 Ejemplo 10: Archivos

```php
// Subir (Base64)
$this->post('/upload', function($req, $res) {
    $body = $this->getBody($req);
    $content = base64_decode($body['content']);
    file_put_contents($path, $content);
});

// Descargar
$this->get('/download', function($req, $res) {
    $content = file_get_contents($path);
    return $this->ok($res, [
        'filename' => basename($path),
        'content' => base64_encode($content)
    ]);
});
```

---

## 🎯 Estructura de un Resource

```php
<?php
use EasyApi\EasyApiResource;

class MiResource extends EasyApiResource
{
    // Descripción para Swagger
    protected $description = 'Mi API';

    // ¿Públicos por defecto?
    protected $publicByDefault = false;

    // Permiso por defecto (opcional)
    protected $defaultPermission = null;

    // Inicialización (opcional)
    protected function init(): void
    {
        $this->loadClass('/mi/clase.php');
    }

    // Registrar rutas (OBLIGATORIO)
    protected function registerRoutes(): void
    {
        $this->get('/', 'Listar', function($req, $res) {
            return $this->ok($res, []);
        });
    }
}
```

---

## 📂 Ubicación de archivos

```
custom/
└── tumodulo/
    └── eapi/
        ├── MiResource.php          → /tumodulo/mi/*
        ├── OtroResource.php        → /tumodulo/otro/*
        └── subcarpeta/
            └── SubResource.php     → /tumodulo/subcarpeta/sub/*
```

---

## ❓ FAQ

**¿Cómo se calculan las rutas?**
- Módulo: nombre de la carpeta en `custom/`
- Resource: nombre del archivo sin "Resource.php", en kebab-case
- Ejemplo: `custom/ventas/eapi/MisProductosResource.php` → `/ventas/mis-productos/*`

**¿Puedo tener múltiples Resources?**
- Sí, crea múltiples archivos `*Resource.php` en la carpeta `eapi/`

**¿Cómo depuro?**
- Usa `$this->log('mensaje', LOG_DEBUG)` para escribir en el log de Dolibarr
