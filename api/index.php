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
 * \file    easyapi/api/index.php
 * \ingroup easyapi
 * \brief   EasyAPI - Headless API for Dolibarr with Slim Framework - Entrypoint principal
 */

// Prevenir generación de tokens y HTML innecesario
define('NOTOKENRENEWAL', '1');
define('NOREQUIREMENU', '1');
define('NOREQUIREHTML', '1');
define('NOREQUIREAJAX', '1');
define('NOCSRFCHECK', '1');
define('NOLOGIN', '1'); // La autenticación se maneja via DOLAPIKEY en el middleware

// Cargar entorno Dolibarr
$res = 0;
// Intentar encontrar main.inc.php
$dirparts = explode(DIRECTORY_SEPARATOR, __DIR__);
for ($i = count($dirparts) - 1; $i >= 0; $i--) {
    $trydir = implode(DIRECTORY_SEPARATOR, array_slice($dirparts, 0, $i + 1));
    if (file_exists($trydir . '/main.inc.php')) {
        $res = @include $trydir . '/main.inc.php';
        break;
    }
    if (file_exists($trydir . '/htdocs/main.inc.php')) {
        $res = @include $trydir . '/htdocs/main.inc.php';
        break;
    }
}

if (!$res) {
    die(json_encode([
        'success' => false,
        'error' => [
            'code' => 500,
            'message' => 'Error: Unable to load Dolibarr environment'
        ]
    ]));
}

// Verificar que el módulo está activo
if (empty($conf->easyapi->enabled)) {
    http_response_code(503);
    header('Content-Type: application/json');
    die(json_encode([
        'success' => false,
        'error' => [
            'code' => 503,
            'message' => 'EasyAPI module is not enabled'
        ]
    ]));
}

// Cargar autoloader de Composer
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($composerAutoload)) {
    http_response_code(500);
    header('Content-Type: application/json');
    die(json_encode([
        'success' => false,
        'error' => [
            'code' => 500,
            'message' => 'Composer dependencies not installed. Run: composer install in ' . dirname(__DIR__)
        ]
    ]));
}
require_once $composerAutoload;

// Cargar clase principal de la API
require_once __DIR__ . '/../class/api_easyapi.class.php';

// Instanciar y ejecutar la API
try {
    $api = new ApiEasyApi($db);
    $api->run();
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');

    // Solo exponer detalles del error (mensaje, fichero, línea) en modo debug
    $debug = !empty($conf->global->EASYAPI_DEBUG);
    $error = [
        'code' => 500,
        'message' => $debug ? $e->getMessage() : 'Internal server error'
    ];
    if ($debug) {
        $error['file'] = $e->getFile();
        $error['line'] = $e->getLine();
    }

    echo json_encode([
        'success' => false,
        'error' => $error
    ]);
}
