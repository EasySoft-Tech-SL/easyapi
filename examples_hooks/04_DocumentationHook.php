<?php
/**
 * =============================================================================
 * EJEMPLO 04: DOCUMENTACIÓN OPENAPI EN HOOKS
 * =============================================================================
 *
 * Demuestra cómo documentar correctamente los endpoints de hooks
 * para que aparezcan en Swagger UI.
 *
 * La documentación se añade a: $api->hookRoutes[]
 *
 * ESTRUCTURA DE hookRoutes:
 *   - methods: array de métodos HTTP
 *   - path: ruta del endpoint
 *   - summary: título corto
 *   - description: descripción larga
 *   - tags: array de tags para agrupar
 *   - security: esquemas de seguridad
 *   - parameters: parámetros de path y query
 *   - requestBody: cuerpo de la petición
 *   - responses: respuestas posibles
 *
 * =============================================================================
 */

class ActionsDocumentacionApi
{
    public $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function easyApiRegisterRoutes($parameters, &$object, &$action)
    {
        $app = $parameters['app'];
        $api = $object;
        $self = $this;

        // =====================================================================
        // Endpoint con documentación COMPLETA
        // =====================================================================
        $app->get('/docs/completo/{id}', function ($request, $response, $args) use ($self) {
            $params = $request->getQueryParams();

            $data = array(
                'success' => true,
                'data' => array(
                    'id' => (int) $args['id'],
                    'params' => $params
                )
            );

            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($data));
            return $response;
        });

        // Documentación COMPLETA
        $api->hookRoutes[] = array(
            // Métodos HTTP soportados
            'methods' => array('GET'),

            // Ruta (con parámetros entre llaves)
            'path' => '/docs/completo/{id}',

            // Título corto (aparece en la lista)
            'summary' => '🪝 Endpoint documentado completo',

            // Descripción larga (aparece al expandir)
            'description' => 'Este endpoint demuestra todas las opciones de documentación disponibles para hooks. Incluye parámetros de ruta, query params y respuestas documentadas.',

            // Tags para agrupar en Swagger UI
            'tags' => array('Documentación (Hook)'),

            // Seguridad (api_key = DOLAPIKEY)
            'security' => array(array('api_key' => array())),

            // Parámetros de ruta y query
            'parameters' => array(
                // Parámetro de ruta (path)
                array(
                    'name' => 'id',
                    'in' => 'path',
                    'required' => true,
                    'description' => 'ID del recurso a consultar',
                    'schema' => array(
                        'type' => 'integer',
                        'minimum' => 1,
                        'example' => 123
                    )
                ),
                // Parámetro de query
                array(
                    'name' => 'incluir_detalles',
                    'in' => 'query',
                    'required' => false,
                    'description' => 'Si es true, incluye información detallada',
                    'schema' => array(
                        'type' => 'boolean',
                        'default' => false
                    )
                ),
                // Query param con enum
                array(
                    'name' => 'formato',
                    'in' => 'query',
                    'required' => false,
                    'description' => 'Formato de respuesta',
                    'schema' => array(
                        'type' => 'string',
                        'enum' => array('simple', 'detallado', 'completo'),
                        'default' => 'simple'
                    )
                )
            ),

            // Respuestas documentadas
            'responses' => array(
                '200' => array(
                    'description' => 'Operación exitosa',
                    'content' => array(
                        'application/json' => array(
                            'schema' => array(
                                'type' => 'object',
                                'properties' => array(
                                    'success' => array('type' => 'boolean'),
                                    'data' => array(
                                        'type' => 'object',
                                        'properties' => array(
                                            'id' => array('type' => 'integer'),
                                            'params' => array('type' => 'object')
                                        )
                                    )
                                )
                            )
                        )
                    )
                ),
                '404' => array(
                    'description' => 'Recurso no encontrado'
                ),
                '500' => array(
                    'description' => 'Error interno del servidor'
                )
            )
        );

        // =====================================================================
        // Endpoint POST con requestBody documentado
        // =====================================================================
        $app->post('/docs/crear', function ($request, $response) use ($self) {
            $body = json_decode((string) $request->getBody(), true);

            $data = array(
                'success' => true,
                'data' => array(
                    'message' => 'Creado correctamente',
                    'received' => $body
                )
            );

            $response = $response->withStatus(201);
            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($data));
            return $response;
        });

        $api->hookRoutes[] = array(
            'methods' => array('POST'),
            'path' => '/docs/crear',
            'summary' => '🪝 Crear recurso (POST documentado)',
            'description' => 'Ejemplo de documentación de endpoint POST con requestBody completo.',
            'tags' => array('Documentación (Hook)'),
            'security' => array(array('api_key' => array())),

            // Documentación del body
            'requestBody' => array(
                'required' => true,
                'description' => 'Datos del recurso a crear',
                'content' => array(
                    'application/json' => array(
                        'schema' => array(
                            'type' => 'object',
                            'required' => array('nombre', 'email'),
                            'properties' => array(
                                'nombre' => array(
                                    'type' => 'string',
                                    'description' => 'Nombre completo',
                                    'minLength' => 2,
                                    'maxLength' => 100,
                                    'example' => 'Juan Pérez'
                                ),
                                'email' => array(
                                    'type' => 'string',
                                    'format' => 'email',
                                    'description' => 'Correo electrónico',
                                    'example' => 'juan@ejemplo.com'
                                ),
                                'edad' => array(
                                    'type' => 'integer',
                                    'description' => 'Edad en años',
                                    'minimum' => 18,
                                    'maximum' => 120,
                                    'example' => 30
                                ),
                                'activo' => array(
                                    'type' => 'boolean',
                                    'description' => 'Estado activo',
                                    'default' => true
                                ),
                                'tipo' => array(
                                    'type' => 'string',
                                    'description' => 'Tipo de usuario',
                                    'enum' => array('cliente', 'proveedor', 'empleado')
                                ),
                                'tags' => array(
                                    'type' => 'array',
                                    'description' => 'Etiquetas',
                                    'items' => array(
                                        'type' => 'string'
                                    ),
                                    'example' => array('vip', 'nuevo')
                                )
                            )
                        )
                    )
                )
            ),

            'responses' => array(
                '201' => array('description' => 'Recurso creado exitosamente'),
                '400' => array('description' => 'Datos de entrada inválidos')
            )
        );

        // =====================================================================
        // Endpoint público (sin security)
        // =====================================================================
        $app->get('/docs/publico', function ($request, $response) {
            $data = array(
                'success' => true,
                'data' => array(
                    'message' => 'Este endpoint es público',
                    'auth_required' => false
                )
            );

            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($data));
            return $response;
        });

        $api->hookRoutes[] = array(
            'methods' => array('GET'),
            'path' => '/docs/publico',
            'summary' => '🪝 Endpoint público',
            'description' => 'Este endpoint NO requiere autenticación.',
            'tags' => array('Documentación (Hook)'),
            // SIN 'security' = público
            'responses' => array(
                '200' => array('description' => 'OK')
            )
        );

        // =====================================================================
        // Múltiples métodos en un mismo path
        // =====================================================================
        $app->get('/docs/multi', function ($request, $response) {
            $data = array('method' => 'GET', 'action' => 'listar');
            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($data));
            return $response;
        });

        $app->post('/docs/multi', function ($request, $response) {
            $data = array('method' => 'POST', 'action' => 'crear');
            $response = $response->withStatus(201);
            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($data));
            return $response;
        });

        // Cada método necesita su propia entrada en hookRoutes
        $api->hookRoutes[] = array(
            'methods' => array('GET'),
            'path' => '/docs/multi',
            'summary' => '🪝 Multi - GET (listar)',
            'tags' => array('Documentación (Hook)'),
            'security' => array(array('api_key' => array()))
        );

        $api->hookRoutes[] = array(
            'methods' => array('POST'),
            'path' => '/docs/multi',
            'summary' => '🪝 Multi - POST (crear)',
            'tags' => array('Documentación (Hook)'),
            'security' => array(array('api_key' => array())),
            'requestBody' => array(
                'content' => array(
                    'application/json' => array(
                        'schema' => array('type' => 'object')
                    )
                )
            )
        );

        // =====================================================================
        // Documentación mínima (solo lo esencial)
        // =====================================================================
        $app->get('/docs/minimo', function ($request, $response) {
            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode(array('ok' => true)));
            return $response;
        });

        // Documentación mínima - solo lo obligatorio
        $api->hookRoutes[] = array(
            'methods' => array('GET'),
            'path' => '/docs/minimo',
            'summary' => '🪝 Documentación mínima',
            'tags' => array('Documentación (Hook)')
        );

        return 0;
    }
}
