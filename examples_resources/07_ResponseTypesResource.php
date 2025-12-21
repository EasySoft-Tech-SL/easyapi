<?php
/**
 * =============================================================================
 * EJEMPLO 07: TIPOS DE RESPUESTA
 * =============================================================================
 *
 * Demuestra todos los métodos de respuesta disponibles en EasyApiResource.
 *
 * MÉTODOS DE RESPUESTA:
 *   - $this->ok($res, $data)              → 200 OK
 *   - $this->created($res, $data)         → 201 Created
 *   - $this->noContent($res)              → 204 No Content
 *   - $this->badRequest($res, $msg)       → 400 Bad Request
 *   - $this->unauthorized($res, $msg)     → 401 Unauthorized
 *   - $this->forbidden($res, $msg)        → 403 Forbidden
 *   - $this->notFound($res, $msg)         → 404 Not Found
 *   - $this->error($res, $msg)            → 500 Internal Server Error
 *   - $this->json($res, $data, $status)   → Respuesta JSON personalizada
 *
 * =============================================================================
 */

use EasyApi\EasyApiResource;

class ResponseTypesResource extends EasyApiResource
{
    protected $description = 'Ejemplos de todos los tipos de respuesta';

    protected function registerRoutes(): void
    {
        // =====================================================================
        // 200 OK - Operación exitosa
        // =====================================================================
        $this->get('/ok', 'Respuesta 200 OK', function ($req, $res) {
            return $this->ok($res, array(
                'mensaje' => 'Operación exitosa',
                'codigo' => 200,
                'uso' => 'Para GET exitoso, UPDATE exitoso, etc.'
            ));
        })
        ->tags('Respuestas')
        ->describe('Respuesta 200 OK - Operación exitosa con datos.');


        // =====================================================================
        // 201 Created - Recurso creado
        // =====================================================================
        $this->post('/created', 'Respuesta 201 Created', function ($req, $res) {
            return $this->created($res, array(
                'id' => 12345,
                'mensaje' => 'Recurso creado correctamente',
                'codigo' => 201,
                'uso' => 'Para POST que crea un nuevo recurso'
            ));
        })
        ->tags('Respuestas')
        ->describe('Respuesta 201 Created - Recurso creado exitosamente.');


        // =====================================================================
        // 204 No Content - Operación exitosa sin contenido
        // =====================================================================
        $this->delete('/no-content', 'Respuesta 204 No Content', function ($req, $res) {
            // Simular eliminación exitosa
            return $this->noContent($res);
        })
        ->tags('Respuestas')
        ->describe('Respuesta 204 No Content - Para DELETE exitoso.');


        // =====================================================================
        // 400 Bad Request - Error de validación
        // =====================================================================
        $this->get('/bad-request', 'Respuesta 400 Bad Request', function ($req, $res) {
            return $this->badRequest($res, 'El parámetro "email" es inválido');
        })
        ->tags('Respuestas')
        ->describe('Respuesta 400 Bad Request - Error de validación o datos inválidos.');


        // =====================================================================
        // 401 Unauthorized - No autenticado
        // =====================================================================
        $this->get('/unauthorized', 'Respuesta 401 Unauthorized', function ($req, $res) {
            return $this->unauthorized($res, 'Token de autenticación inválido o expirado');
        })
        ->tags('Respuestas')
        ->describe('Respuesta 401 Unauthorized - Usuario no autenticado.');


        // =====================================================================
        // 403 Forbidden - Sin permisos
        // =====================================================================
        $this->get('/forbidden', 'Respuesta 403 Forbidden', function ($req, $res) {
            return $this->forbidden($res, 'No tienes permisos para realizar esta acción');
        })
        ->tags('Respuestas')
        ->describe('Respuesta 403 Forbidden - Autenticado pero sin permisos.');


        // =====================================================================
        // 404 Not Found - Recurso no encontrado
        // =====================================================================
        $this->get('/not-found', 'Respuesta 404 Not Found', function ($req, $res) {
            return $this->notFound($res, 'El recurso solicitado no existe');
        })
        ->tags('Respuestas')
        ->describe('Respuesta 404 Not Found - Recurso no encontrado.');


        // =====================================================================
        // 500 Internal Server Error - Error del servidor
        // =====================================================================
        $this->get('/error', 'Respuesta 500 Error', function ($req, $res) {
            return $this->error($res, 'Error interno del servidor');
        })
        ->tags('Respuestas')
        ->describe('Respuesta 500 Internal Server Error - Error del servidor.');


        // =====================================================================
        // json() - Respuesta personalizada
        // =====================================================================
        $this->get('/custom/{code}', 'Respuesta personalizada', function ($req, $res, $args) {
            $code = (int) $args['code'];

            // Validar código HTTP
            if ($code < 100 || $code > 599) {
                return $this->badRequest($res, 'Código HTTP debe estar entre 100 y 599');
            }

            return $this->json($res, array(
                'custom' => true,
                'http_code' => $code,
                'mensaje' => "Respuesta personalizada con código $code"
            ), $code);
        })
        ->pathParams(array(
            'code' => array('type' => 'integer', 'description' => 'Código HTTP (100-599)')
        ))
        ->tags('Respuestas')
        ->describe('Respuesta JSON con código HTTP personalizado.');


        // =====================================================================
        // Ejemplo práctico: Manejo de errores en CRUD
        // =====================================================================
        $this->get('/producto/{id}', 'Ejemplo práctico', function ($req, $res, $args) {
            $id = (int) $args['id'];

            // Simular diferentes escenarios según el ID
            switch ($id) {
                case 0:
                    return $this->badRequest($res, 'El ID debe ser mayor que 0');

                case 404:
                    return $this->notFound($res, "Producto #$id no encontrado");

                case 403:
                    return $this->forbidden($res, 'No tienes permisos para ver este producto');

                case 500:
                    return $this->error($res, 'Error al consultar la base de datos');

                default:
                    return $this->ok($res, array(
                        'id' => $id,
                        'nombre' => "Producto #$id",
                        'precio' => 99.99
                    ));
            }
        })
        ->pathParams(array(
            'id' => array('type' => 'integer', 'description' => 'ID del producto (usa 0, 403, 404, 500 para simular errores)')
        ))
        ->tags('Respuestas')
        ->describe('Ejemplo práctico de manejo de diferentes respuestas.');
    }
}
