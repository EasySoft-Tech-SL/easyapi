<?php
/* Copyright (C) 2025 Alberto Luque Rivas <aluquerivasdev@gmail.com> | EasySoft Tech S.L <info@easysoft.es>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    easyapi/middleware/RateLimitMiddleware.php
 * \ingroup easyapi
 * \brief   EasyAPI - Middleware de Rate Limiting por usuario/IP
 */

namespace EasyApi\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Middleware de Rate Limiting
 *
 * Almacena los contadores en ficheros temporales del sistema
 * (sys_get_temp_dir()), uno por identificador (usuario o IP), con bloqueo
 * exclusivo para evitar condiciones de carrera bajo concurrencia.
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    /** @var \DoliDB */
    private $db;

    /** @var int Máximo de peticiones */
    private $maxRequests;

    /** @var int Ventana de tiempo en segundos */
    private $windowSeconds;

    /** @var bool Activado */
    private $enabled;

    /**
     * Constructor
     *
     * @param \DoliDB $db
     * @param array $options
     */
    public function __construct($db, array $options = array())
    {
        $this->db = $db;
        $this->maxRequests = isset($options['maxRequests']) ? $options['maxRequests'] : 100;
        $this->windowSeconds = isset($options['windowSeconds']) ? $options['windowSeconds'] : 60;
        $this->enabled = isset($options['enabled']) ? $options['enabled'] : false; // Desactivado por defecto
    }

    /**
     * @inheritDoc
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        if (!$this->enabled) {
            return $handler->handle($request);
        }

        $identifier = $this->getIdentifier($request);
        $remaining = $this->checkRateLimit($identifier);

        if ($remaining < 0) {
            return $this->rateLimitedResponse();
        }

        $response = $handler->handle($request);

        // Añadir cabeceras de rate limit
        return $response
            ->withHeader('X-RateLimit-Limit', (string) $this->maxRequests)
            ->withHeader('X-RateLimit-Remaining', (string) max(0, $remaining))
            ->withHeader('X-RateLimit-Reset', (string) (time() + $this->windowSeconds));
    }

    /**
     * Obtiene el identificador para el rate limit
     *
     * @param Request $request
     * @return string
     */
    private function getIdentifier(Request $request): string
    {
        // Preferir usuario autenticado
        $user = $request->getAttribute('dolibarr_user');
        if ($user) {
            return 'user:' . $user->id;
        }

        // Fallback a IP
        return 'ip:' . $this->getClientIp($request);
    }

    /**
     * Verifica el rate limit y devuelve las peticiones restantes
     *
     * @param string $identifier
     * @return int Peticiones restantes (-1 si excedido)
     */
    private function checkRateLimit(string $identifier): int
    {
        $now = time();
        $windowStart = $now - $this->windowSeconds;
        $key = md5($identifier);

        // Almacén por identificador en fichero temporal, con bloqueo exclusivo
        // para que el ciclo leer-modificar-escribir sea atómico entre peticiones
        // concurrentes.
        $cacheFile = sys_get_temp_dir() . '/easyapi_ratelimit_' . $key;

        $fp = @fopen($cacheFile, 'c+');
        if ($fp === false) {
            // Si no se puede acceder a la caché, no bloquear el servicio
            return $this->maxRequests;
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return $this->maxRequests;
        }

        $contents = stream_get_contents($fp);
        $requests = $contents ? json_decode($contents, true) : array();
        if (!is_array($requests)) {
            $requests = array();
        }

        // Filtrar peticiones fuera de la ventana
        $requests = array_values(array_filter($requests, function ($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        }));

        // Verificar límite
        if (count($requests) >= $this->maxRequests) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return -1;
        }

        // Registrar nueva petición
        $requests[] = $now;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($requests));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        return $this->maxRequests - count($requests);
    }

    /**
     * Obtiene la IP del cliente
     *
     * @param Request $request
     * @return string
     */
    private function getClientIp(Request $request): string
    {
        $serverParams = $request->getServerParams();
        return isset($serverParams['REMOTE_ADDR']) ? $serverParams['REMOTE_ADDR'] : '0.0.0.0';
    }

    /**
     * Respuesta de rate limit excedido
     *
     * @return Response
     */
    private function rateLimitedResponse(): Response
    {
        $response = new SlimResponse();
        $response = $response->withStatus(429);
        $response = $response->withHeader('Content-Type', 'application/json');
        $response = $response->withHeader('Retry-After', (string) $this->windowSeconds);

        $body = json_encode(array(
            'success' => false,
            'error' => array(
                'code' => 429,
                'message' => 'Too Many Requests. Please retry after ' . $this->windowSeconds . ' seconds.'
            )
        ), JSON_PRETTY_PRINT);

        $response->getBody()->write($body);
        return $response;
    }
}
