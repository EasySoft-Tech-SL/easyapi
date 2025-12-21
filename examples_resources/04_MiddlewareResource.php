<?php
/**
 * =============================================================================
 * EJEMPLO 04: MIDDLEWARE PERSONALIZADO
 * =============================================================================
 *
 * Demuestra cómo añadir middleware personalizado a los endpoints.
 * Los middleware se ejecutan ANTES del handler y pueden:
 *   - Modificar la request
 *   - Devolver una respuesta directamente (cortocircuitar)
 *   - Añadir headers a la respuesta
 *   - Logging, caché, rate limiting, validaciones, etc.
 *
 * MÉTODO CLAVE:
 *   ->middleware($middlewareCallable)
 *   ->middleware([$middleware1, $middleware2])  // Múltiples
 *
 * =============================================================================
 */

use EasyApi\EasyApiResource;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class MiddlewareResource extends EasyApiResource
{
    protected $description = 'Ejemplos de middleware personalizado';

    protected function registerRoutes(): void
    {
        // =====================================================================
        // MIDDLEWARE: Logging de tiempo de respuesta
        // =====================================================================
        $loggingMiddleware = function (Request $request, RequestHandler $handler): Response {
            // ANTES del handler
            $startTime = microtime(true);
            $path = $request->getUri()->getPath();
            $method = $request->getMethod();

            dol_syslog("EAPI [$method] $path - Iniciando request", LOG_DEBUG);

            // Ejecutar el handler
            $response = $handler->handle($request);

            // DESPUÉS del handler
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            $status = $response->getStatusCode();

            dol_syslog("EAPI [$method] $path - Completado en {$duration}ms (HTTP $status)", LOG_DEBUG);

            // Añadir header con el tiempo de respuesta
            return $response->withHeader('X-Response-Time', $duration . 'ms');
        };

        $this->get('/con-logging', 'Endpoint con logging', function ($req, $res) {
            // Simular algo de trabajo
            usleep(50000); // 50ms

            return $this->ok($res, array(
                'message' => 'Este endpoint tiene middleware de logging',
                'tip' => 'Revisa el header X-Response-Time en la respuesta'
            ));
        })
        ->middleware($loggingMiddleware)
        ->tags('Middleware')
        ->describe('Incluye middleware que mide y loguea el tiempo de respuesta.');


        // =====================================================================
        // MIDDLEWARE: Headers de Caché
        // =====================================================================
        $cacheMiddleware = function (Request $request, RequestHandler $handler): Response {
            $response = $handler->handle($request);

            // Añadir headers de caché
            return $response
                ->withHeader('X-Cache', 'MISS')
                ->withHeader('Cache-Control', 'public, max-age=3600')
                ->withHeader('Expires', gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');
        };

        $this->get('/con-cache', 'Endpoint con caché', function ($req, $res) {
            return $this->ok($res, array(
                'message' => 'Este endpoint tiene headers de caché',
                'generated_at' => date('Y-m-d H:i:s'),
                'cache_duration' => '3600 segundos (1 hora)'
            ));
        })
        ->middleware($cacheMiddleware)
        ->tags('Middleware')
        ->describe('Añade headers de caché HTTP a la respuesta.');


        // =====================================================================
        // MIDDLEWARE: Validar Header Requerido
        // =====================================================================
        $requireHeaderMiddleware = function (Request $request, RequestHandler $handler): Response {
            $apiVersion = $request->getHeaderLine('X-API-Version');

            // Si no viene el header, devolver error
            if (empty($apiVersion)) {
                $response = new \Slim\Psr7\Response();
                $response = $response->withStatus(400);
                $response = $response->withHeader('Content-Type', 'application/json');
                $response->getBody()->write(json_encode(array(
                    'success' => false,
                    'error' => array(
                        'code' => 400,
                        'message' => 'Header X-API-Version es requerido'
                    )
                )));
                return $response;
            }

            // Verificar versión soportada
            if (!in_array($apiVersion, array('1.0', '1.1', '2.0'))) {
                $response = new \Slim\Psr7\Response();
                $response = $response->withStatus(400);
                $response = $response->withHeader('Content-Type', 'application/json');
                $response->getBody()->write(json_encode(array(
                    'success' => false,
                    'error' => array(
                        'code' => 400,
                        'message' => 'X-API-Version no soportada. Versiones válidas: 1.0, 1.1, 2.0'
                    )
                )));
                return $response;
            }

            // Añadir la versión como atributo de la request
            $request = $request->withAttribute('api_version', $apiVersion);

            return $handler->handle($request);
        };

        $this->get('/versionado', 'Endpoint versionado', function ($req, $res) {
            $version = $req->getAttribute('api_version');

            return $this->ok($res, array(
                'message' => 'Endpoint con versionado de API',
                'version_recibida' => $version,
                'versiones_soportadas' => array('1.0', '1.1', '2.0')
            ));
        })
        ->middleware($requireHeaderMiddleware)
        ->tags('Middleware')
        ->describe('Requiere el header X-API-Version con valor válido.');


        // =====================================================================
        // MIDDLEWARE: Rate Limiting Simple
        // =====================================================================
        $rateLimitMiddleware = function (Request $request, RequestHandler $handler): Response {
            // En producción, usar Redis o similar
            // Este es un ejemplo simplificado con archivo temporal

            $clientIp = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown';
            $cacheFile = sys_get_temp_dir() . '/eapi_ratelimit_' . md5($clientIp) . '.txt';

            $maxRequests = 10; // Máximo 10 requests
            $windowSeconds = 60; // Por minuto

            $requests = array();
            if (file_exists($cacheFile)) {
                $requests = json_decode(file_get_contents($cacheFile), true) ?: array();
            }

            // Limpiar requests antiguos
            $now = time();
            $requests = array_filter($requests, function ($timestamp) use ($now, $windowSeconds) {
                return ($now - $timestamp) < $windowSeconds;
            });

            // Verificar límite
            if (count($requests) >= $maxRequests) {
                $response = new \Slim\Psr7\Response();
                $response = $response->withStatus(429);
                $response = $response->withHeader('Content-Type', 'application/json');
                $response = $response->withHeader('X-RateLimit-Limit', $maxRequests);
                $response = $response->withHeader('X-RateLimit-Remaining', 0);
                $response = $response->withHeader('Retry-After', $windowSeconds);
                $response->getBody()->write(json_encode(array(
                    'success' => false,
                    'error' => array(
                        'code' => 429,
                        'message' => 'Demasiadas peticiones. Intenta de nuevo en ' . $windowSeconds . ' segundos'
                    )
                )));
                return $response;
            }

            // Añadir request actual
            $requests[] = $now;
            file_put_contents($cacheFile, json_encode($requests));

            $remaining = $maxRequests - count($requests);

            // Ejecutar handler y añadir headers
            $response = $handler->handle($request);

            return $response
                ->withHeader('X-RateLimit-Limit', $maxRequests)
                ->withHeader('X-RateLimit-Remaining', $remaining)
                ->withHeader('X-RateLimit-Reset', $now + $windowSeconds);
        };

        $this->get('/rate-limited', 'Endpoint con rate limit', function ($req, $res) {
            return $this->ok($res, array(
                'message' => 'Endpoint con rate limiting',
                'limite' => '10 requests por minuto',
                'tip' => 'Revisa los headers X-RateLimit-*'
            ));
        })
        ->middleware($rateLimitMiddleware)
        ->tags('Middleware')
        ->describe('Rate limiting: máximo 10 peticiones por minuto.');


        // =====================================================================
        // MIDDLEWARE: Validación de JSON Schema
        // =====================================================================
        $jsonSchemaMiddleware = function (Request $request, RequestHandler $handler): Response {
            if (in_array($request->getMethod(), array('POST', 'PUT', 'PATCH'))) {
                $contentType = $request->getHeaderLine('Content-Type');

                if (strpos($contentType, 'application/json') === false) {
                    $response = new \Slim\Psr7\Response();
                    $response = $response->withStatus(415);
                    $response = $response->withHeader('Content-Type', 'application/json');
                    $response->getBody()->write(json_encode(array(
                        'success' => false,
                        'error' => array(
                            'code' => 415,
                            'message' => 'Content-Type debe ser application/json'
                        )
                    )));
                    return $response;
                }

                // Verificar que el body sea JSON válido
                $body = json_decode((string) $request->getBody(), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $response = new \Slim\Psr7\Response();
                    $response = $response->withStatus(400);
                    $response = $response->withHeader('Content-Type', 'application/json');
                    $response->getBody()->write(json_encode(array(
                        'success' => false,
                        'error' => array(
                            'code' => 400,
                            'message' => 'JSON inválido: ' . json_last_error_msg()
                        )
                    )));
                    return $response;
                }
            }

            return $handler->handle($request);
        };

        $this->post('/json-validado', 'Endpoint con validación JSON', function ($req, $res) {
            $body = $this->getBody($req);

            return $this->created($res, array(
                'message' => 'JSON recibido correctamente',
                'data' => $body
            ));
        })
        ->middleware($jsonSchemaMiddleware)
        ->tags('Middleware')
        ->describe('Valida que el Content-Type sea application/json y el body sea JSON válido.');


        // =====================================================================
        // MÚLTIPLES MIDDLEWARES EN UN ENDPOINT
        // =====================================================================
        $this->get('/multi-middleware', 'Múltiples middlewares', function ($req, $res) {
            return $this->ok($res, array(
                'message' => 'Este endpoint tiene logging + caché',
                'middlewares' => array('logging', 'cache')
            ));
        })
        ->middleware($loggingMiddleware)
        ->middleware($cacheMiddleware)  // Se pueden encadenar
        ->tags('Middleware')
        ->describe('Endpoint con múltiples middlewares encadenados.');
    }
}
