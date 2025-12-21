<?php
/**
 * =============================================================================
 * EJEMPLO 01: CRUD BÁSICO
 * =============================================================================
 *
 * Este es el ejemplo más simple de un Resource EAPI.
 * Implementa las operaciones CRUD básicas: Listar, Ver, Crear, Actualizar, Eliminar.
 *
 * UBICACIÓN: custom/tumodulo/eapi/ProductosResource.php
 * ENDPOINTS GENERADOS:
 *   - GET    /tumodulo/productos         → Listar todos
 *   - GET    /tumodulo/productos/{id}    → Ver uno
 *   - POST   /tumodulo/productos         → Crear
 *   - PUT    /tumodulo/productos/{id}    → Actualizar completo
 *   - DELETE /tumodulo/productos/{id}    → Eliminar
 *
 * =============================================================================
 */

use EasyApi\EasyApiResource;

class ProductosResource extends EasyApiResource
{
    /**
     * Descripción que aparece en Swagger
     */
    protected $description = 'Gestión de productos';

    /**
     * Registrar las rutas del recurso
     */
    protected function registerRoutes(): void
    {
        // =====================================================================
        // GET / - Listar todos los productos
        // =====================================================================
        $this->get('/', 'Listar productos', function ($req, $res) {
            // Obtener parámetros de paginación del query string
            $limit = (int) $this->query($req, 'limit', 10);
            $offset = (int) $this->query($req, 'offset', 0);

            // Consulta a la base de datos
            $sql = "SELECT rowid, ref, label, price
                    FROM " . MAIN_DB_PREFIX . "product
                    WHERE entity = " . (int) $this->conf->entity . "
                    LIMIT $limit OFFSET $offset";

            $productos = $this->fetchAll($sql);

            // Respuesta exitosa con ok()
            return $this->ok($res, array(
                'productos' => $productos,
                'pagination' => array(
                    'limit' => $limit,
                    'offset' => $offset
                )
            ));
        })
        ->queryParams(array(
            'limit' => array('type' => 'integer', 'description' => 'Cantidad de resultados (default: 10)'),
            'offset' => array('type' => 'integer', 'description' => 'Offset para paginación')
        ))
        ->describe('Obtiene un listado paginado de productos.');


        // =====================================================================
        // GET /{id} - Ver un producto específico
        // =====================================================================
        $this->get('/{id}', 'Ver producto', function ($req, $res, $args) {
            $id = (int) $args['id'];

            // Consulta a la base de datos
            $sql = "SELECT rowid, ref, label, description, price, tva_tx, stock
                    FROM " . MAIN_DB_PREFIX . "product
                    WHERE rowid = $id AND entity = " . (int) $this->conf->entity;

            $producto = $this->fetchOne($sql);

            // Si no existe, devolver 404
            if (!$producto) {
                return $this->notFound($res, "Producto #$id no encontrado");
            }

            return $this->ok($res, $producto);
        })
        ->pathParams(array(
            'id' => array('type' => 'integer', 'description' => 'ID del producto')
        ))
        ->describe('Obtiene los detalles de un producto por su ID.');


        // =====================================================================
        // POST / - Crear un nuevo producto
        // =====================================================================
        $this->post('/', 'Crear producto', function ($req, $res) {
            // Definir schema de validación
            $schema = array(
                'required' => array('ref', 'label'),
                'properties' => array(
                    'ref' => array('type' => 'string', 'minLength' => 1, 'maxLength' => 128),
                    'label' => array('type' => 'string', 'minLength' => 1, 'maxLength' => 255),
                    'description' => array('type' => 'string'),
                    'price' => array('type' => 'number', 'min' => 0),
                    'tva_tx' => array('type' => 'number', 'min' => 0, 'max' => 100)
                )
            );

            // Validar body
            $validation = $this->validateBody($req, $schema);
            if (!$validation['valid']) {
                return $this->badRequest($res, $validation['error']);
            }

            $data = $validation['data'];

            // Cargar clase Product de Dolibarr
            $this->loadClass('/product/class/product.class.php');

            $product = new \Product($this->db);
            $product->ref = $data['ref'];
            $product->label = $data['label'];
            $product->description = isset($data['description']) ? $data['description'] : '';
            $product->price = isset($data['price']) ? $data['price'] : 0;
            $product->tva_tx = isset($data['tva_tx']) ? $data['tva_tx'] : 21;
            $product->status = 1; // Activo

            $result = $product->create($this->getUser($req));

            if ($result < 0) {
                return $this->error($res, 'Error al crear producto: ' . $product->error);
            }

            // Respuesta 201 Created
            return $this->created($res, array(
                'id' => $result,
                'ref' => $product->ref,
                'message' => 'Producto creado correctamente'
            ));
        })
        ->body(array(
            'required' => array('ref', 'label'),
            'properties' => array(
                'ref' => array('type' => 'string', 'description' => 'Referencia única del producto'),
                'label' => array('type' => 'string', 'description' => 'Nombre del producto'),
                'description' => array('type' => 'string', 'description' => 'Descripción'),
                'price' => array('type' => 'number', 'description' => 'Precio sin IVA'),
                'tva_tx' => array('type' => 'number', 'description' => 'Porcentaje de IVA')
            )
        ))
        ->describe('Crea un nuevo producto en el sistema.');


        // =====================================================================
        // PUT /{id} - Actualizar un producto completo
        // =====================================================================
        $this->put('/{id}', 'Actualizar producto', function ($req, $res, $args) {
            $id = (int) $args['id'];

            // Validar body
            $schema = array(
                'required' => array('ref', 'label'),
                'properties' => array(
                    'ref' => array('type' => 'string'),
                    'label' => array('type' => 'string'),
                    'description' => array('type' => 'string'),
                    'price' => array('type' => 'number', 'min' => 0)
                )
            );

            $validation = $this->validateBody($req, $schema);
            if (!$validation['valid']) {
                return $this->badRequest($res, $validation['error']);
            }

            $data = $validation['data'];

            // Cargar y buscar producto
            $this->loadClass('/product/class/product.class.php');
            $product = new \Product($this->db);

            if ($product->fetch($id) <= 0) {
                return $this->notFound($res, "Producto #$id no encontrado");
            }

            // Actualizar campos
            $product->ref = $data['ref'];
            $product->label = $data['label'];
            $product->description = isset($data['description']) ? $data['description'] : $product->description;
            $product->price = isset($data['price']) ? $data['price'] : $product->price;

            $result = $product->update($id, $this->getUser($req));

            if ($result < 0) {
                return $this->error($res, 'Error al actualizar: ' . $product->error);
            }

            return $this->ok($res, array(
                'id' => $id,
                'message' => 'Producto actualizado correctamente'
            ));
        })
        ->pathParams(array(
            'id' => array('type' => 'integer', 'description' => 'ID del producto')
        ))
        ->body(array(
            'required' => array('ref', 'label'),
            'properties' => array(
                'ref' => array('type' => 'string'),
                'label' => array('type' => 'string'),
                'description' => array('type' => 'string'),
                'price' => array('type' => 'number')
            )
        ))
        ->describe('Actualiza todos los campos de un producto.');


        // =====================================================================
        // DELETE /{id} - Eliminar un producto
        // =====================================================================
        $this->delete('/{id}', 'Eliminar producto', function ($req, $res, $args) {
            $id = (int) $args['id'];

            $this->loadClass('/product/class/product.class.php');
            $product = new \Product($this->db);

            if ($product->fetch($id) <= 0) {
                return $this->notFound($res, "Producto #$id no encontrado");
            }

            $result = $product->delete($this->getUser($req));

            if ($result < 0) {
                return $this->error($res, 'Error al eliminar: ' . $product->error);
            }

            // 204 No Content - Eliminación exitosa sin body
            return $this->noContent($res);
        })
        ->pathParams(array(
            'id' => array('type' => 'integer', 'description' => 'ID del producto a eliminar')
        ))
        ->describe('Elimina un producto del sistema.');
    }
}
