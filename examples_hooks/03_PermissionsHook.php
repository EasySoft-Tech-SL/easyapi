<?php
/**
 * =============================================================================
 * EJEMPLO 03: PERMISOS EN HOOKS
 * =============================================================================
 *
 * Demuestra cómo verificar permisos de Dolibarr en hooks.
 *
 * En hooks necesitas verificar permisos MANUALMENTE:
 *   - Obtener el usuario del atributo 'dolibarr_user'
 *   - Verificar $user->rights->modulo->permiso
 *
 * =============================================================================
 */

class ActionsPermisosApi
{
    public $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Helper para verificar permisos
     */
    private function checkPermission($user, $permission)
    {
        if (!$user) {
            return false;
        }

        // Admin siempre tiene acceso
        if (!empty($user->admin)) {
            return true;
        }

        // Parsear permiso: 'modulo->permiso' o 'modulo->sub->permiso'
        $parts = explode('->', $permission);
        if (count($parts) < 2) {
            return false;
        }

        $module = array_shift($parts);
        $rights = $user->rights;

        if (!isset($rights->$module)) {
            return false;
        }

        $current = $rights->$module;
        foreach ($parts as $part) {
            if (!isset($current->$part)) {
                return false;
            }
            $current = $current->$part;
        }

        return !empty($current);
    }

    /**
     * Helper para respuesta de error de permisos
     */
    private function forbiddenResponse($response, $message = 'No tienes permisos para esta acción')
    {
        $response = $response->withStatus(403);
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode(array(
            'success' => false,
            'error' => array(
                'code' => 403,
                'message' => $message
            )
        )));
        return $response;
    }

    public function easyApiRegisterRoutes($parameters, &$object, &$action)
    {
        $app = $parameters['app'];
        $api = $object;
        $self = $this;

        // =====================================================================
        // Endpoint que requiere un permiso específico
        // =====================================================================
        $app->get('/permisos/facturas', function ($request, $response) use ($self) {
            // Obtener usuario autenticado
            $user = $request->getAttribute('dolibarr_user');

            // Verificar permiso
            if (!$self->checkPermission($user, 'facture->lire')) {
                return $self->forbiddenResponse($response, 'Requiere permiso facture->lire');
            }

            // Si tiene permiso, continuar
            $data = array(
                'success' => true,
                'data' => array(
                    'message' => 'Tienes acceso a ver facturas',
                    'usuario' => $user->login,
                    'permiso' => 'facture->lire'
                )
            );

            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($data));
            return $response;
        });

        // =====================================================================
        // Endpoint que requiere permiso de escritura
        // =====================================================================
        $app->post('/permisos/facturas', function ($request, $response) use ($self) {
            $user = $request->getAttribute('dolibarr_user');

            // Verificar permiso de creación
            if (!$self->checkPermission($user, 'facture->creer')) {
                return $self->forbiddenResponse($response, 'Requiere permiso facture->creer');
            }

            $data = array(
                'success' => true,
                'data' => array(
                    'message' => 'Tienes permiso para crear facturas (simulado)',
                    'usuario' => $user->login
                )
            );

            $response = $response->withStatus(201);
            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($data));
            return $response;
        });

        // =====================================================================
        // Endpoint con múltiples permisos (OR)
        // =====================================================================
        $app->get('/permisos/documentos', function ($request, $response) use ($self) {
            $user = $request->getAttribute('dolibarr_user');

            // Verificar si tiene AL MENOS UNO de los permisos
            $hasFacturas = $self->checkPermission($user, 'facture->lire');
            $hasPedidos = $self->checkPermission($user, 'commande->lire');
            $hasPresupuestos = $self->checkPermission($user, 'propal->lire');

            if (!$hasFacturas && !$hasPedidos && !$hasPresupuestos) {
                return $self->forbiddenResponse(
                    $response,
                    'Requiere al menos uno de: facture->lire, commande->lire, propal->lire'
                );
            }

            $data = array(
                'success' => true,
                'data' => array(
                    'message' => 'Tienes acceso a documentos',
                    'permisos' => array(
                        'facture->lire' => $hasFacturas,
                        'commande->lire' => $hasPedidos,
                        'propal->lire' => $hasPresupuestos
                    )
                )
            );

            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($data));
            return $response;
        });

        // =====================================================================
        // Endpoint con múltiples permisos (AND)
        // =====================================================================
        $app->get('/permisos/contabilidad', function ($request, $response) use ($self) {
            $user = $request->getAttribute('dolibarr_user');

            // Verificar que tiene TODOS los permisos
            $permsRequired = array('compta->resultat->lire', 'compta->ventilation->lire');
            $missingPerms = array();

            foreach ($permsRequired as $perm) {
                if (!$self->checkPermission($user, $perm)) {
                    $missingPerms[] = $perm;
                }
            }

            if (!empty($missingPerms)) {
                return $self->forbiddenResponse(
                    $response,
                    'Faltan permisos: ' . implode(', ', $missingPerms)
                );
            }

            $data = array(
                'success' => true,
                'data' => array(
                    'message' => 'Tienes todos los permisos de contabilidad',
                    'permisos_verificados' => $permsRequired
                )
            );

            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($data));
            return $response;
        });

        // =====================================================================
        // Endpoint solo para administradores
        // =====================================================================
        $app->get('/permisos/admin-only', function ($request, $response) use ($self) {
            $user = $request->getAttribute('dolibarr_user');

            if (empty($user->admin)) {
                return $self->forbiddenResponse($response, 'Solo administradores');
            }

            $data = array(
                'success' => true,
                'data' => array(
                    'message' => 'Acceso de administrador',
                    'admin' => $user->login
                )
            );

            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($data));
            return $response;
        });

        // =====================================================================
        // Endpoint que muestra permisos del usuario
        // =====================================================================
        $app->get('/permisos/mis-permisos', function ($request, $response) use ($self) {
            $user = $request->getAttribute('dolibarr_user');

            $permisosComunes = array(
                'societe->lire', 'societe->creer', 'societe->supprimer',
                'facture->lire', 'facture->creer', 'facture->supprimer',
                'commande->lire', 'commande->creer',
                'propal->lire', 'propal->creer',
                'produit->lire', 'produit->creer'
            );

            $misPermisos = array();
            foreach ($permisosComunes as $perm) {
                $misPermisos[$perm] = $self->checkPermission($user, $perm);
            }

            $data = array(
                'success' => true,
                'data' => array(
                    'usuario' => array(
                        'id' => $user->id,
                        'login' => $user->login,
                        'admin' => (bool) $user->admin
                    ),
                    'permisos' => $misPermisos
                )
            );

            $response = $response->withHeader('Content-Type', 'application/json');
            $response->getBody()->write(json_encode($data));
            return $response;
        });

        // =====================================================================
        // Documentación OpenAPI
        // =====================================================================
        $api->hookRoutes[] = array(
            'methods' => array('GET'),
            'path' => '/permisos/facturas',
            'summary' => '🪝 Ver facturas (requiere permiso)',
            'description' => 'Requiere permiso facture->lire',
            'tags' => array('Permisos (Hook)'),
            'security' => array(array('api_key' => array()))
        );

        $api->hookRoutes[] = array(
            'methods' => array('POST'),
            'path' => '/permisos/facturas',
            'summary' => '🪝 Crear factura (requiere permiso)',
            'description' => 'Requiere permiso facture->creer',
            'tags' => array('Permisos (Hook)'),
            'security' => array(array('api_key' => array()))
        );

        $api->hookRoutes[] = array(
            'methods' => array('GET'),
            'path' => '/permisos/documentos',
            'summary' => '🪝 Ver documentos (OR)',
            'description' => 'Requiere AL MENOS UNO de: facture->lire, commande->lire, propal->lire',
            'tags' => array('Permisos (Hook)'),
            'security' => array(array('api_key' => array()))
        );

        $api->hookRoutes[] = array(
            'methods' => array('GET'),
            'path' => '/permisos/contabilidad',
            'summary' => '🪝 Contabilidad (AND)',
            'description' => 'Requiere TODOS: compta->resultat->lire Y compta->ventilation->lire',
            'tags' => array('Permisos (Hook)'),
            'security' => array(array('api_key' => array()))
        );

        $api->hookRoutes[] = array(
            'methods' => array('GET'),
            'path' => '/permisos/admin-only',
            'summary' => '🪝 Solo admin',
            'description' => 'Solo accesible por administradores',
            'tags' => array('Permisos (Hook)'),
            'security' => array(array('api_key' => array()))
        );

        $api->hookRoutes[] = array(
            'methods' => array('GET'),
            'path' => '/permisos/mis-permisos',
            'summary' => '🪝 Ver mis permisos',
            'description' => 'Muestra los permisos del usuario autenticado',
            'tags' => array('Permisos (Hook)'),
            'security' => array(array('api_key' => array()))
        );

        return 0;
    }
}
