<?php
/**
 * =============================================================================
 * EJEMPLO 01: HOOK BÁSICO
 * =============================================================================
 *
 * Los Hooks son la forma tradicional de agregar endpoints a EasyAPI.
 * Se registran mediante el sistema de hooks de Dolibarr.
 *
 * UBICACIÓN: custom/tumodulo/class/actions_tumodulo.class.php
 *
 * HOOK PRINCIPAL: easyApiRegisterRoutes
 *   - Se ejecuta cuando EasyAPI registra las rutas
 *   - Recibe: $app (Slim App), $api (ApiEasyApi)
 *   - Permite registrar rutas directamente en Slim
 *
 * =============================================================================
 */

/**
 * Clase de acciones del módulo (hooks de Dolibarr)
 *
 * El nombre debe ser: ActionsNombreModulo
 * El archivo debe estar en: custom/tumodulo/class/actions_tumodulo.class.php
 */
class ActionsMiModulo
{
    /**
     * @var DoliDB Base de datos
     */
    public $db;

    /**
     * @var string Código de error
     */
    public $error = '';

    /**
     * @var array Errores múltiples
     */
    public $errors = array();

    /**
     * @var array Resultados del hook
     */
    public $results = array();

    /**
     * @var string Cadena para retornar
     */
    public $resprints = '';

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Hook para registrar rutas en EasyAPI
     *
     * Este es el hook principal para agregar endpoints.
     *
     * @param array $parameters Parámetros del hook
     * @param object $object Objeto que llama al hook (ApiEasyApi)
     * @param string $action Acción actual
     * @return int 0=OK, <0=Error
     */
    public function easyApiRegisterRoutes($parameters, &$object, &$action)
    {
        global $conf, $user, $langs;

        // Obtener la app de Slim
        $app = $parameters['app'];

        // Obtener la instancia de ApiEasyApi (útil para helpers)
        $api = $object;

        // Guardar referencia a $this para usar en closures
        $self = $this;

        // =====================================================================
        // ENDPOINT GET SIMPLE
        // =====================================================================
        $app->get('/mimodulo/hola', function ($request, $response) {
            $data = array(
                'success' => true,
                'data' => array(
                    'mensaje' => '¡Hola desde el hook!',
                    'timestamp' => time()
                )
            );

            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($data));
            return $response;
        });

        // =====================================================================
        // ENDPOINT CON PARÁMETROS DE RUTA
        // =====================================================================
        $app->get('/mimodulo/usuario/{id}', function ($request, $response, $args) use ($self) {
            $id = (int) $args['id'];

            // Consultar base de datos
            $sql = "SELECT rowid, login, firstname, lastname, email
                    FROM " . MAIN_DB_PREFIX . "user
                    WHERE rowid = " . $id;

            $resql = $self->db->query($sql);

            if ($resql && $self->db->num_rows($resql) > 0) {
                $obj = $self->db->fetch_object($resql);
                $data = array(
                    'success' => true,
                    'data' => array(
                        'id' => (int) $obj->rowid,
                        'login' => $obj->login,
                        'nombre' => $obj->firstname . ' ' . $obj->lastname,
                        'email' => $obj->email
                    )
                );
            } else {
                $data = array(
                    'success' => false,
                    'error' => array(
                        'code' => 404,
                        'message' => "Usuario #$id no encontrado"
                    )
                );
                $response = $response->withStatus(404);
            }

            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($data));
            return $response;
        });

        // =====================================================================
        // ENDPOINT CON QUERY PARAMS
        // =====================================================================
        $app->get('/mimodulo/buscar', function ($request, $response) use ($self) {
            // Obtener parámetros del query string
            $params = $request->getQueryParams();
            $search = isset($params['q']) ? $params['q'] : '';
            $limit = isset($params['limit']) ? (int) $params['limit'] : 10;

            if (empty($search)) {
                $data = array(
                    'success' => false,
                    'error' => array(
                        'code' => 400,
                        'message' => 'El parámetro "q" es requerido'
                    )
                );
                $response = $response->withStatus(400);
            } else {
                // Búsqueda simulada
                $data = array(
                    'success' => true,
                    'data' => array(
                        'query' => $search,
                        'limit' => $limit,
                        'resultados' => array()
                    )
                );
            }

            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($data));
            return $response;
        });

        // =====================================================================
        // ENDPOINT POST
        // =====================================================================
        $app->post('/mimodulo/mensaje', function ($request, $response) {
            // Obtener body JSON
            $body = json_decode((string) $request->getBody(), true);

            if (empty($body['texto'])) {
                $data = array(
                    'success' => false,
                    'error' => array(
                        'code' => 400,
                        'message' => 'El campo "texto" es requerido'
                    )
                );
                $response = $response->withStatus(400);
            } else {
                $data = array(
                    'success' => true,
                    'data' => array(
                        'mensaje' => 'Mensaje recibido correctamente',
                        'texto_recibido' => $body['texto'],
                        'fecha' => date('Y-m-d H:i:s')
                    )
                );
                $response = $response->withStatus(201);
            }

            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($data));
            return $response;
        });

        // =====================================================================
        // REGISTRAR EN OPENAPI (Documentación Swagger)
        // =====================================================================
        // Agregar a $api->hookRoutes para que aparezcan en Swagger
        $api->hookRoutes[] = array(
            'methods' => array('GET'),
            'path' => '/mimodulo/hola',
            'summary' => '🪝 Saludo básico',
            'description' => 'Endpoint de ejemplo que devuelve un saludo.',
            'tags' => array('Mi Módulo - Hooks'),
            'security' => array(array('api_key' => array())),
            'responses' => array(
                '200' => array('description' => 'Saludo exitoso')
            )
        );

        $api->hookRoutes[] = array(
            'methods' => array('GET'),
            'path' => '/mimodulo/usuario/{id}',
            'summary' => '🪝 Ver usuario',
            'description' => 'Obtiene información de un usuario por ID.',
            'tags' => array('Mi Módulo - Hooks'),
            'security' => array(array('api_key' => array())),
            'parameters' => array(
                array(
                    'name' => 'id',
                    'in' => 'path',
                    'required' => true,
                    'schema' => array('type' => 'integer'),
                    'description' => 'ID del usuario'
                )
            ),
            'responses' => array(
                '200' => array('description' => 'Usuario encontrado'),
                '404' => array('description' => 'Usuario no encontrado')
            )
        );

        $api->hookRoutes[] = array(
            'methods' => array('GET'),
            'path' => '/mimodulo/buscar',
            'summary' => '🪝 Buscar',
            'description' => 'Búsqueda con query params.',
            'tags' => array('Mi Módulo - Hooks'),
            'security' => array(array('api_key' => array())),
            'parameters' => array(
                array(
                    'name' => 'q',
                    'in' => 'query',
                    'required' => true,
                    'schema' => array('type' => 'string'),
                    'description' => 'Término de búsqueda'
                ),
                array(
                    'name' => 'limit',
                    'in' => 'query',
                    'required' => false,
                    'schema' => array('type' => 'integer'),
                    'description' => 'Límite de resultados'
                )
            ),
            'responses' => array(
                '200' => array('description' => 'Resultados de búsqueda')
            )
        );

        $api->hookRoutes[] = array(
            'methods' => array('POST'),
            'path' => '/mimodulo/mensaje',
            'summary' => '🪝 Crear mensaje',
            'description' => 'Recibe un mensaje por POST.',
            'tags' => array('Mi Módulo - Hooks'),
            'security' => array(array('api_key' => array())),
            'requestBody' => array(
                'required' => true,
                'content' => array(
                    'application/json' => array(
                        'schema' => array(
                            'type' => 'object',
                            'required' => array('texto'),
                            'properties' => array(
                                'texto' => array(
                                    'type' => 'string',
                                    'description' => 'Texto del mensaje'
                                )
                            )
                        )
                    )
                )
            ),
            'responses' => array(
                '201' => array('description' => 'Mensaje creado'),
                '400' => array('description' => 'Datos inválidos')
            )
        );

        return 0; // OK
    }
}
