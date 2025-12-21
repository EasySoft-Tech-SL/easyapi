<?php
/**
 * =============================================================================
 * EJEMPLO 06: CONSULTAS A BASE DE DATOS
 * =============================================================================
 *
 * Demuestra cómo realizar consultas a la base de datos de Dolibarr.
 *
 * MÉTODOS DISPONIBLES:
 *   - $this->fetchAll($sql)  → Ejecuta SQL y devuelve array de filas
 *   - $this->fetchOne($sql)  → Ejecuta SQL y devuelve una sola fila
 *   - $this->db              → Acceso directo al objeto DoliDB
 *   - $this->db->escape()    → Escapar valores para SQL
 *   - MAIN_DB_PREFIX         → Prefijo de tablas (ej: llx_)
 *
 * =============================================================================
 */

use EasyApi\EasyApiResource;

class DatabaseQueriesResource extends EasyApiResource
{
    protected $description = 'Ejemplos de consultas a base de datos';

    protected function registerRoutes(): void
    {
        // =====================================================================
        // fetchAll() - Obtener múltiples registros
        // =====================================================================
        $this->get('/paises', 'Listar países', function ($req, $res) {
            $sql = "SELECT code, label
                    FROM " . MAIN_DB_PREFIX . "c_country
                    WHERE active = 1
                    ORDER BY label ASC
                    LIMIT 20";

            $paises = $this->fetchAll($sql);

            return $this->ok($res, array(
                'total' => count($paises),
                'paises' => $paises
            ));
        })
        ->tags('Base de Datos')
        ->describe('Usa fetchAll() para obtener múltiples registros.');


        // =====================================================================
        // fetchOne() - Obtener un solo registro
        // =====================================================================
        $this->get('/config/{key}', 'Leer configuración', function ($req, $res, $args) {
            $key = $this->db->escape($args['key']); // IMPORTANTE: Escapar

            $sql = "SELECT name, value
                    FROM " . MAIN_DB_PREFIX . "const
                    WHERE name = '$key'";

            $config = $this->fetchOne($sql);

            if (!$config) {
                return $this->notFound($res, "Configuración '$key' no encontrada");
            }

            return $this->ok($res, $config);
        })
        ->pathParams(array(
            'key' => array('type' => 'string', 'description' => 'Nombre de constante (ej: MAIN_LANG_DEFAULT)')
        ))
        ->tags('Base de Datos')
        ->describe('Usa fetchOne() para obtener un solo registro.');


        // =====================================================================
        // Consulta con parámetros de búsqueda
        // =====================================================================
        $this->get('/terceros', 'Buscar terceros', function ($req, $res) {
            $search = $this->db->escape($this->query($req, 'search', ''));
            $limit = (int) $this->query($req, 'limit', 10);
            $offset = (int) $this->query($req, 'offset', 0);

            $where = "entity = " . (int) $this->conf->entity;
            if (!empty($search)) {
                $where .= " AND (nom LIKE '%$search%' OR email LIKE '%$search%')";
            }

            // Contar total
            $sqlCount = "SELECT COUNT(*) as total FROM " . MAIN_DB_PREFIX . "societe WHERE $where";
            $countResult = $this->fetchOne($sqlCount);
            $total = $countResult ? (int) $countResult['total'] : 0;

            // Obtener registros
            $sql = "SELECT rowid, nom, email, phone, town
                    FROM " . MAIN_DB_PREFIX . "societe
                    WHERE $where
                    ORDER BY nom ASC
                    LIMIT $limit OFFSET $offset";

            $terceros = $this->fetchAll($sql);

            return $this->ok($res, array(
                'terceros' => $terceros,
                'pagination' => array(
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'pages' => ceil($total / $limit)
                )
            ));
        })
        ->queryParams(array(
            'search' => array('type' => 'string', 'description' => 'Buscar por nombre o email'),
            'limit' => array('type' => 'integer', 'description' => 'Límite (default: 10)'),
            'offset' => array('type' => 'integer', 'description' => 'Offset')
        ))
        ->tags('Base de Datos')
        ->describe('Búsqueda con paginación usando fetchAll() y fetchOne().');


        // =====================================================================
        // Acceso directo a $this->db
        // =====================================================================
        $this->get('/estadisticas', 'Estadísticas con DB', function ($req, $res) {
            $entity = (int) $this->conf->entity;

            // Múltiples consultas con acceso directo a db
            $stats = array();

            // Total terceros
            $resql = $this->db->query("SELECT COUNT(*) as c FROM " . MAIN_DB_PREFIX . "societe WHERE entity = $entity");
            if ($resql) {
                $obj = $this->db->fetch_object($resql);
                $stats['terceros'] = (int) $obj->c;
                $this->db->free($resql);
            }

            // Total productos
            $resql = $this->db->query("SELECT COUNT(*) as c FROM " . MAIN_DB_PREFIX . "product WHERE entity = $entity");
            if ($resql) {
                $obj = $this->db->fetch_object($resql);
                $stats['productos'] = (int) $obj->c;
                $this->db->free($resql);
            }

            // Total facturas
            $resql = $this->db->query("SELECT COUNT(*) as c FROM " . MAIN_DB_PREFIX . "facture WHERE entity = $entity");
            if ($resql) {
                $obj = $this->db->fetch_object($resql);
                $stats['facturas'] = (int) $obj->c;
                $this->db->free($resql);
            }

            return $this->ok($res, array(
                'entity' => $entity,
                'estadisticas' => $stats
            ));
        })
        ->tags('Base de Datos')
        ->describe('Uso directo de $this->db para consultas múltiples.');


        // =====================================================================
        // INSERT con transacción
        // =====================================================================
        $this->post('/log', 'Crear log', function ($req, $res) {
            $schema = array(
                'required' => array('message'),
                'properties' => array(
                    'message' => array('type' => 'string', 'minLength' => 1),
                    'level' => array('type' => 'string', 'enum' => array('info', 'warning', 'error'))
                )
            );

            $validation = $this->validateBody($req, $schema);
            if (!$validation['valid']) {
                return $this->badRequest($res, $validation['error']);
            }

            $data = $validation['data'];
            $user = $this->getUser($req);

            // Iniciar transacción
            $this->db->begin();

            try {
                $sql = "INSERT INTO " . MAIN_DB_PREFIX . "events
                        (type, datec, fk_user, description, entity)
                        VALUES (
                            'EAPI_LOG',
                            NOW(),
                            " . (int) $user->id . ",
                            '" . $this->db->escape($data['message']) . "',
                            " . (int) $this->conf->entity . "
                        )";

                $result = $this->db->query($sql);

                if (!$result) {
                    throw new \Exception($this->db->lasterror());
                }

                $newId = $this->db->last_insert_id(MAIN_DB_PREFIX . "events");

                // Confirmar transacción
                $this->db->commit();

                return $this->created($res, array(
                    'id' => $newId,
                    'message' => 'Log creado correctamente'
                ));

            } catch (\Exception $e) {
                // Revertir transacción
                $this->db->rollback();
                return $this->error($res, 'Error al crear log: ' . $e->getMessage());
            }
        })
        ->body(array(
            'required' => array('message'),
            'properties' => array(
                'message' => array('type' => 'string', 'description' => 'Mensaje del log'),
                'level' => array('type' => 'string', 'description' => 'Nivel: info, warning, error')
            )
        ))
        ->tags('Base de Datos')
        ->describe('INSERT con transacción usando begin(), commit(), rollback().');
    }
}
