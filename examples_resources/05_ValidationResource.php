<?php
/**
 * =============================================================================
 * EJEMPLO 05: VALIDACIÓN DE DATOS
 * =============================================================================
 *
 * Demuestra todas las opciones de validación con validateBody().
 *
 * MÉTODO CLAVE:
 *   $validation = $this->validateBody($req, $schema);
 *   if (!$validation['valid']) {
 *       return $this->badRequest($res, $validation['error']);
 *   }
 *   $data = $validation['data'];
 *
 * OPCIONES DE VALIDACIÓN:
 *   - required: array de campos obligatorios
 *   - type: string, integer, number, boolean, array, object
 *   - minLength, maxLength: para strings
 *   - min, max: para números
 *   - format: email, url, date, datetime, time, uuid, phone, ip
 *   - enum: array de valores permitidos
 *   - pattern: expresión regular
 *   - minItems, maxItems: para arrays
 *
 * =============================================================================
 */

use EasyApi\EasyApiResource;

class ValidationResource extends EasyApiResource
{
    protected $description = 'Ejemplos de validación de datos';

    protected function registerRoutes(): void
    {
        // =====================================================================
        // VALIDACIÓN BÁSICA: Campos requeridos y tipos
        // =====================================================================
        $this->post('/basico', 'Validación básica', function ($req, $res) {
            $schema = array(
                'required' => array('nombre', 'email', 'edad'),
                'properties' => array(
                    'nombre' => array('type' => 'string'),
                    'email' => array('type' => 'string'),
                    'edad' => array('type' => 'integer'),
                    'activo' => array('type' => 'boolean')
                )
            );

            $validation = $this->validateBody($req, $schema);
            if (!$validation['valid']) {
                return $this->badRequest($res, $validation['error']);
            }

            return $this->created($res, array(
                'message' => 'Datos válidos',
                'data' => $validation['data']
            ));
        })
        ->body(array(
            'required' => array('nombre', 'email', 'edad'),
            'properties' => array(
                'nombre' => array('type' => 'string', 'description' => 'Nombre completo'),
                'email' => array('type' => 'string', 'description' => 'Email de contacto'),
                'edad' => array('type' => 'integer', 'description' => 'Edad en años'),
                'activo' => array('type' => 'boolean', 'description' => 'Estado activo/inactivo')
            )
        ))
        ->tags('Validación')
        ->describe('Validación de campos requeridos y tipos básicos.');


        // =====================================================================
        // VALIDACIÓN DE STRINGS: minLength, maxLength, pattern
        // =====================================================================
        $this->post('/strings', 'Validación de strings', function ($req, $res) {
            $schema = array(
                'required' => array('username', 'password', 'codigo_postal'),
                'properties' => array(
                    'username' => array(
                        'type' => 'string',
                        'minLength' => 3,      // Mínimo 3 caracteres
                        'maxLength' => 20      // Máximo 20 caracteres
                    ),
                    'password' => array(
                        'type' => 'string',
                        'minLength' => 8       // Mínimo 8 caracteres
                    ),
                    'codigo_postal' => array(
                        'type' => 'string',
                        'pattern' => '^[0-9]{5}$'  // Exactamente 5 dígitos
                    ),
                    'dni' => array(
                        'type' => 'string',
                        'pattern' => '^[0-9]{8}[A-Z]$'  // 8 números + 1 letra
                    )
                )
            );

            $validation = $this->validateBody($req, $schema);
            if (!$validation['valid']) {
                return $this->badRequest($res, $validation['error']);
            }

            return $this->created($res, array(
                'message' => 'Strings válidos',
                'data' => $validation['data']
            ));
        })
        ->body(array(
            'required' => array('username', 'password', 'codigo_postal'),
            'properties' => array(
                'username' => array('type' => 'string', 'description' => '3-20 caracteres'),
                'password' => array('type' => 'string', 'description' => 'Mínimo 8 caracteres'),
                'codigo_postal' => array('type' => 'string', 'description' => '5 dígitos (ej: 28001)'),
                'dni' => array('type' => 'string', 'description' => '8 números + letra (ej: 12345678A)')
            )
        ))
        ->tags('Validación')
        ->describe('Validación de strings con longitud y patrón regex.');


        // =====================================================================
        // VALIDACIÓN DE NÚMEROS: min, max
        // =====================================================================
        $this->post('/numeros', 'Validación de números', function ($req, $res) {
            $schema = array(
                'required' => array('cantidad', 'precio'),
                'properties' => array(
                    'cantidad' => array(
                        'type' => 'integer',
                        'min' => 1,       // Mínimo 1
                        'max' => 1000     // Máximo 1000
                    ),
                    'precio' => array(
                        'type' => 'number',
                        'min' => 0.01,    // Mínimo 0.01
                        'max' => 999999.99
                    ),
                    'descuento' => array(
                        'type' => 'number',
                        'min' => 0,
                        'max' => 100      // Porcentaje 0-100
                    ),
                    'rating' => array(
                        'type' => 'integer',
                        'min' => 1,
                        'max' => 5        // Estrellas 1-5
                    )
                )
            );

            $validation = $this->validateBody($req, $schema);
            if (!$validation['valid']) {
                return $this->badRequest($res, $validation['error']);
            }

            return $this->created($res, array(
                'message' => 'Números válidos',
                'data' => $validation['data']
            ));
        })
        ->body(array(
            'required' => array('cantidad', 'precio'),
            'properties' => array(
                'cantidad' => array('type' => 'integer', 'description' => 'Cantidad (1-1000)'),
                'precio' => array('type' => 'number', 'description' => 'Precio (0.01-999999.99)'),
                'descuento' => array('type' => 'number', 'description' => 'Descuento % (0-100)'),
                'rating' => array('type' => 'integer', 'description' => 'Valoración (1-5 estrellas)')
            )
        ))
        ->tags('Validación')
        ->describe('Validación de números con rangos mínimo y máximo.');


        // =====================================================================
        // VALIDACIÓN DE FORMATOS: email, url, date, etc.
        // =====================================================================
        $this->post('/formatos', 'Validación de formatos', function ($req, $res) {
            $schema = array(
                'required' => array('email'),
                'properties' => array(
                    'email' => array(
                        'type' => 'string',
                        'format' => 'email'          // email@dominio.com
                    ),
                    'website' => array(
                        'type' => 'string',
                        'format' => 'url'            // https://...
                    ),
                    'fecha_nacimiento' => array(
                        'type' => 'string',
                        'format' => 'date'           // YYYY-MM-DD
                    ),
                    'fecha_hora' => array(
                        'type' => 'string',
                        'format' => 'datetime'       // YYYY-MM-DD HH:MM:SS
                    ),
                    'hora' => array(
                        'type' => 'string',
                        'format' => 'time'           // HH:MM:SS
                    ),
                    'uuid' => array(
                        'type' => 'string',
                        'format' => 'uuid'           // xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
                    ),
                    'telefono' => array(
                        'type' => 'string',
                        'format' => 'phone'          // +34 612 345 678
                    ),
                    'ip_servidor' => array(
                        'type' => 'string',
                        'format' => 'ip'             // 192.168.1.1
                    )
                )
            );

            $validation = $this->validateBody($req, $schema);
            if (!$validation['valid']) {
                return $this->badRequest($res, $validation['error']);
            }

            return $this->created($res, array(
                'message' => 'Formatos válidos',
                'data' => $validation['data']
            ));
        })
        ->body(array(
            'required' => array('email'),
            'properties' => array(
                'email' => array('type' => 'string', 'description' => 'Email válido'),
                'website' => array('type' => 'string', 'description' => 'URL válida'),
                'fecha_nacimiento' => array('type' => 'string', 'description' => 'Fecha YYYY-MM-DD'),
                'fecha_hora' => array('type' => 'string', 'description' => 'Datetime YYYY-MM-DD HH:MM:SS'),
                'hora' => array('type' => 'string', 'description' => 'Hora HH:MM:SS'),
                'uuid' => array('type' => 'string', 'description' => 'UUID válido'),
                'telefono' => array('type' => 'string', 'description' => 'Teléfono'),
                'ip_servidor' => array('type' => 'string', 'description' => 'Dirección IP')
            )
        ))
        ->tags('Validación')
        ->describe('Validación de formatos especiales: email, url, date, uuid, etc.');


        // =====================================================================
        // VALIDACIÓN CON ENUM: Valores permitidos
        // =====================================================================
        $this->post('/enum', 'Validación con enum', function ($req, $res) {
            $schema = array(
                'required' => array('estado', 'prioridad'),
                'properties' => array(
                    'estado' => array(
                        'type' => 'string',
                        'enum' => array('borrador', 'pendiente', 'aprobado', 'rechazado', 'cancelado')
                    ),
                    'prioridad' => array(
                        'type' => 'string',
                        'enum' => array('baja', 'media', 'alta', 'urgente')
                    ),
                    'tipo_documento' => array(
                        'type' => 'string',
                        'enum' => array('factura', 'presupuesto', 'pedido', 'albaran')
                    ),
                    'pais' => array(
                        'type' => 'string',
                        'enum' => array('ES', 'FR', 'DE', 'IT', 'PT', 'UK', 'US')
                    )
                )
            );

            $validation = $this->validateBody($req, $schema);
            if (!$validation['valid']) {
                return $this->badRequest($res, $validation['error']);
            }

            return $this->created($res, array(
                'message' => 'Valores enum válidos',
                'data' => $validation['data']
            ));
        })
        ->body(array(
            'required' => array('estado', 'prioridad'),
            'properties' => array(
                'estado' => array('type' => 'string', 'description' => 'borrador|pendiente|aprobado|rechazado|cancelado'),
                'prioridad' => array('type' => 'string', 'description' => 'baja|media|alta|urgente'),
                'tipo_documento' => array('type' => 'string', 'description' => 'factura|presupuesto|pedido|albaran'),
                'pais' => array('type' => 'string', 'description' => 'Código país ISO: ES, FR, DE, IT, PT, UK, US')
            )
        ))
        ->tags('Validación')
        ->describe('Validación con valores permitidos (enum).');


        // =====================================================================
        // VALIDACIÓN DE ARRAYS: minItems, maxItems
        // =====================================================================
        $this->post('/arrays', 'Validación de arrays', function ($req, $res) {
            $schema = array(
                'required' => array('tags', 'productos'),
                'properties' => array(
                    'tags' => array(
                        'type' => 'array',
                        'minItems' => 1,      // Al menos 1 tag
                        'maxItems' => 10      // Máximo 10 tags
                    ),
                    'productos' => array(
                        'type' => 'array',
                        'minItems' => 1,
                        'maxItems' => 100
                    ),
                    'emails_cc' => array(
                        'type' => 'array',
                        'maxItems' => 5       // Máximo 5 emails en CC
                    )
                )
            );

            $validation = $this->validateBody($req, $schema);
            if (!$validation['valid']) {
                return $this->badRequest($res, $validation['error']);
            }

            return $this->created($res, array(
                'message' => 'Arrays válidos',
                'data' => $validation['data'],
                'conteos' => array(
                    'tags' => count($validation['data']['tags']),
                    'productos' => count($validation['data']['productos'])
                )
            ));
        })
        ->body(array(
            'required' => array('tags', 'productos'),
            'properties' => array(
                'tags' => array('type' => 'array', 'description' => 'Array de tags (1-10 items)'),
                'productos' => array('type' => 'array', 'description' => 'Array de IDs de productos (1-100)'),
                'emails_cc' => array('type' => 'array', 'description' => 'Emails en copia (máx 5)')
            )
        ))
        ->tags('Validación')
        ->describe('Validación de arrays con número mínimo y máximo de elementos.');


        // =====================================================================
        // VALIDACIÓN COMPLETA: Formulario de registro
        // =====================================================================
        $this->post('/registro-completo', 'Registro completo', function ($req, $res) {
            $schema = array(
                'required' => array('nombre', 'email', 'password', 'fecha_nacimiento', 'pais'),
                'properties' => array(
                    'nombre' => array(
                        'type' => 'string',
                        'minLength' => 2,
                        'maxLength' => 100
                    ),
                    'email' => array(
                        'type' => 'string',
                        'format' => 'email'
                    ),
                    'password' => array(
                        'type' => 'string',
                        'minLength' => 8,
                        'maxLength' => 100
                    ),
                    'telefono' => array(
                        'type' => 'string',
                        'format' => 'phone'
                    ),
                    'fecha_nacimiento' => array(
                        'type' => 'string',
                        'format' => 'date'
                    ),
                    'pais' => array(
                        'type' => 'string',
                        'enum' => array('ES', 'FR', 'DE', 'IT', 'PT', 'UK', 'US', 'MX', 'AR', 'CO')
                    ),
                    'codigo_postal' => array(
                        'type' => 'string',
                        'pattern' => '^[0-9]{5}$'
                    ),
                    'acepta_terminos' => array(
                        'type' => 'boolean'
                    ),
                    'intereses' => array(
                        'type' => 'array',
                        'minItems' => 1,
                        'maxItems' => 5
                    )
                )
            );

            $validation = $this->validateBody($req, $schema);
            if (!$validation['valid']) {
                // Mostrar todos los errores
                return $this->badRequest($res, implode('. ', $validation['errors']));
            }

            $data = $validation['data'];

            // Validación adicional de negocio
            if (empty($data['acepta_terminos'])) {
                return $this->badRequest($res, 'Debes aceptar los términos y condiciones');
            }

            return $this->created($res, array(
                'message' => 'Usuario registrado correctamente',
                'usuario' => array(
                    'nombre' => $data['nombre'],
                    'email' => $data['email'],
                    'pais' => $data['pais']
                )
            ));
        })
        ->body(array(
            'required' => array('nombre', 'email', 'password', 'fecha_nacimiento', 'pais'),
            'properties' => array(
                'nombre' => array('type' => 'string', 'description' => 'Nombre completo (2-100 chars)'),
                'email' => array('type' => 'string', 'description' => 'Email válido'),
                'password' => array('type' => 'string', 'description' => 'Contraseña (mín 8 chars)'),
                'telefono' => array('type' => 'string', 'description' => 'Teléfono (opcional)'),
                'fecha_nacimiento' => array('type' => 'string', 'description' => 'Fecha YYYY-MM-DD'),
                'pais' => array('type' => 'string', 'description' => 'Código país: ES, FR, DE, IT, PT, UK, US, MX, AR, CO'),
                'codigo_postal' => array('type' => 'string', 'description' => 'CP 5 dígitos'),
                'acepta_terminos' => array('type' => 'boolean', 'description' => 'Debe ser true'),
                'intereses' => array('type' => 'array', 'description' => 'Array de intereses (1-5)')
            )
        ))
        ->tags('Validación')
        ->describe('Ejemplo completo de formulario de registro con todas las validaciones.');
    }
}
