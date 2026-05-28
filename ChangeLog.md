# CHANGELOG EASYAPI FOR [DOLIBARR ERP CRM](https://www.dolibarr.org)

## [Unreleased]

### 🔒 Seguridad
- **DolibarrAuth**: las peticiones preflight CORS (`OPTIONS`) ya no devuelven 401, permitiendo el acceso desde frontends en otro origen.
- **DolibarrAuth**: soporte de `api_key` cifrada (Dolibarr 17+) mediante consulta indexada (texto plano + cifrado determinista, patrón del core), sin recorrer toda la tabla de usuarios.
- **api/index.php**: los detalles de excepción (`file`/`line`/mensaje) solo se exponen con `EASYAPI_DEBUG` activo.
- **RequestLogger**: se redactan `DOLAPIKEY`/`api_key` del query string antes de registrarlos.

### 🐛 Correcciones
- **CORS**: el interruptor `EASYAPI_CORS_ENABLED` ahora se respeta (antes CORS estaba siempre activo).
- **RequirePermission**: los administradores tienen acceso completo, coherente con el resto del módulo.
- **RateLimitMiddleware**: bloqueo exclusivo de fichero para evitar condiciones de carrera al contar peticiones.
- **DolibarrAuth (rutas públicas)**: si la `api_key` es inválida o el usuario está deshabilitado, las rutas públicas (p. ej. `/docs`) se sirven igualmente.
- **EasyApiResource**: validación de enteros que ahora acepta negativos; finalización explícita de la última ruta (sin depender del destructor).
- **modEasyapi**: `phpmin` actualizado a 7.4 (requisito real de Slim 4).

## [1.0.0] - 2025-12-21

### ✨ Lanzamiento Inicial

#### 🚀 API REST Moderna
- **Slim Framework 4**: Base sólida con soporte PSR-7 completo
- **Documentación OpenAPI/Swagger**: Generación automática de especificación y UI interactiva
- **Respuestas JSON estandarizadas**: Formato consistente con manejo de errores uniforme

#### 📦 Sistema EAPI (Easy API Resources)
- **🔍 Autodescubrimiento de recursos**: Detección automática de archivos `*Resource.php` en carpetas `eapi/`
- **🎯 Métodos helper integrados**: `ok()`, `created()`, `notFound()`, `badRequest()`, etc.
- **📝 Documentación automática**: Parámetros de path, query y body documentados automáticamente
- **🔐 Control de permisos por ruta**: `->requirePermission('modulo->permiso')`
- **🌐 Endpoints públicos**: `->public()` para rutas sin autenticación

#### 🔌 Sistema de Hooks
- **Hook `easyapiRegisterRoutes`**: Permite a módulos externos registrar rutas
- **Acceso completo a Slim App**: Control total sobre la aplicación
- **Helpers de API disponibles**: `successResponse()`, `errorResponse()`, `addRoute()`

#### 🛡️ Middlewares de Seguridad
- **DolibarrAuth**: Autenticación mediante DOLAPIKEY integrada con usuarios Dolibarr
- **CorsMiddleware**: Configuración CORS para aplicaciones frontend
- **RateLimitMiddleware**: Limitación de peticiones por usuario/tiempo
- **RequestLogger**: Registro de peticiones para auditoría
- **RequirePermission**: Verificación granular de permisos por endpoint

#### 📋 Endpoints Base
- `GET /status` - Health check del sistema
- `GET /health` - Health check del sistema
- `POST /login` - Autenticación y obtención de API key
- `GET /docs` - Interfaz Swagger UI
- `GET /openapi.json` - Especificación OpenAPI
- `GET /me` - Información del usuario autenticado

#### 📚 Documentación y Ejemplos
- **10 ejemplos de Resources**: CRUD, permisos, validación, uploads, etc.
- **5 ejemplos de Hooks**: Integración básica hasta objetos Dolibarr
- **Guías completas**: EAPI_GUIDE.md, EXTENDING.md, IMPLEMENTATION.md

#### 🌍 Internacionalización
- **Español (es_ES)**: Traducción completa
- **Inglés (en_US)**: Traducción completa

---

*Desarrollado por EasySoft (EASYSOFT TECH S.L.)*
