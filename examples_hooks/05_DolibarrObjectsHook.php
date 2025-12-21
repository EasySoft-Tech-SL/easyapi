<?php
/**
 * =============================================================================
 * EJEMPLO 05: OBJETOS DOLIBARR EN HOOKS
 * =============================================================================
 *
 * Demuestra cómo usar las clases nativas de Dolibarr dentro de hooks.
 * - Societe (clientes/proveedores)
 * - Facture (facturas)
 * - Product (productos)
 * - User (usuarios)
 * - Commande (pedidos)
 *
 * Recuerda que en hooks tienes acceso a $this->db
 *
 * =============================================================================
 */

class ActionsDolibarrObjectsApi
{
    public $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Valida el body de la petición
     * (Reimplementamos para no depender del ResourceHelper)
     */
    private function validateBody($body, $schema)
    {
        $errors = array();
        if (empty($body)) {
            return array('error' => 'El cuerpo de la petición está vacío');
        }

        foreach ($schema as $field => $rules) {
            $value = isset($body[$field]) ? $body[$field] : null;

            if (!empty($rules['required']) && ($value === null || $value === '')) {
                $errors[] = "El campo '{$field}' es requerido";
                continue;
            }

            if ($value !== null && !empty($rules['type'])) {
                $valid = true;
                switch ($rules['type']) {
                    case 'string':
                        $valid = is_string($value);
                        break;
                    case 'integer':
                        $valid = is_numeric($value);
                        break;
                    case 'number':
                        $valid = is_numeric($value);
                        break;
                    case 'email':
                        $valid = filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
                        break;
                }
                if (!$valid) {
                    $errors[] = "El campo '{$field}' debe ser de tipo {$rules['type']}";
                }
            }

            if ($value !== null && !empty($rules['minLength']) && strlen($value) < $rules['minLength']) {
                $errors[] = "El campo '{$field}' debe tener al menos {$rules['minLength']} caracteres";
            }
        }

        return empty($errors) ? null : array('errors' => $errors);
    }

    public function easyApiRegisterRoutes($parameters, &$object, &$action)
    {
        $app = $parameters['app'];
        $api = $object;
        $self = $this;

        // =====================================================================
        // TERCEROS (SOCIETE)
        // =====================================================================

        // Listar terceros
        $app->get('/doli/terceros', function ($request, $response) use ($self) {
            global $conf, $user;

            // Cargar clase Societe
            require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

            $params = $request->getQueryParams();
            $limit = isset($params['limit']) ? min((int) $params['limit'], 100) : 25;
            $page = isset($params['page']) ? max(1, (int) $params['page']) : 1;
            $offset = ($page - 1) * $limit;
            $tipo = isset($params['tipo']) ? $params['tipo'] : ''; // cliente, proveedor, ambos

            // Construir WHERE
            $where = 'e.entity IN (' . getEntity('societe') . ')';
            if ($tipo === 'cliente') {
                $where .= ' AND e.client > 0';
            } elseif ($tipo === 'proveedor') {
                $where .= ' AND e.fournisseur = 1';
            }

            // Contar total
            $sql_count = "SELECT COUNT(*) as total FROM " . MAIN_DB_PREFIX . "societe e WHERE " . $where;
            $resql = $self->db->query($sql_count);
            $total = $self->db->fetch_object($resql)->total;

            // Obtener registros
            $sql = "SELECT e.rowid FROM " . MAIN_DB_PREFIX . "societe e WHERE " . $where;
            $sql .= " ORDER BY e.nom ASC";
            $sql .= " LIMIT " . (int) $limit . " OFFSET " . (int) $offset;

            $terceros = array();
            $resql = $self->db->query($sql);

            while ($obj = $self->db->fetch_object($resql)) {
                $tercero = new Societe($self->db);
                if ($tercero->fetch($obj->rowid) > 0) {
                    $terceros[] = array(
                        'id' => (int) $tercero->id,
                        'ref' => $tercero->name,
                        'name' => $tercero->name,
                        'name_alias' => $tercero->name_alias,
                        'email' => $tercero->email,
                        'phone' => $tercero->phone,
                        'address' => $tercero->address,
                        'zip' => $tercero->zip,
                        'town' => $tercero->town,
                        'country_code' => $tercero->country_code,
                        'client' => (int) $tercero->client,
                        'fournisseur' => (int) $tercero->fournisseur,
                        'status' => (int) $tercero->status,
                        'date_creation' => $tercero->date_creation
                    );
                }
            }

            $data = array(
                'success' => true,
                'data' => $terceros,
                'pagination' => array(
                    'page' => $page,
                    'limit' => $limit,
                    'total' => (int) $total,
                    'pages' => ceil($total / $limit)
                )
            );

            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($data));
            return $response;
        });

        $api->hookRoutes[] = array(
            'methods' => array('GET'),
            'path' => '/doli/terceros',
            'summary' => '🪝 Listar terceros',
            'description' => 'Lista clientes y proveedores usando la clase Societe de Dolibarr',
            'tags' => array('Objetos Dolibarr (Hook)'),
            'security' => array(array('api_key' => array())),
            'parameters' => array(
                array('name' => 'tipo', 'in' => 'query', 'schema' => array('type' => 'string', 'enum' => array('cliente', 'proveedor', 'ambos'))),
                array('name' => 'limit', 'in' => 'query', 'schema' => array('type' => 'integer', 'default' => 25)),
                array('name' => 'page', 'in' => 'query', 'schema' => array('type' => 'integer', 'default' => 1))
            )
        );

        // Obtener un tercero
        $app->get('/doli/terceros/{id}', function ($request, $response, $args) use ($self) {
            require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

            $tercero = new Societe($self->db);
            $result = $tercero->fetch((int) $args['id']);

            if ($result <= 0) {
                $response = $response->withStatus(404);
                $response = $response->withHeader('Content-Type', 'application/json');
                $response->getBody()->write(json_encode(array('error' => 'Tercero no encontrado')));
                return $response;
            }

            // También podemos cargar datos adicionales
            $tercero->fetch_optionals();

            $data = array(
                'success' => true,
                'data' => array(
                    'id' => (int) $tercero->id,
                    'name' => $tercero->name,
                    'name_alias' => $tercero->name_alias,
                    'email' => $tercero->email,
                    'phone' => $tercero->phone,
                    'fax' => $tercero->fax,
                    'url' => $tercero->url,
                    'address' => $tercero->address,
                    'zip' => $tercero->zip,
                    'town' => $tercero->town,
                    'country_code' => $tercero->country_code,
                    'state_code' => $tercero->state_code,
                    'client' => (int) $tercero->client,
                    'fournisseur' => (int) $tercero->fournisseur,
                    'code_client' => $tercero->code_client,
                    'code_fournisseur' => $tercero->code_fournisseur,
                    'idprof1' => $tercero->idprof1, // CIF/NIF
                    'idprof2' => $tercero->idprof2,
                    'tva_intra' => $tercero->tva_intra,
                    'status' => (int) $tercero->status,
                    'date_creation' => $tercero->date_creation,
                    'extrafields' => $tercero->array_options
                )
            );

            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($data));
            return $response;
        });

        $api->hookRoutes[] = array(
            'methods' => array('GET'),
            'path' => '/doli/terceros/{id}',
            'summary' => '🪝 Obtener tercero por ID',
            'tags' => array('Objetos Dolibarr (Hook)'),
            'security' => array(array('api_key' => array())),
            'parameters' => array(
                array('name' => 'id', 'in' => 'path', 'required' => true, 'schema' => array('type' => 'integer'))
            )
        );

        // Crear tercero
        $app->post('/doli/terceros', function ($request, $response) use ($self) {
            global $user;

            require_once DOL_DOCUMENT_ROOT . '/societe/class/societe.class.php';

            $body = json_decode((string) $request->getBody(), true);

            // Validar campos requeridos
            $validation = $self->validateBody($body, array(
                'name' => array('required' => true, 'type' => 'string', 'minLength' => 2)
            ));

            if ($validation) {
                $response = $response->withStatus(400);
                $response = $response->withHeader('Content-Type', 'application/json');
                $response->getBody()->write(json_encode($validation));
                return $response;
            }

            $tercero = new Societe($self->db);

            // Asignar valores
            $tercero->name = $body['name'];
            $tercero->name_alias = isset($body['name_alias']) ? $body['name_alias'] : '';
            $tercero->email = isset($body['email']) ? $body['email'] : '';
            $tercero->phone = isset($body['phone']) ? $body['phone'] : '';
            $tercero->address = isset($body['address']) ? $body['address'] : '';
            $tercero->zip = isset($body['zip']) ? $body['zip'] : '';
            $tercero->town = isset($body['town']) ? $body['town'] : '';
            $tercero->country_id = isset($body['country_id']) ? (int) $body['country_id'] : 0;
            $tercero->client = isset($body['client']) ? (int) $body['client'] : 0;
            $tercero->fournisseur = isset($body['fournisseur']) ? (int) $body['fournisseur'] : 0;

            // Crear
            $result = $tercero->create($user);

            if ($result < 0) {
                $response = $response->withStatus(500);
                $response = $response->withHeader('Content-Type', 'application/json');
                $response->getBody()->write(json_encode(array(
                    'error' => 'Error al crear tercero',
                    'details' => $tercero->errors
                )));
                return $response;
            }

            $data = array(
                'success' => true,
                'data' => array(
                    'id' => (int) $result,
                    'message' => 'Tercero creado correctamente'
                )
            );

            $response = $response->withStatus(201);
            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($data));
            return $response;
        });

        $api->hookRoutes[] = array(
            'methods' => array('POST'),
            'path' => '/doli/terceros',
            'summary' => '🪝 Crear tercero',
            'tags' => array('Objetos Dolibarr (Hook)'),
            'security' => array(array('api_key' => array())),
            'requestBody' => array(
                'content' => array(
                    'application/json' => array(
                        'schema' => array(
                            'type' => 'object',
                            'required' => array('name'),
                            'properties' => array(
                                'name' => array('type' => 'string', 'example' => 'Empresa Ejemplo S.L.'),
                                'email' => array('type' => 'string'),
                                'phone' => array('type' => 'string'),
                                'client' => array('type' => 'integer', 'enum' => array(0, 1, 2, 3)),
                                'fournisseur' => array('type' => 'integer', 'enum' => array(0, 1))
                            )
                        )
                    )
                )
            )
        );

        // =====================================================================
        // FACTURAS (FACTURE)
        // =====================================================================

        // Listar facturas
        $app->get('/doli/facturas', function ($request, $response) use ($self) {
            global $conf;

            require_once DOL_DOCUMENT_ROOT . '/compta/facture/class/facture.class.php';

            $params = $request->getQueryParams();
            $limit = isset($params['limit']) ? min((int) $params['limit'], 100) : 25;
            $socid = isset($params['socid']) ? (int) $params['socid'] : 0;
            $estado = isset($params['estado']) ? (int) $params['estado'] : null;

            $sql = "SELECT f.rowid FROM " . MAIN_DB_PREFIX . "facture f";
            $sql .= " WHERE f.entity IN (" . getEntity('invoice') . ")";
            if ($socid > 0) {
                $sql .= " AND f.fk_soc = " . (int) $socid;
            }
            if ($estado !== null) {
                $sql .= " AND f.fk_statut = " . (int) $estado;
            }
            $sql .= " ORDER BY f.datef DESC";
            $sql .= " LIMIT " . (int) $limit;

            $facturas = array();
            $resql = $self->db->query($sql);

            while ($obj = $self->db->fetch_object($resql)) {
                $factura = new Facture($self->db);
                if ($factura->fetch($obj->rowid) > 0) {
                    // Cargar tercero asociado
                    $factura->fetch_thirdparty();

                    $facturas[] = array(
                        'id' => (int) $factura->id,
                        'ref' => $factura->ref,
                        'ref_supplier' => $factura->ref_supplier,
                        'socid' => (int) $factura->socid,
                        'soc_name' => $factura->thirdparty->name,
                        'date' => $factura->date,
                        'date_lim_reglement' => $factura->date_lim_reglement,
                        'total_ht' => (float) $factura->total_ht,
                        'total_tva' => (float) $factura->total_tva,
                        'total_ttc' => (float) $factura->total_ttc,
                        'status' => (int) $factura->statut,
                        'status_label' => $factura->getLibStatut(1),
                        'paye' => (int) $factura->paye
                    );
                }
            }

            $data = array(
                'success' => true,
                'data' => $facturas
            );

            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($data));
            return $response;
        });

        $api->hookRoutes[] = array(
            'methods' => array('GET'),
            'path' => '/doli/facturas',
            'summary' => '🪝 Listar facturas',
            'tags' => array('Objetos Dolibarr (Hook)'),
            'security' => array(array('api_key' => array())),
            'parameters' => array(
                array('name' => 'socid', 'in' => 'query', 'description' => 'Filtrar por tercero', 'schema' => array('type' => 'integer')),
                array('name' => 'estado', 'in' => 'query', 'description' => '0=Borrador, 1=Validada, 2=Pagada', 'schema' => array('type' => 'integer')),
                array('name' => 'limit', 'in' => 'query', 'schema' => array('type' => 'integer', 'default' => 25))
            )
        );

        // =====================================================================
        // PRODUCTOS (PRODUCT)
        // =====================================================================

        // Listar productos
        $app->get('/doli/productos', function ($request, $response) use ($self) {
            require_once DOL_DOCUMENT_ROOT . '/product/class/product.class.php';

            $params = $request->getQueryParams();
            $limit = isset($params['limit']) ? min((int) $params['limit'], 100) : 25;
            $tipo = isset($params['tipo']) ? (int) $params['tipo'] : null; // 0=producto, 1=servicio
            $search = isset($params['search']) ? $params['search'] : '';

            $sql = "SELECT p.rowid FROM " . MAIN_DB_PREFIX . "product p";
            $sql .= " WHERE p.entity IN (" . getEntity('product') . ")";
            if ($tipo !== null) {
                $sql .= " AND p.fk_product_type = " . (int) $tipo;
            }
            if (!empty($search)) {
                $sql .= " AND (p.ref LIKE '%" . $self->db->escape($search) . "%'";
                $sql .= " OR p.label LIKE '%" . $self->db->escape($search) . "%')";
            }
            $sql .= " ORDER BY p.ref ASC";
            $sql .= " LIMIT " . (int) $limit;

            $productos = array();
            $resql = $self->db->query($sql);

            while ($obj = $self->db->fetch_object($resql)) {
                $producto = new Product($self->db);
                if ($producto->fetch($obj->rowid) > 0) {
                    $productos[] = array(
                        'id' => (int) $producto->id,
                        'ref' => $producto->ref,
                        'label' => $producto->label,
                        'description' => $producto->description,
                        'type' => (int) $producto->type, // 0=producto, 1=servicio
                        'price' => (float) $producto->price,
                        'price_ttc' => (float) $producto->price_ttc,
                        'tva_tx' => (float) $producto->tva_tx,
                        'stock' => (float) $producto->stock_reel,
                        'status' => (int) $producto->status,
                        'status_buy' => (int) $producto->status_buy,
                        'barcode' => $producto->barcode
                    );
                }
            }

            $data = array(
                'success' => true,
                'data' => $productos
            );

            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($data));
            return $response;
        });

        $api->hookRoutes[] = array(
            'methods' => array('GET'),
            'path' => '/doli/productos',
            'summary' => '🪝 Listar productos',
            'tags' => array('Objetos Dolibarr (Hook)'),
            'security' => array(array('api_key' => array())),
            'parameters' => array(
                array('name' => 'tipo', 'in' => 'query', 'description' => '0=Producto, 1=Servicio', 'schema' => array('type' => 'integer', 'enum' => array(0, 1))),
                array('name' => 'search', 'in' => 'query', 'description' => 'Buscar por ref o label', 'schema' => array('type' => 'string')),
                array('name' => 'limit', 'in' => 'query', 'schema' => array('type' => 'integer', 'default' => 25))
            )
        );

        // =====================================================================
        // USUARIOS (USER)
        // =====================================================================

        // Info del usuario actual
        $app->get('/doli/me', function ($request, $response) use ($self) {
            global $user;

            require_once DOL_DOCUMENT_ROOT . '/user/class/user.class.php';

            // $user ya está cargado
            $data = array(
                'success' => true,
                'data' => array(
                    'id' => (int) $user->id,
                    'login' => $user->login,
                    'lastname' => $user->lastname,
                    'firstname' => $user->firstname,
                    'email' => $user->email,
                    'admin' => (int) $user->admin,
                    'entity' => (int) $user->entity,
                    'socid' => (int) $user->socid,
                    'rights' => array(
                        'societe' => array(
                            'lire' => !empty($user->rights->societe->lire),
                            'creer' => !empty($user->rights->societe->creer)
                        ),
                        'facture' => array(
                            'lire' => !empty($user->rights->facture->lire),
                            'creer' => !empty($user->rights->facture->creer)
                        ),
                        'produit' => array(
                            'lire' => !empty($user->rights->produit->lire),
                            'creer' => !empty($user->rights->produit->creer)
                        )
                    )
                )
            );

            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($data));
            return $response;
        });

        $api->hookRoutes[] = array(
            'methods' => array('GET'),
            'path' => '/doli/me',
            'summary' => '🪝 Info del usuario actual',
            'description' => 'Devuelve información del usuario autenticado y sus permisos',
            'tags' => array('Objetos Dolibarr (Hook)'),
            'security' => array(array('api_key' => array()))
        );

        // =====================================================================
        // USANDO CONF Y LANGS
        // =====================================================================

        $app->get('/doli/config', function ($request, $response) use ($self) {
            global $conf, $langs;

            // Cargar traducciones
            $langs->loadLangs(array('main', 'companies', 'products'));

            $data = array(
                'success' => true,
                'data' => array(
                    'entity' => (int) $conf->entity,
                    'currency' => $conf->currency,
                    'global' => array(
                        'MAIN_LANG_DEFAULT' => $conf->global->MAIN_LANG_DEFAULT,
                        'MAIN_MONNAIE' => $conf->global->MAIN_MONNAIE
                    ),
                    'translations' => array(
                        'companies' => $langs->trans('ThirdParties'),
                        'customers' => $langs->trans('Customers'),
                        'suppliers' => $langs->trans('Suppliers'),
                        'products' => $langs->trans('Products'),
                        'services' => $langs->trans('Services')
                    )
                )
            );

            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($data));
            return $response;
        });

        $api->hookRoutes[] = array(
            'methods' => array('GET'),
            'path' => '/doli/config',
            'summary' => '🪝 Configuración Dolibarr',
            'description' => 'Muestra configuración global y traducciones',
            'tags' => array('Objetos Dolibarr (Hook)'),
            'security' => array(array('api_key' => array()))
        );

        return 0;
    }
}
