# EASYAPI PARA [DOLIBARR ERP CRM](https://www.dolibarr.org)

[![Dolibarr](https://img.shields.io/badge/Dolibarr-16.0+-blue.svg)](https://www.dolibarr.org)
[![PHP](https://img.shields.io/badge/PHP-7.4+-green.svg)](https://php.net)
[![License](https://img.shields.io/badge/License-GPL%20v3-red.svg)](COPYING)
[![Slim Framework](https://img.shields.io/badge/Slim-4.x-orange.svg)](https://www.slimframework.com/)

## 📋 Descripción

**EasyAPI** es un módulo avanzado para Dolibarr que proporciona una API REST headless moderna y extensible, construida sobre Slim Framework 4. Permite crear, exponer y documentar endpoints REST de forma sencilla, ya sea mediante el sistema de hooks de Dolibarr o mediante el innovador sistema **EAPI** (Easy API Resources).

El módulo está diseñado para desarrolladores que necesitan integrar Dolibarr con aplicaciones externas, crear frontends personalizados, desarrollar aplicaciones móviles o automatizar procesos mediante API REST.

## ✨ Características Principales

### API REST Moderna
- ✅ **Slim Framework 4** como base sólida y probada
- ✅ **PSR-7 compliant** para interoperabilidad máxima
- ✅ **Documentación OpenAPI/Swagger** generada automáticamente
- ✅ **Respuestas JSON estandarizadas** con manejo de errores consistente

### Sistema EAPI (Easy API Resources)
- 🚀 **Crea endpoints en minutos** con el sistema de Resources
- 📁 **Autodescubrimiento** de recursos en carpetas `eapi/`
- 🔧 **Métodos helper integrados** para respuestas HTTP
- 📝 **Documentación automática** de parámetros y respuestas

### Extensibilidad
- 🔌 **Sistema de hooks** compatible con módulos externos
- 🎯 **Dos métodos de extensión**: Hooks tradicionales o Resources EAPI
- 📦 **Ejemplos completos** incluidos para comenzar rápidamente
- 🔄 **Integración transparente** con el ecosistema Dolibarr

### Seguridad y Control
- 🔐 **Autenticación mediante DOLAPIKEY** integrada con usuarios Dolibarr
- 🛡️ **Sistema de permisos granular** por endpoint
- 🚦 **Rate Limiting** configurable por usuario
- 📊 **CORS configurable** para aplicaciones frontend
- 📝 **Logging de peticiones** para auditoría

### Características Técnicas
- 🚀 **Alto rendimiento** con autoloader optimizado
- 🔧 **Middlewares personalizables** (Auth, CORS, Rate Limit, Logger)
- 💾 **Acceso directo a DoliDB** para consultas optimizadas
- 🌐 **Soporte multi-idioma** (Español, Inglés)

## 🔧 Requisitos del Sistema

### Requisitos Mínimos
- **Dolibarr**: Versión 16.0 o superior
- **PHP**: Versión 7.4 o superior (recomendado PHP 8.0+)
- **MySQL/MariaDB**: Versión 5.7+ / 10.3+
- **Composer**: Para instalación de dependencias
- **Extensiones PHP requeridas**:
  - `php-curl`
  - `php-json`
  - `php-mbstring`

### Requisitos Recomendados
- **Servidor web**: Apache 2.4+ con mod_rewrite o Nginx 1.18+
- **Memoria PHP**: Mínimo 128MB, recomendado 256MB+
- **PHP 8.1+**: Para mejor rendimiento

## 📦 Instalación

### Método 1: Instalación Manual

1. **Descargar el módulo** y extraer en `htdocs/custom/easyapi`

2. **Instalar dependencias con Composer**:
```bash
cd htdocs/custom/easyapi
composer install --no-dev --optimize-autoloader
```

3. **Activar el módulo** en Dolibarr → Configuración → Módulos → EasyAPI

4. **Configurar** en Admin → EasyAPI → Configuración API

### Método 2: Desde archivo ZIP

1. **Descargar el módulo** desde [Dolistore](https://www.dolistore.com) o releases de GitHub
2. **Ir a Dolibarr**: Menú `Inicio → Configuración → Módulos → Instalar módulo externo`
3. **Subir archivo ZIP** y seguir el asistente de instalación
4. **Ejecutar Composer** en la carpeta del módulo
5. **Activar el módulo** en la lista de módulos disponibles

## 🚀 Uso Rápido

### URL Base
```
https://tudominio.com/custom/easyapi/api/
```

### Endpoints Base Incluidos

| Método | Ruta | Descripción | Auth |
|--------|------|-------------|------|
| GET | `/status` | Health check del sistema | No |
| GET | `/health` | Health check del sistema | No |
| POST | `/login` | Obtener API key | No |
| GET | `/docs` | Documentación Swagger UI | No |
| GET | `/openapi.json` | Especificación OpenAPI | No |
| GET | `/me` | Usuario actual autenticado | Sí |

### Autenticación

#### Opción 1: Login para obtener API key

```bash
curl -X POST "https://tudominio.com/custom/easyapi/api/login" \
  -H "Content-Type: application/json" \
  -d '{"login": "usuario", "password": "contraseña"}'
```

Respuesta:
```json
{
  "success": true,
  "data": {
    "api_key": "abc123...",
    "user": { "id": 1, "login": "usuario" }
  }
}
```

#### Opción 2: Usar API key existente

La API key del usuario está en Dolibarr → Usuario → Pestaña API.

#### Usar en peticiones

```bash
curl "https://tudominio.com/custom/easyapi/api/me" \
  -H "DOLAPIKEY: tu_api_key"
```

## 🔌 Extender la API

EasyAPI ofrece **dos métodos** para añadir endpoints personalizados:

### Método 1: Sistema EAPI (Recomendado) ⭐

El método más sencillo. Solo crea una carpeta `eapi/` en tu módulo:

```
custom/
└── tumodulo/
    └── eapi/
        └── ProductosResource.php
```

```php
<?php
use EasyApi\EasyApiResource;

class ProductosResource extends EasyApiResource
{
    protected $description = 'Gestión de productos';

    protected function registerRoutes(): void
    {
        // GET /tumodulo/productos
        $this->get('/', 'Listar productos', function ($req, $res) {
            return $this->ok($res, ['productos' => []]);
        });

        // GET /tumodulo/productos/{id}
        $this->get('/{id}', 'Ver producto', function ($req, $res, $args) {
            return $this->ok($res, ['id' => $args['id']]);
        })->pathParams([
            'id' => ['type' => 'integer', 'description' => 'ID del producto']
        ]);

        // POST /tumodulo/productos (requiere permiso)
        $this->post('/', 'Crear producto', function ($req, $res) {
            $data = $this->getBody($req);
            return $this->created($res, ['id' => 123]);
        })->requirePermission('produit->creer');
    }
}
```

¡Listo! Accede a `/docs` para ver tu API documentada automáticamente.

### Método 2: Sistema de Hooks

Para mayor control, usa el sistema de hooks de Dolibarr:

1. Registrar hook en `module_parts`:
```php
$this->module_parts = array(
    'hooks' => array('data' => array('easyapi')),
);
```

2. Crear `class/actions_tumodulo.class.php`:
```php
<?php
class ActionsTumodulo
{
    public function easyapiRegisterRoutes($parameters, &$object, &$action, $hookmanager)
    {
        $app = $parameters['app'];
        $api = $parameters['api'];

        $app->get('/mi-endpoint', function ($req, $res) use ($api) {
            return $api->successResponse($res, ['hello' => 'world']);
        });

        return 0;
    }
}
```

📚 **Documentación completa**: Ver [EAPI_GUIDE.md](EAPI_GUIDE.md) y [docs/EXTENDING.md](docs/EXTENDING.md)

## ⚙️ Configuración

En Admin → EasyAPI → Configuración API:

| Opción | Descripción |
|--------|-------------|
| **CORS** | Permitir peticiones cross-origin desde frontends |
| **Rate Limit** | Limitar número de peticiones por usuario/tiempo |
| **Logging** | Registrar todas las peticiones para auditoría |
| **Debug Mode** | Activar información de debug en respuestas |

## 📁 Estructura del Módulo

```
easyapi/
├── api/                    # Punto de entrada API
├── class/                  # Clases principales
│   ├── api_easyapi.class.php
│   ├── EasyApiResource.class.php
│   └── EasyApiResourceLoader.class.php
├── middleware/             # Middlewares (Auth, CORS, etc.)
├── eapi/                   # Resources EAPI del módulo
├── examples_resources/     # 10 ejemplos de Resources
├── examples_hooks/         # 5 ejemplos de Hooks
├── docs/                   # Documentación extendida
└── vendor/                 # Dependencias Composer
```

## 📚 Documentación

- 📖 [EAPI_GUIDE.md](EAPI_GUIDE.md) - Guía rápida del sistema EAPI
- 🔌 [docs/EXTENDING.md](docs/EXTENDING.md) - Cómo extender la API
- 🏗️ [docs/IMPLEMENTATION.md](docs/IMPLEMENTATION.md) - Detalles de implementación
- 📁 [examples_resources/](examples_resources/) - 10 ejemplos completos de Resources
- 🪝 [examples_hooks/](examples_hooks/) - 5 ejemplos de uso con Hooks

## 🌍 Traducciones

Las traducciones están disponibles en los siguientes idiomas:

- 🇪🇸 **Español** (es_ES) - Completo
- 🇬🇧 **Inglés** (en_US) - Completo

## 📄 Licencias

### Código Principal

GPLv3 or later. Ver archivo [COPYING](COPYING) para más información.

### Documentación

Todos los textos y archivos readme están licenciados bajo GFDL.

### Dependencias de Terceros

Este módulo incluye las siguientes bibliotecas de terceros:

| Biblioteca | Licencia | Descripción |
|------------|----------|-------------|
| Slim Framework | MIT | Framework PHP para APIs |
| PSR-7 (Nyholm) | MIT | Implementación PSR-7 |
| FastRoute | BSD-3 | Router de alto rendimiento |

---

Otros módulos externos están disponibles en [Dolistore.com](https://www.dolistore.com).

## 📞 Soporte y Contacto

Para soporte técnico, reportar errores o solicitar nuevas funcionalidades:

- **Email**: info@easysoft.es
- **Web**: [https://easysoft.es](https://easysoft.es)
- **Documentación**: Consulta los archivos incluidos en el módulo
- **Issues**: Reporta problemas en el repositorio del proyecto

---

**Desarrollado por EasySoft (EASYSOFT TECH S.L.)** | CIF: B16885766 | Oviedo, Asturias, España
