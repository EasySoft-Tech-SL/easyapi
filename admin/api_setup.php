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
 * \file    easyapi/admin/api_setup.php
 * \ingroup easyapi
 * \brief   EasyAPI - Página de Configuración de la API
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME']; $tmp2 = realpath(__FILE__); $i = strlen($tmp) - 1; $j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--; $j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

global $langs, $user, $conf, $db;

require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once '../lib/easyapi.lib.php';

$langs->loadLangs(array("admin", "easyapi@easyapi"));

// Access control
if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');

/*
 * Actions
 */
if ($action == 'update') {
	// CORS
	dolibarr_set_const($db, 'EASYAPI_CORS_ENABLED', GETPOST('EASYAPI_CORS_ENABLED', 'int'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'EASYAPI_CORS_ORIGIN', GETPOST('EASYAPI_CORS_ORIGIN', 'alpha'), 'chaine', 0, '', $conf->entity);

	// Rate Limit
	dolibarr_set_const($db, 'EASYAPI_RATE_LIMIT_ENABLED', GETPOST('EASYAPI_RATE_LIMIT_ENABLED', 'int'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'EASYAPI_RATE_LIMIT_MAX', GETPOST('EASYAPI_RATE_LIMIT_MAX', 'int'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'EASYAPI_RATE_LIMIT_WINDOW', GETPOST('EASYAPI_RATE_LIMIT_WINDOW', 'int'), 'chaine', 0, '', $conf->entity);

	// Logging
	dolibarr_set_const($db, 'EASYAPI_LOG_ENABLED', GETPOST('EASYAPI_LOG_ENABLED', 'int'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'EASYAPI_LOG_FILE', GETPOST('EASYAPI_LOG_FILE', 'alpha'), 'chaine', 0, '', $conf->entity);

	// Debug
	dolibarr_set_const($db, 'EASYAPI_DEBUG', GETPOST('EASYAPI_DEBUG', 'int'), 'chaine', 0, '', $conf->entity);

	// Rutas públicas
	dolibarr_set_const($db, 'EASYAPI_PUBLIC_ROUTES', GETPOST('EASYAPI_PUBLIC_ROUTES', 'alpha'), 'chaine', 0, '', $conf->entity);

	setEventMessages($langs->trans("SetupSaved"), null, 'mesgs');
	header("Location: ".$_SERVER["PHP_SELF"]);
	exit;
}

/*
 * View
 */
$form = new Form($db);

$page_name = "EasyAPI - Configuración API";
llxHeader('', $page_name, '');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($page_name, $linkback, 'title_setup');

$head = easyapiAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', 'EasyAPI', -1, "easyapi@easyapi");

// Información de la API
print '<div class="info" style="margin-bottom: 20px; padding: 15px; background: #e8f4fd; border-left: 4px solid #0088cc; border-radius: 4px;">';
print '<h3 style="margin-top: 0;"><i class="fas fa-info-circle"></i> Información de la API</h3>';

$apiBaseUrl = (empty($_SERVER['HTTPS']) ? 'http://' : 'https://') . $_SERVER['HTTP_HOST'];
$apiPath = str_replace('/admin/api_setup.php', '/api/', $_SERVER['SCRIPT_NAME']);
$apiUrl = $apiBaseUrl . $apiPath;

print '<table class="noborder">';
print '<tr><td><strong>URL Base de la API:</strong></td><td><code style="background: #f5f5f5; padding: 3px 8px; border-radius: 3px;">'.$apiUrl.'</code></td></tr>';
print '<tr><td><strong>Health Check:</strong></td><td><a href="'.$apiUrl.'status" target="_blank">'.$apiUrl.'status</a></td></tr>';
print '<tr><td><strong>Documentación:</strong></td><td><a href="'.$apiUrl.'docs" target="_blank">'.$apiUrl.'docs</a></td></tr>';
print '<tr><td><strong>Autenticación:</strong></td><td>Header <code>DOLAPIKEY: tu_api_key</code></td></tr>';
print '</table>';
print '</div>';

// Formulario de configuración
print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';

// ===================== CORS =====================
print '<tr class="liste_titre"><td colspan="3"><strong><i class="fas fa-globe"></i> CORS (Cross-Origin Resource Sharing)</strong></td></tr>';

print '<tr class="oddeven">';
print '<td width="300">Habilitar CORS</td>';
print '<td>'.ajax_constantonoff('EASYAPI_CORS_ENABLED', array(), null, 0, 0, 1).'</td>';
print '<td class="opacitymedium">Permitir peticiones desde otros dominios</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>Origen permitido</td>';
print '<td><input type="text" name="EASYAPI_CORS_ORIGIN" value="'.getDolGlobalString('EASYAPI_CORS_ORIGIN', '*').'" class="minwidth300"></td>';
print '<td class="opacitymedium">* para todos, o dominio específico (ej: https://miapp.com)</td>';
print '</tr>';

// ===================== Rate Limit =====================
print '<tr class="liste_titre"><td colspan="3"><strong><i class="fas fa-tachometer-alt"></i> Rate Limiting</strong></td></tr>';

print '<tr class="oddeven">';
print '<td>Habilitar Rate Limit</td>';
print '<td>'.ajax_constantonoff('EASYAPI_RATE_LIMIT_ENABLED', array(), null, 0, 0, 1).'</td>';
print '<td class="opacitymedium">Limitar número de peticiones por usuario/IP</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>Máximo de peticiones</td>';
print '<td><input type="number" name="EASYAPI_RATE_LIMIT_MAX" value="'.getDolGlobalString('EASYAPI_RATE_LIMIT_MAX', '100').'" class="width100"></td>';
print '<td class="opacitymedium">Número máximo de peticiones en la ventana de tiempo</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>Ventana de tiempo (segundos)</td>';
print '<td><input type="number" name="EASYAPI_RATE_LIMIT_WINDOW" value="'.getDolGlobalString('EASYAPI_RATE_LIMIT_WINDOW', '60').'" class="width100"></td>';
print '<td class="opacitymedium">Período en segundos para el conteo de peticiones</td>';
print '</tr>';

// ===================== Logging =====================
print '<tr class="liste_titre"><td colspan="3"><strong><i class="fas fa-file-alt"></i> Logging</strong></td></tr>';

print '<tr class="oddeven">';
print '<td>Habilitar Logging</td>';
print '<td>'.ajax_constantonoff('EASYAPI_LOG_ENABLED', array(), null, 0, 0, 1).'</td>';
print '<td class="opacitymedium">Registrar todas las peticiones a la API</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>Archivo de log</td>';
print '<td><input type="text" name="EASYAPI_LOG_FILE" value="'.getDolGlobalString('EASYAPI_LOG_FILE', '').'" class="minwidth400" placeholder="/var/log/easyapi.log"></td>';
print '<td class="opacitymedium">Ruta al archivo de log (vacío = solo syslog de Dolibarr)</td>';
print '</tr>';

// ===================== Debug =====================
print '<tr class="liste_titre"><td colspan="3"><strong><i class="fas fa-bug"></i> Debug</strong></td></tr>';

print '<tr class="oddeven">';
print '<td>Modo Debug</td>';
print '<td>'.ajax_constantonoff('EASYAPI_DEBUG', array(), null, 0, 0, 1).'</td>';
print '<td class="opacitymedium" style="color: #cc0000;">⚠️ Muestra detalles de errores. NO activar en producción.</td>';
print '</tr>';

// ===================== Rutas Públicas =====================
print '<tr class="liste_titre"><td colspan="3"><strong><i class="fas fa-unlock"></i> Rutas Públicas</strong></td></tr>';

print '<tr class="oddeven">';
print '<td>Rutas sin autenticación</td>';
print '<td><input type="text" name="EASYAPI_PUBLIC_ROUTES" value="'.getDolGlobalString('EASYAPI_PUBLIC_ROUTES', '/status,/health,/login,/docs').'" class="minwidth400"></td>';
print '<td class="opacitymedium">Rutas separadas por coma que no requieren DOLAPIKEY</td>';
print '</tr>';

print '</table>';

print '<div class="center" style="margin-top: 20px;">';
print '<input type="submit" class="button button-save" value="'.$langs->trans("Save").'">';
print '</div>';

print '</form>';

// ===================== Ejemplo de uso =====================
print '<br><br>';
print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
print '<table class="border centpercent tableforfield">';

print '<tr class="liste_titre"><td colspan="2"><strong><i class="fas fa-code"></i> Ejemplos de Uso</strong></td></tr>';

print '<tr class="oddeven"><td colspan="2">';
print '<h4>1. Login para obtener API key:</h4>';
print '<pre style="background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 5px; overflow-x: auto;">
curl -X POST "'.$apiUrl.'login" \
     -H "Content-Type: application/json" \
     -d \'{"login": "usuario", "password": "contraseña"}\'
</pre>';

print '<h4>2. Usar API key en peticiones:</h4>';
print '<pre style="background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 5px; overflow-x: auto;">
curl -X GET "'.$apiUrl.'me" \
     -H "DOLAPIKEY: TU_API_KEY"
</pre>';

print '<h4>JavaScript (fetch):</h4>';
print '<pre style="background: #2d2d2d; color: #f8f8f2; padding: 15px; border-radius: 5px; overflow-x: auto;">
// Login
const login = await fetch("'.$apiUrl.'login", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ login: "usuario", password: "contraseña" })
});
const { data } = await login.json();
const apiKey = data.api_key;

// Usar API key
const response = await fetch("'.$apiUrl.'me", {
    headers: { "DOLAPIKEY": apiKey }
});
</pre>';

print '</td></tr>';

print '</table>';
print '</div>';

// ===================== Endpoints disponibles =====================
print '<br>';
print '<div class="fichecenter">';
print '<table class="border centpercent tableforfield">';
print '<tr class="liste_titre"><td colspan="3"><strong><i class="fas fa-list"></i> Endpoints Disponibles</strong></td></tr>';

$endpoints = [
	['GET', '/status', 'Health check (público)'],
	['GET', '/health', 'Health check alias (público)'],
	['POST', '/login', 'Obtener API key con credenciales (público)'],
	['GET', '/docs', 'Documentación de la API (público)'],
	['GET', '/me', 'Información del usuario autenticado'],
];

print '<tr class="liste_titre"><td colspan="3"><strong><i class="fas fa-info-circle"></i> Nota</strong></td></tr>';
print '<tr class="oddeven"><td colspan="3" class="opacitymedium">';
print 'Los endpoints adicionales (productos, facturas, etc.) deben añadirse mediante módulos externos usando hooks.<br>';
print 'Ver documentación: <code>custom/easyapi/docs/EXTENDING.md</code>';
print '</td></tr>';
print '<tr class="liste_titre"><td colspan="3"><strong><i class="fas fa-list"></i> Endpoints Base</strong></td></tr>';

foreach ($endpoints as $ep) {
	print '<tr class="oddeven">';
	print '<td width="80"><span class="badge badge-'.($ep[0] == 'GET' ? 'primary' : 'success').'">'.$ep[0].'</span></td>';
	print '<td width="250"><code>'.$ep[1].'</code></td>';
	print '<td>'.$ep[2].'</td>';
	print '</tr>';
}

print '</table>';
print '</div>';

print dol_get_fiche_end();

llxFooter();
