<?php
/**
 * =============================================================================
 * EJEMPLO 02: ENDPOINTS PÚBLICOS (Sin Autenticación)
 * =============================================================================
 *
 * Demuestra cómo crear endpoints que NO requieren autenticación.
 * Útil para: health checks, versión de API, datos públicos, webhooks, etc.
 *
 * MÉTODOS CLAVE:
 *   - ->public()              → Hace el endpoint público (sin auth)
 *   - $publicByDefault = true → Todos los endpoints son públicos por defecto
 *
 * =============================================================================
 */

use EasyApi\EasyApiResource;

class PublicApiResource extends EasyApiResource
{
    protected $description = 'Endpoints públicos de la API';

    /**
     * Si true, TODAS las rutas son públicas por defecto.
     * Cada ruta puede usar ->private() para requerir auth.
     */
    protected $publicByDefault = false; // Lo dejamos en false para usar ->public()

    protected function registerRoutes(): void
    {
        // =====================================================================
        // ENDPOINT PÚBLICO: Health Check / Ping
        // =====================================================================
        $this->get('/ping', 'Health check', function ($req, $res) {
            return $this->ok($res, array(
                'status' => 'ok',
                'timestamp' => time(),
                'datetime' => date('Y-m-d H:i:s'),
                'message' => 'API funcionando correctamente'
            ));
        })
        ->public()  // <-- CLAVE: Este endpoint NO requiere DOLAPIKEY
        ->tags('Público')
        ->describe('Verifica que la API está funcionando. No requiere autenticación.');


        // =====================================================================
        // ENDPOINT PÚBLICO: Versión de la API
        // =====================================================================
        $this->get('/version', 'Versión de API', function ($req, $res) {
            return $this->ok($res, array(
                'api' => 'EasyAPI',
                'version' => '1.0.0',
                'dolibarr_version' => DOL_VERSION,
                'php_version' => PHP_VERSION,
                'environment' => !empty($this->conf->global->MAIN_PROD) ? 'production' : 'development'
            ));
        })
        ->public()
        ->tags('Público')
        ->describe('Información de versión de la API. No requiere autenticación.');


        // =====================================================================
        // ENDPOINT PÚBLICO: Catálogo público de productos
        // =====================================================================
        $this->get('/catalogo', 'Catálogo público', function ($req, $res) {
            $limit = (int) $this->query($req, 'limit', 20);

            // Solo productos activos y públicos (tosell = 1)
            $sql = "SELECT rowid as id, ref, label, price
                    FROM " . MAIN_DB_PREFIX . "product
                    WHERE tosell = 1
                    AND entity = " . (int) $this->conf->entity . "
                    LIMIT " . min($limit, 100); // Máximo 100

            $productos = $this->fetchAll($sql);

            return $this->ok($res, array(
                'productos' => $productos,
                'total' => count($productos)
            ));
        })
        ->public()
        ->queryParams(array(
            'limit' => array('type' => 'integer', 'description' => 'Máximo de productos (default: 20, max: 100)')
        ))
        ->tags('Público')
        ->describe('Lista de productos disponibles para venta. No requiere autenticación.');


        // =====================================================================
        // ENDPOINT PÚBLICO: Webhook receptor
        // =====================================================================
        $this->post('/webhook/stripe', 'Webhook Stripe', function ($req, $res) {
            $body = $this->getBody($req);

            // Log del webhook recibido
            $this->log('Webhook Stripe recibido: ' . json_encode($body), LOG_INFO);

            // Procesar según el tipo de evento
            $eventType = isset($body['type']) ? $body['type'] : 'unknown';

            switch ($eventType) {
                case 'payment_intent.succeeded':
                    // Procesar pago exitoso
                    $this->log('Pago exitoso: ' . $body['data']['object']['id'], LOG_INFO);
                    break;

                case 'payment_intent.failed':
                    // Procesar pago fallido
                    $this->log('Pago fallido: ' . $body['data']['object']['id'], LOG_WARNING);
                    break;

                default:
                    $this->log('Evento no manejado: ' . $eventType, LOG_DEBUG);
            }

            // Stripe espera un 200 OK
            return $this->ok($res, array('received' => true));
        })
        ->public()
        ->tags('Webhooks')
        ->describe('Endpoint para recibir webhooks de Stripe. No requiere autenticación.');


        // =====================================================================
        // ENDPOINT PÚBLICO: Verificar si un email ya existe
        // =====================================================================
        $this->get('/check-email', 'Verificar email', function ($req, $res) {
            $email = $this->query($req, 'email', '');

            if (empty($email)) {
                return $this->badRequest($res, 'El parámetro email es requerido');
            }

            // Buscar si existe el email
            $sql = "SELECT COUNT(*) as count
                    FROM " . MAIN_DB_PREFIX . "socpeople
                    WHERE email = '" . $this->db->escape($email) . "'";

            $result = $this->fetchOne($sql);
            $exists = $result && $result['count'] > 0;

            return $this->ok($res, array(
                'email' => $email,
                'exists' => $exists,
                'available' => !$exists
            ));
        })
        ->public()
        ->queryParams(array(
            'email' => array('type' => 'string', 'description' => 'Email a verificar')
        ))
        ->tags('Público')
        ->describe('Verifica si un email ya está registrado. No requiere autenticación.');


        // =====================================================================
        // ENDPOINT PRIVADO (para comparar)
        // =====================================================================
        $this->get('/datos-sensibles', 'Datos sensibles', function ($req, $res) {
            $user = $this->getUser($req);

            return $this->ok($res, array(
                'message' => 'Estos datos requieren autenticación',
                'usuario' => $user->login,
                'datos_privados' => array(
                    'ventas_totales' => 150000.00,
                    'clientes_activos' => 234
                )
            ));
        })
        // Sin ->public(), requiere autenticación por defecto
        ->tags('Privado')
        ->describe('Este endpoint SÍ requiere autenticación (DOLAPIKEY).');
    }
}
