<?php
/* Copyright (C) 2025 Alberto Luque Rivas <aluquerivasdev@gmail.com>
 *
 * EasyAPI - Clase de Hooks BASE (Plantilla vacía)
 *
 * Esta es una plantilla base para crear hooks de EasyAPI.
 * Copia este archivo y renómbralo según tu módulo.
 *
 * INSTRUCCIONES:
 * 1. Copia este archivo a tu módulo: class/actions_tumodulo.class.php
 * 2. Renombra la clase a ActionsTumodulo
 * 3. En tu modTumodulo.class.php añade: 'hooks' => array('data' => array('easyapi'))
 * 4. Implementa tus rutas en easyapiRegisterRoutes()
 */

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Clase de acciones/hooks para EasyAPI - PLANTILLA BASE
 */
class ActionsEasyapi
{
    /** @var \DoliDB */
    public $db;

    /** @var string */
    public $error = '';

    /** @var array */
    public $errors = array();

    /** @var array */
    public $results = array();

    /** @var string */
    public $resprints = '';

    /** @var bool Evitar registro duplicado */
    private static $routesRegistered = false;

    /**
     * Constructor
     *
     * @param \DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Hook para registrar rutas en EasyAPI
     *
     * @param array $parameters Contiene: 'app' (Slim), 'db' (DoliDB), 'api' (ApiEasyApi), 'config'
     * @param object $object
     * @param string $action
     * @param HookManager $hookmanager
     * @return int 0=OK, <0=Error
     */
    public function easyapiRegisterRoutes($parameters, &$object, &$action, $hookmanager)
    {
        // Evitar registro duplicado
        if (self::$routesRegistered) {
            return 0;
        }
        self::$routesRegistered = true;

        /** @var \Slim\App $app */
        $app = $parameters['app'];

        /** @var \DoliDB $db */
        $db = $parameters['db'];

        /** @var \ApiEasyApi $api */
        $api = $parameters['api'];

        // =====================================================================
        // REGISTRA TUS RUTAS AQUÍ
        // =====================================================================

        // Ejemplo: GET simple
        // $app->get('/mimodulo/info', function (Request $request, Response $response) use ($api) {
        //     return $api->successResponse($response, array('version' => '1.0'));
        // });
        // $api->addRoute(array('method' => 'GET', 'path' => '/mimodulo/info', 'summary' => 'Info', 'tags' => array('MiModulo')));

        // Ejemplo: GET con parámetro
        // $app->get('/mimodulo/item/{id}', function (Request $request, Response $response, array $args) use ($db, $api) {
        //     $id = (int) $args['id'];
        //     return $api->successResponse($response, array('id' => $id));
        // });

        // Ejemplo: POST
        // $app->post('/mimodulo/item', function (Request $request, Response $response) use ($db, $api) {
        //     $body = json_decode((string) $request->getBody(), true);
        //     return $api->successResponse($response, array('created' => true), 201);
        // });

        // Ejemplo: Group de rutas
        // $app->group('/mimodulo', function ($group) use ($db, $api) {
        //     $group->get('/items', function ($req, $res) use ($api) { ... });
        //     $group->post('/items', function ($req, $res) use ($api) { ... });
        // });

        return 0;
    }
}
