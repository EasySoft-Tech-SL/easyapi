<?php
/**
 * =============================================================================
 * EJEMPLO 02: CRUD COMPLETO CON HOOKS
 * =============================================================================
 *
 * Implementación de un CRUD completo usando el sistema de hooks.
 *
 * ENDPOINTS:
 *   - GET    /productos         → Listar
 *   - GET    /productos/{id}    → Ver uno
 *   - POST   /productos         → Crear
 *   - PUT    /productos/{id}    → Actualizar
 *   - DELETE /productos/{id}    → Eliminar
 *
 * =============================================================================
 */

class ActionsProductosApi
{
    public $db;
    public $error = '';
    public $errors = array();

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Hook para registrar rutas CRUD
     */
    public function easyApiRegisterRoutes($parameters, &$object, &$action)
    {
        global $conf, $user;

        $app = $parameters['app'];
        $api = $object;
        $self = $this;

        // =====================================================================
        // GET /productos - Listar todos
        // =====================================================================
        $app->get('/productos', function ($request, $response) use ($self, $conf) {
            $params = $request->getQueryParams();
            $limit = isset($params['limit']) ? min((int) $params['limit'], 100) : 20;
            $offset = isset($params['offset']) ? (int) $params['offset'] : 0;
            $search = isset($params['search']) ? $self->db->escape($params['search']) : '';

            // Construir WHERE
            $where = "entity = " . (int) $conf->entity;
            if (!empty($search)) {
                $where .= " AND (ref LIKE '%$search%' OR label LIKE '%$search%')";
            }

            // Contar total
            $sqlCount = "SELECT COUNT(*) as total FROM " . MAIN_DB_PREFIX . "product WHERE $where";
            $resCount = $self->db->query($sqlCount);
            $total = 0;
            if ($resCount) {
                $objCount = $self->db->fetch_object($resCount);
                $total = (int) $objCount->total;
            }

            // Obtener registros
            $sql = "SELECT rowid, ref, label, price, tva_tx, stock
                    FROM " . MAIN_DB_PREFIX . "product
                    WHERE $where
                    ORDER BY ref ASC
                    LIMIT $limit OFFSET $offset";

            $productos = array();
            $resql = $self->db->query($sql);
            if ($resql) {
                while ($obj = $self->db->fetch_object($resql)) {
                    $productos[] = array(
                        'id' => (int) $obj->rowid,
                        'ref' => $obj->ref,
                        'label' => $obj->label,
                        'price' => (float) $obj->price,
                        'tva_tx' => (float) $obj->tva_tx,
                        'stock' => (float) $obj->stock
                    );
                }
            }

            $data = array(
                'success' => true,
                'data' => array(
                    'productos' => $productos,
                    'pagination' => array(
                        'total' => $total,
                        'limit' => $limit,
                        'offset' => $offset,
                        'pages' => ceil($total / $limit)
                    )
                )
            );

            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($data));
            return $response;
        });

        // =====================================================================
        // GET /productos/{id} - Ver uno
        // =====================================================================
        $app->get('/productos/{id}', function ($request, $response, $args) use ($self) {
            $id = (int) $args['id'];

            $sql = "SELECT rowid, ref, label, description, price, price_ttc,
                           tva_tx, stock, tobuy, tosell, fk_product_type
                    FROM " . MAIN_DB_PREFIX . "product
                    WHERE rowid = $id";

            $resql = $self->db->query($sql);

            if ($resql && $self->db->num_rows($resql) > 0) {
                $obj = $self->db->fetch_object($resql);
                $data = array(
                    'success' => true,
                    'data' => array(
                        'id' => (int) $obj->rowid,
                        'ref' => $obj->ref,
                        'label' => $obj->label,
                        'description' => $obj->description,
                        'price' => (float) $obj->price,
                        'price_ttc' => (float) $obj->price_ttc,
                        'tva_tx' => (float) $obj->tva_tx,
                        'stock' => (float) $obj->stock,
                        'tobuy' => (bool) $obj->tobuy,
                        'tosell' => (bool) $obj->tosell,
                        'type' => $obj->fk_product_type == 1 ? 'service' : 'product'
                    )
                );
            } else {
                $response = $response->withStatus(404);
                $data = array(
                    'success' => false,
                    'error' => array(
                        'code' => 404,
                        'message' => "Producto #$id no encontrado"
                    )
                );
            }

            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($data));
            return $response;
        });

        // =====================================================================
        // POST /productos - Crear
        // =====================================================================
        $app->post('/productos', function ($request, $response) use ($self, $conf) {
            $body = json_decode((string) $request->getBody(), true);

            // Validación
            $errors = array();
            if (empty($body['ref'])) {
                $errors[] = 'El campo "ref" es obligatorio';
            }
            if (empty($body['label'])) {
                $errors[] = 'El campo "label" es obligatorio';
            }

            if (!empty($errors)) {
                $response = $response->withStatus(400);
                $data = array(
                    'success' => false,
                    'error' => array(
                        'code' => 400,
                        'message' => implode('. ', $errors)
                    )
                );
            } else {
                // Verificar ref único
                $checkSql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "product
                             WHERE ref = '" . $self->db->escape($body['ref']) . "'";
                $checkRes = $self->db->query($checkSql);
                if ($checkRes && $self->db->num_rows($checkRes) > 0) {
                    $response = $response->withStatus(400);
                    $data = array(
                        'success' => false,
                        'error' => array(
                            'code' => 400,
                            'message' => 'Ya existe un producto con esa referencia'
                        )
                    );
                } else {
                    // Insertar
                    $sql = "INSERT INTO " . MAIN_DB_PREFIX . "product
                            (ref, label, description, price, tva_tx, tosell, tobuy, entity, datec)
                            VALUES (
                                '" . $self->db->escape($body['ref']) . "',
                                '" . $self->db->escape($body['label']) . "',
                                '" . $self->db->escape(isset($body['description']) ? $body['description'] : '') . "',
                                " . (float) (isset($body['price']) ? $body['price'] : 0) . ",
                                " . (float) (isset($body['tva_tx']) ? $body['tva_tx'] : 21) . ",
                                1,
                                1,
                                " . (int) $conf->entity . ",
                                NOW()
                            )";

                    $result = $self->db->query($sql);

                    if ($result) {
                        $newId = $self->db->last_insert_id(MAIN_DB_PREFIX . 'product');
                        $response = $response->withStatus(201);
                        $data = array(
                            'success' => true,
                            'data' => array(
                                'id' => (int) $newId,
                                'ref' => $body['ref'],
                                'message' => 'Producto creado correctamente'
                            )
                        );
                    } else {
                        $response = $response->withStatus(500);
                        $data = array(
                            'success' => false,
                            'error' => array(
                                'code' => 500,
                                'message' => 'Error al crear el producto'
                            )
                        );
                    }
                }
            }

            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($data));
            return $response;
        });

        // =====================================================================
        // PUT /productos/{id} - Actualizar
        // =====================================================================
        $app->put('/productos/{id}', function ($request, $response, $args) use ($self) {
            $id = (int) $args['id'];
            $body = json_decode((string) $request->getBody(), true);

            // Verificar que existe
            $checkSql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "product WHERE rowid = $id";
            $checkRes = $self->db->query($checkSql);

            if (!$checkRes || $self->db->num_rows($checkRes) == 0) {
                $response = $response->withStatus(404);
                $data = array(
                    'success' => false,
                    'error' => array(
                        'code' => 404,
                        'message' => "Producto #$id no encontrado"
                    )
                );
            } else {
                // Construir UPDATE
                $updates = array();
                if (isset($body['ref'])) {
                    $updates[] = "ref = '" . $self->db->escape($body['ref']) . "'";
                }
                if (isset($body['label'])) {
                    $updates[] = "label = '" . $self->db->escape($body['label']) . "'";
                }
                if (isset($body['description'])) {
                    $updates[] = "description = '" . $self->db->escape($body['description']) . "'";
                }
                if (isset($body['price'])) {
                    $updates[] = "price = " . (float) $body['price'];
                }
                if (isset($body['tva_tx'])) {
                    $updates[] = "tva_tx = " . (float) $body['tva_tx'];
                }

                if (empty($updates)) {
                    $response = $response->withStatus(400);
                    $data = array(
                        'success' => false,
                        'error' => array(
                            'code' => 400,
                            'message' => 'No hay campos para actualizar'
                        )
                    );
                } else {
                    $updates[] = "tms = NOW()";
                    $sql = "UPDATE " . MAIN_DB_PREFIX . "product
                            SET " . implode(', ', $updates) . "
                            WHERE rowid = $id";

                    $result = $self->db->query($sql);

                    if ($result) {
                        $data = array(
                            'success' => true,
                            'data' => array(
                                'id' => $id,
                                'message' => 'Producto actualizado correctamente'
                            )
                        );
                    } else {
                        $response = $response->withStatus(500);
                        $data = array(
                            'success' => false,
                            'error' => array(
                                'code' => 500,
                                'message' => 'Error al actualizar el producto'
                            )
                        );
                    }
                }
            }

            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($data));
            return $response;
        });

        // =====================================================================
        // DELETE /productos/{id} - Eliminar
        // =====================================================================
        $app->delete('/productos/{id}', function ($request, $response, $args) use ($self) {
            $id = (int) $args['id'];

            // Verificar que existe
            $checkSql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "product WHERE rowid = $id";
            $checkRes = $self->db->query($checkSql);

            if (!$checkRes || $self->db->num_rows($checkRes) == 0) {
                $response = $response->withStatus(404);
                $data = array(
                    'success' => false,
                    'error' => array(
                        'code' => 404,
                        'message' => "Producto #$id no encontrado"
                    )
                );
            } else {
                $sql = "DELETE FROM " . MAIN_DB_PREFIX . "product WHERE rowid = $id";
                $result = $self->db->query($sql);

                if ($result) {
                    $response = $response->withStatus(204);
                    return $response; // No Content
                } else {
                    $response = $response->withStatus(500);
                    $data = array(
                        'success' => false,
                        'error' => array(
                            'code' => 500,
                            'message' => 'Error al eliminar el producto'
                        )
                    );
                }
            }

            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($data));
            return $response;
        });

        // =====================================================================
        // Documentación OpenAPI
        // =====================================================================
        $api->hookRoutes[] = array(
            'methods' => array('GET'),
            'path' => '/productos',
            'summary' => '🪝 Listar productos',
            'tags' => array('Productos (Hook)'),
            'security' => array(array('api_key' => array())),
            'parameters' => array(
                array('name' => 'limit', 'in' => 'query', 'schema' => array('type' => 'integer')),
                array('name' => 'offset', 'in' => 'query', 'schema' => array('type' => 'integer')),
                array('name' => 'search', 'in' => 'query', 'schema' => array('type' => 'string'))
            )
        );

        $api->hookRoutes[] = array(
            'methods' => array('GET'),
            'path' => '/productos/{id}',
            'summary' => '🪝 Ver producto',
            'tags' => array('Productos (Hook)'),
            'security' => array(array('api_key' => array())),
            'parameters' => array(
                array('name' => 'id', 'in' => 'path', 'required' => true, 'schema' => array('type' => 'integer'))
            )
        );

        $api->hookRoutes[] = array(
            'methods' => array('POST'),
            'path' => '/productos',
            'summary' => '🪝 Crear producto',
            'tags' => array('Productos (Hook)'),
            'security' => array(array('api_key' => array())),
            'requestBody' => array(
                'required' => true,
                'content' => array(
                    'application/json' => array(
                        'schema' => array(
                            'type' => 'object',
                            'required' => array('ref', 'label'),
                            'properties' => array(
                                'ref' => array('type' => 'string'),
                                'label' => array('type' => 'string'),
                                'description' => array('type' => 'string'),
                                'price' => array('type' => 'number'),
                                'tva_tx' => array('type' => 'number')
                            )
                        )
                    )
                )
            )
        );

        $api->hookRoutes[] = array(
            'methods' => array('PUT'),
            'path' => '/productos/{id}',
            'summary' => '🪝 Actualizar producto',
            'tags' => array('Productos (Hook)'),
            'security' => array(array('api_key' => array())),
            'parameters' => array(
                array('name' => 'id', 'in' => 'path', 'required' => true, 'schema' => array('type' => 'integer'))
            ),
            'requestBody' => array(
                'content' => array(
                    'application/json' => array(
                        'schema' => array(
                            'type' => 'object',
                            'properties' => array(
                                'ref' => array('type' => 'string'),
                                'label' => array('type' => 'string'),
                                'price' => array('type' => 'number')
                            )
                        )
                    )
                )
            )
        );

        $api->hookRoutes[] = array(
            'methods' => array('DELETE'),
            'path' => '/productos/{id}',
            'summary' => '🪝 Eliminar producto',
            'tags' => array('Productos (Hook)'),
            'security' => array(array('api_key' => array())),
            'parameters' => array(
                array('name' => 'id', 'in' => 'path', 'required' => true, 'schema' => array('type' => 'integer'))
            )
        );

        return 0;
    }
}
