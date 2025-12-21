<?php
/**
 * =============================================================================
 * EJEMPLO 08: TAGS Y DOCUMENTACIÓN
 * =============================================================================
 *
 * Demuestra cómo documentar endpoints para Swagger/OpenAPI.
 *
 * MÉTODOS DE DOCUMENTACIÓN:
 *   - ->tags('Nombre Tag')             → Agrupa en Swagger UI
 *   - ->describe('Descripción')        → Descripción detallada
 *   - ->pathParams(array())            → Documenta parámetros de ruta
 *   - ->queryParams(array())           → Documenta parámetros de query
 *   - ->body(array())                  → Documenta el body del request
 *   - protected $description           → Descripción del recurso
 *   - protected $defaultTags           → Tags por defecto
 *
 * =============================================================================
 */

use EasyApi\EasyApiResource;

class TagsDocumentationResource extends EasyApiResource
{
    /**
     * Descripción del recurso - aparece junto al tag en Swagger
     */
    protected $description = 'API para gestión de pedidos con documentación completa';

    /**
     * Tags por defecto para TODAS las rutas de este recurso
     */
    protected $defaultTags = array('Pedidos');

    protected function registerRoutes(): void
    {
        // =====================================================================
        // Tags personalizados - Agrupación en Swagger UI
        // =====================================================================
        $this->get('/estadisticas', 'Estadísticas de pedidos', function ($req, $res) {
            return $this->ok($res, array(
                'total_pedidos' => 1250,
                'pedidos_mes' => 85,
                'importe_medio' => 450.00
            ));
        })
        ->tags('Pedidos - Estadísticas')  // Tag personalizado
        ->describe('Obtiene estadísticas generales de pedidos.');


        $this->get('/exportar', 'Exportar pedidos', function ($req, $res) {
            return $this->ok($res, array('export_url' => '/downloads/pedidos.csv'));
        })
        ->tags('Pedidos - Exportación')
        ->describe('Genera un archivo de exportación de pedidos.');


        // =====================================================================
        // pathParams() - Documentar parámetros de ruta
        // =====================================================================
        $this->get('/{id}', 'Ver pedido', function ($req, $res, $args) {
            return $this->ok($res, array(
                'id' => (int) $args['id'],
                'ref' => 'PED-2025-001',
                'estado' => 'confirmado'
            ));
        })
        ->pathParams(array(
            'id' => array(
                'type' => 'integer',
                'description' => 'ID único del pedido',
                'example' => 123
            )
        ))
        ->describe('Obtiene los detalles completos de un pedido por su ID.');


        $this->get('/{id}/lineas/{lineaId}', 'Ver línea de pedido', function ($req, $res, $args) {
            return $this->ok($res, array(
                'pedido_id' => (int) $args['id'],
                'linea_id' => (int) $args['lineaId'],
                'producto' => 'Producto XYZ',
                'cantidad' => 5
            ));
        })
        ->pathParams(array(
            'id' => array(
                'type' => 'integer',
                'description' => 'ID del pedido'
            ),
            'lineaId' => array(
                'type' => 'integer',
                'description' => 'ID de la línea dentro del pedido'
            )
        ))
        ->describe('Obtiene una línea específica de un pedido.');


        // =====================================================================
        // queryParams() - Documentar parámetros de query string
        // =====================================================================
        $this->get('/', 'Listar pedidos', function ($req, $res) {
            $estado = $this->query($req, 'estado', 'todos');
            $fechaDesde = $this->query($req, 'fecha_desde');
            $fechaHasta = $this->query($req, 'fecha_hasta');
            $cliente = $this->query($req, 'cliente');
            $limit = (int) $this->query($req, 'limit', 20);
            $offset = (int) $this->query($req, 'offset', 0);
            $ordenar = $this->query($req, 'ordenar', 'fecha_desc');

            return $this->ok($res, array(
                'filtros_aplicados' => array(
                    'estado' => $estado,
                    'fecha_desde' => $fechaDesde,
                    'fecha_hasta' => $fechaHasta,
                    'cliente' => $cliente,
                    'ordenar' => $ordenar
                ),
                'pagination' => array(
                    'limit' => $limit,
                    'offset' => $offset
                ),
                'pedidos' => array()
            ));
        })
        ->queryParams(array(
            'estado' => array(
                'type' => 'string',
                'description' => 'Filtrar por estado',
                'enum' => array('borrador', 'confirmado', 'enviado', 'entregado', 'cancelado')
            ),
            'fecha_desde' => array(
                'type' => 'string',
                'description' => 'Fecha inicio (YYYY-MM-DD)'
            ),
            'fecha_hasta' => array(
                'type' => 'string',
                'description' => 'Fecha fin (YYYY-MM-DD)'
            ),
            'cliente' => array(
                'type' => 'integer',
                'description' => 'ID del cliente'
            ),
            'limit' => array(
                'type' => 'integer',
                'description' => 'Cantidad de resultados (default: 20, max: 100)'
            ),
            'offset' => array(
                'type' => 'integer',
                'description' => 'Offset para paginación'
            ),
            'ordenar' => array(
                'type' => 'string',
                'description' => 'Ordenamiento',
                'enum' => array('fecha_asc', 'fecha_desc', 'total_asc', 'total_desc', 'ref_asc')
            )
        ))
        ->describe('Lista pedidos con filtros, paginación y ordenamiento.');


        // =====================================================================
        // body() - Documentar el cuerpo del request
        // =====================================================================
        $this->post('/', 'Crear pedido', function ($req, $res) {
            $schema = array(
                'required' => array('cliente_id', 'lineas'),
                'properties' => array(
                    'cliente_id' => array('type' => 'integer', 'min' => 1),
                    'lineas' => array('type' => 'array', 'minItems' => 1)
                )
            );

            $validation = $this->validateBody($req, $schema);
            if (!$validation['valid']) {
                return $this->badRequest($res, $validation['error']);
            }

            return $this->created($res, array(
                'id' => 999,
                'ref' => 'PED-2025-999',
                'mensaje' => 'Pedido creado'
            ));
        })
        ->body(array(
            'required' => array('cliente_id', 'lineas'),
            'properties' => array(
                'cliente_id' => array(
                    'type' => 'integer',
                    'description' => 'ID del cliente (tercero)'
                ),
                'ref_cliente' => array(
                    'type' => 'string',
                    'description' => 'Referencia del cliente (opcional)'
                ),
                'fecha_entrega' => array(
                    'type' => 'string',
                    'description' => 'Fecha de entrega deseada (YYYY-MM-DD)'
                ),
                'notas' => array(
                    'type' => 'string',
                    'description' => 'Notas internas del pedido'
                ),
                'notas_publicas' => array(
                    'type' => 'string',
                    'description' => 'Notas visibles para el cliente'
                ),
                'lineas' => array(
                    'type' => 'array',
                    'description' => 'Array de líneas del pedido. Cada línea: {producto_id, cantidad, precio_unitario}'
                )
            )
        ))
        ->describe('Crea un nuevo pedido con sus líneas.');


        // =====================================================================
        // Documentación completa en un solo endpoint
        // =====================================================================
        $this->put('/{id}', 'Actualizar pedido', function ($req, $res, $args) {
            return $this->ok($res, array(
                'id' => (int) $args['id'],
                'mensaje' => 'Pedido actualizado'
            ));
        })
        ->pathParams(array(
            'id' => array(
                'type' => 'integer',
                'description' => 'ID del pedido a actualizar'
            )
        ))
        ->body(array(
            'properties' => array(
                'ref_cliente' => array('type' => 'string', 'description' => 'Referencia del cliente'),
                'fecha_entrega' => array('type' => 'string', 'description' => 'Fecha entrega YYYY-MM-DD'),
                'notas' => array('type' => 'string', 'description' => 'Notas internas'),
                'estado' => array('type' => 'string', 'description' => 'Nuevo estado del pedido')
            )
        ))
        ->tags('Pedidos - CRUD')
        ->describe('Actualiza los datos de un pedido existente. No modifica las líneas.');
    }
}
