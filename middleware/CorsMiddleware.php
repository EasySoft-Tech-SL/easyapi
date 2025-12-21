<?php
/* Copyright (C) 2025 Alberto Luque Rivas <aluquerivasdev@gmail.com>
 *
 * EasyAPI - Middleware de CORS
 *
 * Maneja las cabeceras CORS para permitir peticiones cross-origin
 */

namespace EasyApi\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Middleware CORS para peticiones cross-origin
 */
class CorsMiddleware implements MiddlewareInterface
{
    /** @var array */
    private $options;

    /**
     * Constructor
     *
     * @param array $options
     */
    public function __construct(array $options = array())
    {
        $this->options = array_merge(array(
            'origin' => '*',
            'methods' => array('GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'),
            'headers' => array('Content-Type', 'Authorization', 'DOLAPIKEY', 'X-Requested-With'),
            'credentials' => false,
            'maxAge' => 86400, // 24 horas
            'exposeHeaders' => array('X-Total-Count', 'X-Page', 'X-Per-Page')
        ), $options);
    }

    /**
     * @inheritDoc
     */
    public function process(Request $request, RequestHandler $handler): Response
    {
        // Pre-flight request
        if ($request->getMethod() === 'OPTIONS') {
            $response = new SlimResponse();
            return $this->addCorsHeaders($response, $request);
        }

        $response = $handler->handle($request);
        return $this->addCorsHeaders($response, $request);
    }

    /**
     * Añade las cabeceras CORS a la respuesta
     *
     * @param Response $response
     * @param Request $request
     * @return Response
     */
    private function addCorsHeaders(Response $response, Request $request): Response
    {
        $origin = $request->getHeaderLine('Origin');

        // Determinar el origen permitido
        $allowedOrigin = $this->options['origin'];
        if (is_array($allowedOrigin)) {
            $allowedOrigin = in_array($origin, $allowedOrigin) ? $origin : $allowedOrigin[0];
        }

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $allowedOrigin)
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->options['methods']))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->options['headers']))
            ->withHeader('Access-Control-Max-Age', (string) $this->options['maxAge']);

        if (!empty($this->options['exposeHeaders'])) {
            $response = $response->withHeader(
                'Access-Control-Expose-Headers',
                implode(', ', $this->options['exposeHeaders'])
            );
        }

        if ($this->options['credentials']) {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }
}
