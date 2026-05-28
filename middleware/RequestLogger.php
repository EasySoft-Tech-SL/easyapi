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
 * \file    easyapi/middleware/RequestLogger.php
 * \ingroup easyapi
 * \brief   EasyAPI - Middleware de Logging para auditoría de peticiones
 */

namespace EasyApi\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

/**
 * Middleware de Logging para auditoría de peticiones
 */
class RequestLogger implements MiddlewareInterface
{
    /** @var \DoliDB */
    private $db;

    /** @var bool */
    private $enabled;

    /** @var string */
    private $logFile;

    /**
     * Constructor
     *
     * @param \DoliDB $db
     * @param array $options
     */
    public function __construct($db, array $options = array())
    {
        $this->db = $db;
        $this->enabled = isset($options['enabled']) ? $options['enabled'] : true;
        $this->logFile = isset($options['logFile']) ? $options['logFile'] : null;
    }

    /**
     * @inheritDoc
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        $startTime = microtime(true);

        // Ejecutar la petición
        $response = $handler->handle($request);

        if ($this->enabled) {
            $endTime = microtime(true);
            $duration = round(($endTime - $startTime) * 1000, 2); // ms

            $this->logRequest($request, $response, $duration);
        }

        return $response;
    }

    /**
     * Registra la petición
     *
     * @param Request $request
     * @param Response $response
     * @param float $duration
     */
    private function logRequest(Request $request, Response $response, float $duration): void
    {
        $user = $request->getAttribute('dolibarr_user');
        $userId = $user ? $user->id : 0;
        $userName = $user ? $user->login : 'anonymous';

        // Redactar credenciales que puedan venir en el query string para no
        // registrarlas en claro (DOLAPIKEY / api_key).
        $uri = (string) $request->getUri();
        $uri = preg_replace('/([?&](?:DOLAPIKEY|api_key)=)[^&]*/i', '$1***', $uri);

        $logEntry = array(
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $request->getMethod(),
            'uri' => $uri,
            'status' => $response->getStatusCode(),
            'duration_ms' => $duration,
            'user_id' => $userId,
            'user_login' => $userName,
            'ip' => $this->getClientIp($request),
            'user_agent' => $request->getHeaderLine('User-Agent')
        );

        // Log a archivo si está configurado
        if ($this->logFile) {
            $line = json_encode($logEntry) . PHP_EOL;
            file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
        }

        // Log usando el sistema de Dolibarr si está disponible
        if (function_exists('dol_syslog')) {
            dol_syslog(
                "EasyAPI: {$logEntry['method']} {$logEntry['uri']} - {$logEntry['status']} - {$duration}ms - User: {$userName}",
                LOG_INFO
            );
        }
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

        // Headers de proxy
        $headers = array(
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        );

        foreach ($headers as $header) {
            if (!empty($serverParams[$header])) {
                $ips = explode(',', $serverParams[$header]);
                return trim($ips[0]);
            }
        }

        return '0.0.0.0';
    }
}
