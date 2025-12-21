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
 * \file    easyapi/class/EasyApiResourceLoader.class.php
 * \ingroup easyapi
 * \brief   EAPI Resource Loader - Auto-descubrimiento de recursos
 */

/**
 * Cargador de recursos EAPI
 *
 * Escanea automáticamente los módulos en custom/ buscando carpetas 'eapi'
 * y carga los recursos encontrados.
 */
class EasyApiResourceLoader
{
    /** @var \Slim\App */
    private $app;

    /** @var \DoliDB */
    private $db;

    /** @var \ApiEasyApi */
    private $api;

    /** @var array Módulos con EAPI encontrados */
    private $discoveredModules = array();

    /** @var array Recursos cargados */
    private $loadedResources = array();

    /** @var array Errores durante la carga */
    private $errors = array();

    /**
     * Constructor
     *
     * @param \Slim\App $app
     * @param \DoliDB $db
     * @param \ApiEasyApi $api
     */
    public function __construct($app, $db, $api)
    {
        $this->app = $app;
        $this->db = $db;
        $this->api = $api;
    }

    /**
     * Descubre y carga todos los recursos EAPI de los módulos instalados
     *
     * Escanea tanto módulos en custom/ como en la raíz de Dolibarr,
     * similar a como lo hace dolGetModulesDirs().
     *
     * @return array Información sobre los recursos cargados
     */
    public function loadAll(): array
    {
        global $conf;

        // Obtener todos los directorios de módulos (igual que Dolibarr)
        $modulesDirs = $this->getModulesDirs();

        foreach ($modulesDirs as $dir) {
            $this->scanModulesDirectory($dir);
        }

        return array(
            'modules' => $this->discoveredModules,
            'resources' => $this->loadedResources,
            'errors' => $this->errors
        );
    }

    /**
     * Obtiene los directorios donde buscar módulos
     *
     * Similar a dolGetModulesDirs() de Dolibarr
     *
     * @return array Lista de directorios
     */
    private function getModulesDirs(): array
    {
        global $conf;

        $dirs = array();

        // 1. Directorio custom (prioridad)
        $customPath = DOL_DOCUMENT_ROOT . '/custom';
        if (is_dir($customPath)) {
            $dirs[] = $customPath;
        }

        // 2. Directorios de la raíz de Dolibarr (módulos del core)
        // Solo escaneamos ciertos directorios conocidos para evitar escanear todo htdocs
        $coreDirs = array(
            DOL_DOCUMENT_ROOT,  // Raíz para módulos como /product, /societe, etc.
        );

        foreach ($coreDirs as $coreDir) {
            if (is_dir($coreDir) && !in_array($coreDir, $dirs)) {
                $dirs[] = $coreDir;
            }
        }

        return $dirs;
    }

    /**
     * Escanea un directorio buscando módulos con carpeta eapi
     *
     * @param string $baseDir Directorio base a escanear
     */
    private function scanModulesDirectory(string $baseDir): void
    {
        global $conf;

        if (!is_dir($baseDir)) {
            return;
        }

        $items = scandir($baseDir);

        foreach ($items as $moduleName) {
            // Ignorar . y .. y archivos ocultos
            if ($moduleName === '.' || $moduleName === '..' || $moduleName[0] === '.') {
                continue;
            }

            // Ignorar directorios del sistema que no son módulos
            $ignoreDirs = array(
                'api', 'core', 'includes', 'install', 'langs', 'theme', 'themes',
                'public', 'scripts', 'documents', 'conf', 'vendor', 'build',
                'dev', 'doc', 'test', 'htdocs'
            );
            if (in_array($moduleName, $ignoreDirs)) {
                continue;
            }

            $modulePath = $baseDir . '/' . $moduleName;

            // Verificar que es un directorio
            if (!is_dir($modulePath)) {
                continue;
            }

            // Verificar si el módulo está activo usando $conf->modulo->enabled
            // El módulo easyapi siempre está activo si estamos ejecutando este código
            $moduleNameLower = strtolower($moduleName);
            if ($moduleName !== 'easyapi') {
                if (!isset($conf->$moduleNameLower) || empty($conf->$moduleNameLower->enabled)) {
                    continue;
                }
            }

            // Buscar carpeta eapi
            $eapiPath = $modulePath . '/eapi';

            if (is_dir($eapiPath)) {
                // Evitar duplicados
                if (!in_array($moduleName, $this->discoveredModules)) {
                    $this->discoveredModules[] = $moduleName;
                    $this->loadModuleResources($moduleName, $eapiPath);
                }
            }
        }
    }

    /**
     * Carga los recursos de un módulo específico
     *
     * @param string $moduleName
     * @param string $eapiPath
     */
    private function loadModuleResources(string $moduleName, string $eapiPath): void
    {
        dol_syslog("EAPI Loader: Scanning module '$moduleName' at $eapiPath", LOG_DEBUG);

        // Cargar la clase base si no está cargada
        require_once __DIR__ . '/EasyApiResource.class.php';

        // Escanear recursivamente la carpeta eapi
        $this->scanDirectory($moduleName, $eapiPath, '');
    }

    /**
     * Escanea un directorio recursivamente buscando archivos *Resource.php
     *
     * @param string $moduleName
     * @param string $basePath
     * @param string $relativePath
     */
    private function scanDirectory(string $moduleName, string $basePath, string $relativePath): void
    {
        $currentPath = $basePath . ($relativePath ? '/' . $relativePath : '');

        if (!is_dir($currentPath)) {
            return;
        }

        $items = scandir($currentPath);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            // Ignorar carpetas especiales
            if ($item === '_middleware' || $item === '_config' || $item === '_shared') {
                continue;
            }

            $itemPath = $currentPath . '/' . $item;

            if (is_dir($itemPath)) {
                // Recursión en subdirectorios
                $newRelativePath = $relativePath ? $relativePath . '/' . $item : $item;
                $this->scanDirectory($moduleName, $basePath, $newRelativePath);
            } elseif (preg_match('/^([A-Z][a-zA-Z0-9]*)Resource\.php$/', $item, $matches)) {
                // Encontrado un archivo Resource
                $this->loadResource($moduleName, $itemPath, $relativePath, $matches[1]);
            }
        }
    }

    /**
     * Carga un recurso específico
     *
     * @param string $moduleName
     * @param string $filePath
     * @param string $relativePath
     * @param string $resourceName
     */
    private function loadResource(string $moduleName, string $filePath, string $relativePath, string $resourceName): void
    {
        dol_syslog("EAPI Loader: Loading resource '$resourceName' from $filePath (relativePath='$relativePath')", LOG_INFO);

        try {
            // Incluir el archivo
            require_once $filePath;

            // Determinar el nombre de la clase
            $className = $resourceName . 'Resource';

            // Verificar que la clase existe
            if (!class_exists($className)) {
                // Intentar con namespace basado en el módulo
                $nsClassName = ucfirst($moduleName) . '\\Eapi\\' . $className;
                if (!class_exists($nsClassName)) {
                    $this->errors[] = "Class '$className' not found in $filePath";
                    dol_syslog("EAPI Loader: Class '$className' not found in $filePath", LOG_ERR);
                    return;
                }
                $className = $nsClassName;
            }

            // Verificar que extiende EasyApiResource
            if (!is_subclass_of($className, 'EasyApi\\EasyApiResource')) {
                $this->errors[] = "Class '$className' must extend EasyApi\\EasyApiResource";
                dol_syslog("EAPI Loader: Class '$className' must extend EasyApiResource", LOG_ERR);
                return;
            }

            // Calcular el prefijo de la ruta
            $prefix = $this->calculatePrefix($moduleName, $relativePath, $resourceName);
            dol_syslog("EAPI Loader: Calculated prefix='$prefix' for $className (module=$moduleName, relativePath=$relativePath, resource=$resourceName)", LOG_INFO);

            // Instanciar y registrar
            $resource = new $className(
                $this->app,
                $this->db,
                $this->api,
                $moduleName,
                $prefix
            );

            $resource->register();

            $this->loadedResources[] = array(
                'module' => $moduleName,
                'resource' => $resourceName,
                'class' => $className,
                'prefix' => $prefix,
                'file' => $filePath
            );

            dol_syslog("EAPI Loader: Successfully loaded '$className' with prefix '$prefix'", LOG_INFO);

        } catch (\Throwable $e) {
            $this->errors[] = "Error loading $filePath: " . $e->getMessage();
            dol_syslog("EAPI Loader: Error loading $filePath: " . $e->getMessage(), LOG_ERR);
        }
    }

    /**
     * Calcula el prefijo de ruta basado en la estructura de carpetas
     *
     * Ejemplos:
     * - easyapi/eapi/DemoResource.php → /demo (módulo easyapi no repite nombre)
     * - mimodulo/eapi/ProductosResource.php → /mimodulo/productos
     * - mimodulo/eapi/ordenes/OrdenesResource.php → /mimodulo/ordenes
     * - mimodulo/eapi/vehiculos/historial/HistorialResource.php → /mimodulo/vehiculos/historial
     *
     * @param string $moduleName
     * @param string $relativePath
     * @param string $resourceName
     * @return string
     */
    private function calculatePrefix(string $moduleName, string $relativePath, string $resourceName): string
    {
        // Convertir nombre del módulo a kebab-case
        $moduleSlug = $this->toKebabCase($moduleName);

        // Para el propio módulo easyapi, no incluir el nombre del módulo en el prefijo
        // ya que la URL base ya es /custom/easyapi/api
        $includeModuleName = ($moduleName !== 'easyapi');

        // Si no hay path relativo, usar nombre del recurso
        if (empty($relativePath)) {
            $resourceSlug = $this->toKebabCase($resourceName);
            if ($includeModuleName) {
                return '/' . $moduleSlug . '/' . $resourceSlug;
            } else {
                return '/' . $resourceSlug;
            }
        }

        // Convertir path relativo a kebab-case
        $pathParts = explode('/', $relativePath);
        $sluggedParts = array();

        foreach ($pathParts as $part) {
            $sluggedParts[] = $this->toKebabCase($part);
        }

        // Verificar si el nombre del recurso coincide con el último segmento del path
        $lastPathPart = end($pathParts);
        $lastPathSlug = $this->toKebabCase($lastPathPart);
        $resourceSlug = $this->toKebabCase($resourceName);

        // Si el recurso se llama como la combinación del path (VehiculosHistorial)
        // o como el último segmento (Historial), no duplicar
        $pathCombined = str_replace('/', '', implode('', array_map('ucfirst', $pathParts)));

        if (strtolower($resourceName) === strtolower($pathCombined) ||
            strtolower($resourceName) === strtolower($lastPathPart)) {
            // Solo usar el path
            if ($includeModuleName) {
                return '/' . $moduleSlug . '/' . implode('/', $sluggedParts);
            } else {
                return '/' . implode('/', $sluggedParts);
            }
        }

        // Caso especial: recurso diferente al path (ej: config/SettingsResource)
        if ($includeModuleName) {
            return '/' . $moduleSlug . '/' . implode('/', $sluggedParts) . '/' . $resourceSlug;
        } else {
            return '/' . implode('/', $sluggedParts) . '/' . $resourceSlug;
        }
    }

    /**
     * Convierte un string PascalCase o camelCase a kebab-case
     *
     * @param string $string
     * @return string
     */
    private function toKebabCase(string $string): string
    {
        // Insertar guión antes de mayúsculas
        $result = preg_replace('/([a-z])([A-Z])/', '$1-$2', $string);
        // Convertir a minúsculas
        return strtolower($result);
    }

    /**
     * Obtiene los módulos descubiertos
     *
     * @return array
     */
    public function getDiscoveredModules(): array
    {
        return $this->discoveredModules;
    }

    /**
     * Obtiene los recursos cargados
     *
     * @return array
     */
    public function getLoadedResources(): array
    {
        return $this->loadedResources;
    }

    /**
     * Obtiene los errores ocurridos
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
