# 🚀 Guía EAPI - Crea APIs en Minutos

## ¿Qué es EAPI?

EAPI (Easy API) es un sistema súper simple para crear endpoints REST en tus módulos de Dolibarr. **En menos de 5 minutos** tendrás tu primera API funcionando.

---

## Inicio Rápido (3 pasos)

### Paso 1: Crear la carpeta `eapi`

En tu módulo, crea una carpeta llamada `eapi`:

```
custom/
└── tumodulo/
    └── eapi/           <-- ¡Aquí van tus recursos!
        └── ProductosResource.php
```

### Paso 2: Crear tu primer Resource

```php
<?php
// custom/tumodulo/eapi/ProductosResource.php

use EasyApi\EasyApiResource;

class ProductosResource extends EasyApiResource
{
    protected $description = 'Gestión de productos';

    protected function registerRoutes(): void
    {
        // GET /tumodulo/productos
        $this->get('/', 'Listar productos', function ($req, $res) {
            return $this->ok($res, ['productos' => []]);
        });

        // GET /tumodulo/productos/{id}
        $this->get('/{id}', 'Ver producto', function ($req, $res, $args) {
            return $this->ok($res, ['id' => $args['id']]);
        });

        // POST /tumodulo/productos
        $this->post('/', 'Crear producto', function ($req, $res) {
            $data = $this->getBody($req);
            return $this->created($res, ['id' => 123]);
        });
    }
}
```

### Paso 3: ¡Listo! 

Accede a `/custom/easyapi/easyapiindex.php/docs` para ver tu API documentada automáticamente.

---

## Métodos HTTP

```php
$this->get('/ruta', 'Descripción', function($req, $res) { });
$this->post('/ruta', 'Descripción', function($req, $res) { });
$this->put('/ruta', 'Descripción', function($req, $res) { });
$this->patch('/ruta', 'Descripción', function($req, $res) { });
$this->delete('/ruta', 'Descripción', function($req, $res) { });
```

---

## Respuestas

```php
// ✅ Éxito (200)
return $this->ok($res, ['data' => 'valor']);

// ✅ Creado (201)
return $this->created($res, ['id' => 123]);

// ✅ Sin contenido (204)
return $this->noContent($res);

// ❌ Error de cliente (400)
return $this->badRequest($res, 'Campo requerido faltante');

// ❌ No autorizado (401)
return $this->unauthorized($res, 'Token inválido');

// ❌ Prohibido (403)
return $this->forbidden($res, 'Sin permiso');

// ❌ No encontrado (404)
return $this->notFound($res, 'Producto no existe');

// ❌ Error interno (500)
return $this->error($res, 'Error de base de datos');
```

---

## Modificadores de Ruta

### Ruta Pública (sin autenticación)

```php
$this->get('/info', 'Info pública', function($req, $res) {
    return $this->ok($res, ['version' => '1.0']);
})->public();
```

### Requerir Permisos

```php
// Un permiso específico
$this->post('/', 'Crear', function($req, $res) {
    // ...
})->requirePermission('facture->creer');

// TODOS los permisos requeridos
$this->delete('/{id}', 'Eliminar', function($req, $res) {
    // ...
})->requirePermission(['facture->supprimer', 'facture->lire'], 'all');

// AL MENOS UNO de los permisos
$this->get('/report', 'Reporte', function($req, $res) {
    // ...
})->requirePermission(['facture->lire', 'commande->lire'], 'any');
```

### Documentación de Parámetros

```php
// Parámetros de path
$this->get('/{id}', 'Ver producto', function($req, $res, $args) {
    $id = $args['id'];
    // ...
})->pathParams([
    'id' => ['type' => 'integer', 'description' => 'ID del producto']
]);

// Parámetros de query
$this->get('/', 'Listar', function($req, $res) {
    $limit = $this->query($req, 'limit', 20);
    $page = $this->query($req, 'page', 1);
    // ...
})->queryParams([
    'limit' => ['type' => 'integer', 'description' => 'Límite de resultados'],
    'page' => ['type' => 'integer', 'description' => 'Número de página']
]);

// Body de la petición
$this->post('/', 'Crear', function($req, $res) {
    $body = $this->getBody($req);
    // ...
})->body([
    'required' => ['nombre', 'precio'],
    'properties' => [
        'nombre' => ['type' => 'string', 'description' => 'Nombre del producto'],
        'precio' => ['type' => 'number', 'description' => 'Precio de venta'],
        'descripcion' => ['type' => 'string']
    ]
]);
```

---

## Helpers Disponibles

```php
// Obtener body JSON de la petición
$body = $this->getBody($req);

// Obtener parámetro de query string
$limit = $this->query($req, 'limit', 20);  // 20 es el default

// Obtener usuario autenticado
$user = $this->getUser($req);
if ($user) {
    echo $user->login;
}

// Verificar permiso manualmente
if ($this->hasPermission($req, 'facture->creer')) {
    // Puede crear facturas
}

// Ejecutar SQL
$rows = $this->fetchAll("SELECT * FROM llx_product LIMIT 10");
$row = $this->fetchOne("SELECT * FROM llx_product WHERE rowid = 1");

// Cargar clase de Dolibarr
$this->loadClass('/product/class/product.class.php');
$product = new \Product($this->db);

// Log
$this->log('Algo importante pasó', LOG_INFO);
```

---

## Organización con Subcarpetas

Puedes organizar tus recursos en subcarpetas:

```
custom/tumodulo/eapi/
├── ProductosResource.php           → /tumodulo/productos
├── ClientesResource.php            → /tumodulo/clientes
└── vehiculos/
    ├── VehiculosResource.php       → /tumodulo/vehiculos
    └── historial/
        └── HistorialResource.php   → /tumodulo/vehiculos/historial
```

Las URLs se calculan automáticamente basándose en:
1. Nombre del módulo
2. Estructura de carpetas
3. Nombre del recurso

---

## Propiedades Configurables

```php
class MiResource extends EasyApiResource
{
    // Descripción para documentación OpenAPI
    protected $description = 'Mi descripción';

    // ¿Todas las rutas son públicas por defecto?
    protected $publicByDefault = false;

    // Permiso requerido por defecto para todas las rutas
    protected $defaultPermission = 'mimodulo->lire';

    protected function registerRoutes(): void
    {
        // ...
    }
}
```

---

## Propiedades Disponibles en tu Resource

```php
protected function registerRoutes(): void
{
    // Acceso a base de datos Dolibarr
    $this->db->query("SELECT ...");

    // Nombre del módulo
    echo $this->moduleName;  // "tumodulo"

    // Nombre del recurso
    echo $this->resourceName;  // "Productos"

    // Prefijo de ruta calculado
    echo $this->prefix;  // "/tumodulo/productos"
}
```

---

## Ejemplo Completo: CRUD de Productos

```php
<?php
use EasyApi\EasyApiResource;

class ProductosResource extends EasyApiResource
{
    protected $description = 'CRUD completo de productos';
    protected $defaultPermission = 'produit->lire';

    protected function init(): void
    {
        // Cargar la clase de productos de Dolibarr
        $this->loadClass('/product/class/product.class.php');
    }

    protected function registerRoutes(): void
    {
        // LISTAR
        $this->get('/', 'Listar productos', function ($req, $res) {
            $limit = (int) $this->query($req, 'limit', 50);

            $sql = "SELECT rowid, ref, label, price FROM " . MAIN_DB_PREFIX . "product LIMIT " . $limit;
            $productos = $this->fetchAll($sql);

            return $this->ok($res, ['productos' => $productos]);
        })->queryParams([
            'limit' => ['type' => 'integer', 'description' => 'Máximo de resultados']
        ]);

        // VER UNO
        $this->get('/{id}', 'Ver producto', function ($req, $res, $args) {
            $product = new \Product($this->db);

            if ($product->fetch($args['id']) <= 0) {
                return $this->notFound($res, 'Producto no encontrado');
            }

            return $this->ok($res, [
                'id' => $product->id,
                'ref' => $product->ref,
                'label' => $product->label,
                'price' => $product->price
            ]);
        });

        // CREAR
        $this->post('/', 'Crear producto', function ($req, $res) {
            $body = $this->getBody($req);
            $user = $this->getUser($req);

            $product = new \Product($this->db);
            $product->ref = $body['ref'];
            $product->label = $body['label'];
            $product->price = $body['price'] ?? 0;

            $result = $product->create($user);

            if ($result < 0) {
                return $this->badRequest($res, $product->error);
            }

            return $this->created($res, ['id' => $result]);
        })->requirePermission('produit->creer')
          ->body([
            'required' => ['ref', 'label'],
            'properties' => [
                'ref' => ['type' => 'string'],
                'label' => ['type' => 'string'],
                'price' => ['type' => 'number']
            ]
        ]);

        // ACTUALIZAR
        $this->put('/{id}', 'Actualizar producto', function ($req, $res, $args) {
            $body = $this->getBody($req);
            $user = $this->getUser($req);

            $product = new \Product($this->db);
            if ($product->fetch($args['id']) <= 0) {
                return $this->notFound($res);
            }

            if (isset($body['label'])) $product->label = $body['label'];
            if (isset($body['price'])) $product->price = $body['price'];

            $product->update($product->id, $user);

            return $this->ok($res, ['updated' => true]);
        })->requirePermission('produit->creer');

        // ELIMINAR
        $this->delete('/{id}', 'Eliminar producto', function ($req, $res, $args) {
            $user = $this->getUser($req);
            $product = new \Product($this->db);

            if ($product->fetch($args['id']) <= 0) {
                return $this->notFound($res);
            }

            $product->delete($user);

            return $this->noContent($res);
        })->requirePermission('produit->supprimer');
    }
}
```

---

## Convenciones de Nombres

| Elemento | Formato | Ejemplo |
|----------|---------|---------|
| Carpetas | snake_case | `ordenes_trabajo/` |
| Clases | PascalCase + Resource | `OrdenesTrabajoResource.php` |
| URLs | kebab-case (automático) | `/ordenes-trabajo` |

---

## Carpetas Especiales (Ignoradas)

Estas carpetas son ignoradas por el auto-discovery:

- `_middleware/` - Para middleware compartido
- `_config/` - Para configuración
- `_shared/` - Para código compartido

---

## Tips

1. **Siempre usa `$this->getUser($req)`** para obtener el usuario autenticado
2. **Los permisos usan formato Dolibarr**: `modulo->permiso` (ej: `facture->lire`)
3. **La documentación OpenAPI se genera automáticamente** - accede a `/docs`
4. **Los errores se manejan automáticamente** - solo lanza excepciones
5. **Usa `->public()`** solo para endpoints que realmente deben ser públicos

---

¿Preguntas? Revisa los ejemplos en `custom/easyapi/eapi/`
