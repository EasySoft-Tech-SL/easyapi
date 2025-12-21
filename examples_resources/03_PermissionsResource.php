<?php
/**
 * =============================================================================
 * EJEMPLO 03: CONTROL DE PERMISOS
 * =============================================================================
 *
 * Demuestra cómo controlar el acceso a endpoints según permisos de Dolibarr.
 *
 * MÉTODOS CLAVE:
 *   - ->requirePermission('modulo->permiso')           → Permiso único
 *   - ->requirePermission(['perm1', 'perm2'], 'any')   → Cualquiera de los permisos (OR)
 *   - ->requirePermission(['perm1', 'perm2'], 'all')   → Todos los permisos (AND)
 *   - $this->hasPermission($req, 'modulo->permiso')    → Verificar en el handler
 *
 * FORMATO DE PERMISOS:
 *   - 'facture->lire'          → $user->rights->facture->lire
 *   - 'societe->creer'         → $user->rights->societe->creer
 *   - 'compta->resultat->lire' → $user->rights->compta->resultat->lire
 *
 * =============================================================================
 */

use EasyApi\EasyApiResource;

class PermissionsResource extends EasyApiResource
{
    protected $description = 'Ejemplos de control de permisos';

    /**
     * Permiso por defecto para TODAS las rutas del recurso.
     * Se puede sobrescribir con ->requirePermission() en cada ruta.
     */
    protected $defaultPermission = null; // Ej: 'mymodule->lire'

    protected function registerRoutes(): void
    {
        // =====================================================================
        // PERMISO SIMPLE: Un solo permiso requerido
        // =====================================================================
        $this->get('/facturas', 'Ver facturas', function ($req, $res) {
            // Si llegamos aquí, el usuario tiene el permiso facture->lire
            $user = $this->getUser($req);

            $sql = "SELECT rowid, ref, total_ttc, fk_statut
                    FROM " . MAIN_DB_PREFIX . "facture
                    WHERE entity = " . (int) $this->conf->entity . "
                    LIMIT 10";

            return $this->ok($res, array(
                'message' => "Acceso concedido a {$user->login}",
                'permiso_requerido' => 'facture->lire',
                'facturas' => $this->fetchAll($sql)
            ));
        })
        ->requirePermission('facture->lire')  // <-- PERMISO SIMPLE
        ->tags('Permisos - Simple')
        ->describe('Requiere permiso facture->lire para ver facturas.');


        // =====================================================================
        // PERMISO SIMPLE: Crear terceros
        // =====================================================================
        $this->post('/terceros', 'Crear tercero', function ($req, $res) {
            $schema = array(
                'required' => array('nom'),
                'properties' => array(
                    'nom' => array('type' => 'string', 'minLength' => 2)
                )
            );

            $validation = $this->validateBody($req, $schema);
            if (!$validation['valid']) {
                return $this->badRequest($res, $validation['error']);
            }

            return $this->created($res, array(
                'message' => 'Tercero creado (simulado)',
                'data' => $validation['data']
            ));
        })
        ->requirePermission('societe->creer')  // <-- PERMISO DE ESCRITURA
        ->body(array(
            'required' => array('nom'),
            'properties' => array(
                'nom' => array('type' => 'string', 'description' => 'Nombre del tercero')
            )
        ))
        ->tags('Permisos - Simple')
        ->describe('Requiere permiso societe->creer para crear terceros.');


        // =====================================================================
        // PERMISO OR: Cualquiera de los permisos (mode = 'any')
        // =====================================================================
        $this->get('/documentos', 'Ver documentos', function ($req, $res) {
            // El usuario tiene AL MENOS UNO de los permisos
            $user = $this->getUser($req);

            // Podemos verificar cuál tiene para mostrar contenido específico
            $tieneFacturas = $this->hasPermission($req, 'facture->lire');
            $tienePedidos = $this->hasPermission($req, 'commande->lire');
            $tienePresupuestos = $this->hasPermission($req, 'propal->lire');

            $documentos = array();

            if ($tieneFacturas) {
                $documentos['facturas'] = $this->fetchAll(
                    "SELECT rowid, ref FROM " . MAIN_DB_PREFIX . "facture LIMIT 5"
                );
            }
            if ($tienePedidos) {
                $documentos['pedidos'] = $this->fetchAll(
                    "SELECT rowid, ref FROM " . MAIN_DB_PREFIX . "commande LIMIT 5"
                );
            }
            if ($tienePresupuestos) {
                $documentos['presupuestos'] = $this->fetchAll(
                    "SELECT rowid, ref FROM " . MAIN_DB_PREFIX . "propal LIMIT 5"
                );
            }

            return $this->ok($res, array(
                'usuario' => $user->login,
                'permisos' => array(
                    'facture->lire' => $tieneFacturas,
                    'commande->lire' => $tienePedidos,
                    'propal->lire' => $tienePresupuestos
                ),
                'documentos' => $documentos
            ));
        })
        ->requirePermission(
            array('facture->lire', 'commande->lire', 'propal->lire'),
            'any'  // <-- CUALQUIERA de estos permisos (OR)
        )
        ->tags('Permisos - OR')
        ->describe('Requiere AL MENOS UNO de: facture->lire, commande->lire o propal->lire');


        // =====================================================================
        // PERMISO AND: Todos los permisos requeridos (mode = 'all')
        // =====================================================================
        $this->get('/contabilidad/completa', 'Acceso contabilidad completa', function ($req, $res) {
            // El usuario tiene TODOS los permisos requeridos
            return $this->ok($res, array(
                'message' => 'Acceso completo a contabilidad',
                'permisos_verificados' => array(
                    'compta->resultat->lire',
                    'compta->ventilation->lire'
                ),
                'datos' => array(
                    'balance' => 150000.00,
                    'resultado' => 35000.00
                )
            ));
        })
        ->requirePermission(
            array('compta->resultat->lire', 'compta->ventilation->lire'),
            'all'  // <-- TODOS los permisos (AND)
        )
        ->tags('Permisos - AND')
        ->describe('Requiere TODOS: compta->resultat->lire Y compta->ventilation->lire');


        // =====================================================================
        // VERIFICACIÓN MANUAL EN EL HANDLER
        // =====================================================================
        $this->get('/acciones-condicionales', 'Acciones según permisos', function ($req, $res) {
            $user = $this->getUser($req);
            $acciones = array();

            // Verificar permisos manualmente para mostrar acciones disponibles
            if ($this->hasPermission($req, 'facture->lire')) {
                $acciones[] = 'ver_facturas';
            }
            if ($this->hasPermission($req, 'facture->creer')) {
                $acciones[] = 'crear_facturas';
            }
            if ($this->hasPermission($req, 'facture->supprimer')) {
                $acciones[] = 'eliminar_facturas';
            }
            if ($this->hasPermission($req, 'societe->lire')) {
                $acciones[] = 'ver_terceros';
            }
            if ($this->hasPermission($req, 'societe->creer')) {
                $acciones[] = 'crear_terceros';
            }

            return $this->ok($res, array(
                'usuario' => $user->login,
                'es_admin' => (bool) $user->admin,
                'acciones_disponibles' => $acciones,
                'total_acciones' => count($acciones)
            ));
        })
        // Sin requirePermission - cualquier usuario autenticado
        ->tags('Permisos - Manual')
        ->describe('Muestra las acciones disponibles según los permisos del usuario.');


        // =====================================================================
        // PERMISO CON SUBPERMISOS (3 niveles)
        // =====================================================================
        $this->get('/accounting/export', 'Exportar contabilidad', function ($req, $res) {
            return $this->ok($res, array(
                'message' => 'Acceso a exportación de contabilidad',
                'permiso' => 'accounting->export->export',
                'formatos' => array('csv', 'pdf', 'xml')
            ));
        })
        ->requirePermission('accounting->export->export')  // <-- 3 NIVELES
        ->tags('Permisos - Multinivel')
        ->describe('Ejemplo de permiso con 3 niveles: accounting->export->export');


        // =====================================================================
        // ACCESO SOLO ADMIN
        // =====================================================================
        $this->get('/admin/config', 'Configuración admin', function ($req, $res) {
            $user = $this->getUser($req);

            // Verificar si es admin
            if (empty($user->admin)) {
                return $this->forbidden($res, 'Solo administradores pueden acceder');
            }

            return $this->ok($res, array(
                'message' => 'Configuración de administrador',
                'admin' => $user->login,
                'config' => array(
                    'MAIN_LANG_DEFAULT' => $this->conf->global->MAIN_LANG_DEFAULT,
                    'MAIN_THEME' => $this->conf->global->MAIN_THEME
                )
            ));
        })
        ->tags('Permisos - Admin')
        ->describe('Solo accesible para usuarios administradores.');
    }
}
