<?php
/**
 * RequirePermission Middleware - Verifica permisos de Dolibarr
 *
 * Este middleware verifica que el usuario autenticado tenga los permisos
 * necesarios de Dolibarr para acceder al endpoint.
 *
 * @package     EasyAPI
 * @subpackage  Middleware
 * @author      EasyAPI Team
 * @copyright   2024 EasyAPI
 * @license     GPL-3.0
 * @version     1.0.0
 */

namespace EasyAPI\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Nyholm\Psr7\Response as Psr7Response;

/**
 * Middleware para verificar permisos de Dolibarr
 *
 * Uso como middleware:
 *   new RequirePermission('facture->lire')
 *   new RequirePermission('societe->creer')
 *   new RequirePermission('facture->lire,societe->lire', 'ANY')  // Cualquiera de los permisos
 *   new RequirePermission('facture->lire,societe->lire', 'ALL')  // Todos los permisos
 *
 * Uso como callable en route:
 *   $app->get('/path', ...)->add(RequirePermission::check('facture->lire'));
 */
class RequirePermission implements MiddlewareInterface
{
    /** @var array Permisos requeridos */
    private $permissions = array();

    /** @var string Modo de verificación: 'ALL' (todos) o 'ANY' (cualquiera) */
    private $mode = 'ALL';

    /**
     * Constructor
     *
     * @param string $permissions Permisos separados por coma (ej: 'facture->lire,societe->creer')
     * @param string $mode 'ALL' para requerir todos, 'ANY' para requerir al menos uno
     */
    public function __construct(string $permissions, string $mode = 'ALL')
    {
        $this->permissions = array_map('trim', explode(',', $permissions));
        $this->mode = strtoupper($mode) === 'ANY' ? 'ANY' : 'ALL';
    }

    /**
     * Procesa la petición
     *
     * @param Request $request
     * @param RequestHandler $handler
     * @return Response
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $user = $request->getAttribute('dolibarr_user');

        // Si no hay usuario autenticado, denegar acceso
        if (empty($user)) {
            return $this->denyAccess('Authentication required. Please provide DOLAPIKEY.', 401);
        }

        // Verificar permisos
        $hasAccess = $this->checkPermissions($user);

        if (!$hasAccess) {
            $permList = implode(', ', $this->permissions);
            $modeText = $this->mode === 'ANY' ? 'at least one of' : 'all of';
            return $this->denyAccess(
                "Access denied. You need {$modeText} these permissions: {$permList}",
                403
            );
        }

        return $handler->handle($request);
    }

    /**
     * Verifica si el usuario tiene los permisos requeridos
     *
     * @param object $user Objeto usuario de Dolibarr
     * @return bool
     */
    private function checkPermissions($user): bool
    {
        if (empty($this->permissions)) {
            return true;
        }

        $results = array();

        foreach ($this->permissions as $permission) {
            $results[] = $this->userHasPermission($user, $permission);
        }

        if ($this->mode === 'ANY') {
            // Al menos uno debe ser true
            return in_array(true, $results, true);
        }

        // Todos deben ser true
        return !in_array(false, $results, true);
    }

    /**
     * Verifica un permiso específico
     *
     * @param object $user Objeto usuario de Dolibarr
     * @param string $permission Permiso en formato 'module->action' o 'module->sub->action'
     * @return bool
     */
    private function userHasPermission($user, string $permission): bool
    {
        if (empty($permission) || empty($user->rights)) {
            return false;
        }

        // Parsear el permiso: 'facture->lire' o 'facture->facture->lire'
        $parts = explode('->', $permission);
        if (count($parts) < 2) {
            return false;
        }

        // Navegar por la estructura de permisos
        $current = $user->rights;
        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            if (is_object($current) && isset($current->$part)) {
                $current = $current->$part;
            } elseif (is_array($current) && isset($current[$part])) {
                $current = $current[$part];
            } else {
                return false;
            }
        }

        // El valor final debe ser truthy
        return !empty($current);
    }

    /**
     * Genera respuesta de acceso denegado
     *
     * @param string $message Mensaje de error
     * @param int $code Código HTTP
     * @return Response
     */
    private function denyAccess(string $message, int $code): Response
    {
        $response = new Psr7Response($code);
        $response = $response->withHeader('Content-Type', 'application/json');

        $body = json_encode(array(
            'success' => false,
            'error' => array(
                'code' => $code,
                'message' => $message,
                'type' => $code === 401 ? 'authentication_required' : 'permission_denied'
            )
        ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $response->getBody()->write($body);
        return $response;
    }

    /**
     * Método estático para uso como callable
     *
     * Permite usar: ->add(RequirePermission::check('facture->lire'))
     *
     * @param string $permissions Permisos requeridos
     * @param string $mode 'ALL' o 'ANY'
     * @return RequirePermission
     */
    public static function check(string $permissions, string $mode = 'ALL'): self
    {
        return new self($permissions, $mode);
    }

    /**
     * Método estático que requiere CUALQUIERA de los permisos
     *
     * @param string $permissions Permisos separados por coma
     * @return RequirePermission
     */
    public static function any(string $permissions): self
    {
        return new self($permissions, 'ANY');
    }

    /**
     * Método estático que requiere TODOS los permisos
     *
     * @param string $permissions Permisos separados por coma
     * @return RequirePermission
     */
    public static function all(string $permissions): self
    {
        return new self($permissions, 'ALL');
    }
}
