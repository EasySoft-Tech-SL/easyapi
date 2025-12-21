<?php
/**
 * =============================================================================
 * EJEMPLO 10: SUBIDA Y DESCARGA DE ARCHIVOS
 * =============================================================================
 *
 * Demuestra cómo manejar archivos en endpoints EAPI.
 *
 * CONCEPTOS:
 *   - Recibir archivos en base64
 *   - Guardar archivos en el sistema
 *   - Devolver archivos para descarga
 *   - Asociar archivos a objetos Dolibarr
 *
 * =============================================================================
 */

use EasyApi\EasyApiResource;

class FileUploadResource extends EasyApiResource
{
    protected $description = 'Gestión de archivos y documentos';

    protected function registerRoutes(): void
    {
        // =====================================================================
        // Subir archivo en Base64
        // =====================================================================
        $this->post('/upload', 'Subir archivo', function ($req, $res) {
            $schema = array(
                'required' => array('filename', 'content'),
                'properties' => array(
                    'filename' => array('type' => 'string', 'minLength' => 1),
                    'content' => array('type' => 'string', 'minLength' => 1),
                    'folder' => array('type' => 'string')
                )
            );

            $validation = $this->validateBody($req, $schema);
            if (!$validation['valid']) {
                return $this->badRequest($res, $validation['error']);
            }

            $data = $validation['data'];
            $user = $this->getUser($req);

            // Decodificar base64
            $fileContent = base64_decode($data['content']);
            if ($fileContent === false) {
                return $this->badRequest($res, 'El contenido no es Base64 válido');
            }

            // Validar extensión
            $filename = basename($data['filename']); // Seguridad
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $allowedExtensions = array('pdf', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'csv');

            if (!in_array($extension, $allowedExtensions)) {
                return $this->badRequest($res, 'Extensión no permitida. Permitidas: ' . implode(', ', $allowedExtensions));
            }

            // Validar tamaño (máximo 10MB)
            $maxSize = 10 * 1024 * 1024;
            if (strlen($fileContent) > $maxSize) {
                return $this->badRequest($res, 'El archivo excede el tamaño máximo de 10MB');
            }

            // Directorio de destino
            $folder = isset($data['folder']) ? $data['folder'] : 'eapi_uploads';
            $uploadDir = DOL_DATA_ROOT . '/' . $folder . '/' . date('Y/m');

            // Crear directorio si no existe
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Nombre único para evitar colisiones
            $uniqueName = date('Ymd_His') . '_' . $user->id . '_' . $filename;
            $filePath = $uploadDir . '/' . $uniqueName;

            // Guardar archivo
            $result = file_put_contents($filePath, $fileContent);
            if ($result === false) {
                return $this->error($res, 'Error al guardar el archivo');
            }

            return $this->created($res, array(
                'filename' => $uniqueName,
                'original_name' => $filename,
                'size' => strlen($fileContent),
                'size_human' => $this->formatBytes(strlen($fileContent)),
                'extension' => $extension,
                'path' => $folder . '/' . date('Y/m') . '/' . $uniqueName,
                'uploaded_by' => $user->login,
                'uploaded_at' => date('Y-m-d H:i:s')
            ));
        })
        ->body(array(
            'required' => array('filename', 'content'),
            'properties' => array(
                'filename' => array('type' => 'string', 'description' => 'Nombre del archivo con extensión'),
                'content' => array('type' => 'string', 'description' => 'Contenido del archivo en Base64'),
                'folder' => array('type' => 'string', 'description' => 'Carpeta destino (default: eapi_uploads)')
            )
        ))
        ->tags('Archivos')
        ->describe('Sube un archivo codificado en Base64. Máximo 10MB.');


        // =====================================================================
        // Listar archivos de una carpeta
        // =====================================================================
        $this->get('/list', 'Listar archivos', function ($req, $res) {
            $folder = $this->query($req, 'folder', 'eapi_uploads');
            $dir = DOL_DATA_ROOT . '/' . $folder;

            if (!is_dir($dir)) {
                return $this->ok($res, array('files' => array(), 'total' => 0));
            }

            $files = array();
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $relativePath = str_replace($dir . '/', '', $file->getPathname());
                    $files[] = array(
                        'name' => $file->getFilename(),
                        'path' => $folder . '/' . $relativePath,
                        'size' => $file->getSize(),
                        'size_human' => $this->formatBytes($file->getSize()),
                        'modified' => date('Y-m-d H:i:s', $file->getMTime()),
                        'extension' => $file->getExtension()
                    );
                }
            }

            // Ordenar por fecha de modificación descendente
            usort($files, function ($a, $b) {
                return strcmp($b['modified'], $a['modified']);
            });

            return $this->ok($res, array(
                'folder' => $folder,
                'total' => count($files),
                'files' => array_slice($files, 0, 100) // Máximo 100
            ));
        })
        ->queryParams(array(
            'folder' => array('type' => 'string', 'description' => 'Carpeta a listar (default: eapi_uploads)')
        ))
        ->tags('Archivos')
        ->describe('Lista los archivos de una carpeta.');


        // =====================================================================
        // Descargar archivo (devuelve Base64)
        // =====================================================================
        $this->get('/download', 'Descargar archivo', function ($req, $res) {
            $path = $this->query($req, 'path', '');

            if (empty($path)) {
                return $this->badRequest($res, 'El parámetro path es requerido');
            }

            // Seguridad: evitar path traversal
            $path = str_replace('..', '', $path);
            $filePath = DOL_DATA_ROOT . '/' . $path;

            if (!file_exists($filePath) || !is_file($filePath)) {
                return $this->notFound($res, 'Archivo no encontrado');
            }

            $content = file_get_contents($filePath);
            $filename = basename($filePath);
            $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            // Determinar MIME type
            $mimeTypes = array(
                'pdf' => 'application/pdf',
                'jpg' => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'gif' => 'image/gif',
                'doc' => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'xls' => 'application/vnd.ms-excel',
                'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'txt' => 'text/plain',
                'csv' => 'text/csv'
            );

            $mimeType = isset($mimeTypes[$extension]) ? $mimeTypes[$extension] : 'application/octet-stream';

            return $this->ok($res, array(
                'filename' => $filename,
                'mime_type' => $mimeType,
                'size' => strlen($content),
                'content' => base64_encode($content)
            ));
        })
        ->queryParams(array(
            'path' => array('type' => 'string', 'description' => 'Ruta del archivo (ej: eapi_uploads/2025/01/archivo.pdf)')
        ))
        ->tags('Archivos')
        ->describe('Descarga un archivo en formato Base64.');


        // =====================================================================
        // Eliminar archivo
        // =====================================================================
        $this->delete('/delete', 'Eliminar archivo', function ($req, $res) {
            $path = $this->query($req, 'path', '');

            if (empty($path)) {
                return $this->badRequest($res, 'El parámetro path es requerido');
            }

            // Seguridad
            $path = str_replace('..', '', $path);
            $filePath = DOL_DATA_ROOT . '/' . $path;

            if (!file_exists($filePath) || !is_file($filePath)) {
                return $this->notFound($res, 'Archivo no encontrado');
            }

            $result = unlink($filePath);

            if (!$result) {
                return $this->error($res, 'Error al eliminar el archivo');
            }

            $this->log("Archivo eliminado: $path", LOG_WARNING);

            return $this->noContent($res);
        })
        ->queryParams(array(
            'path' => array('type' => 'string', 'description' => 'Ruta del archivo a eliminar')
        ))
        ->tags('Archivos')
        ->describe('Elimina un archivo del sistema.');


        // =====================================================================
        // Adjuntar archivo a un objeto Dolibarr
        // =====================================================================
        $this->post('/attach/{object_type}/{object_id}', 'Adjuntar a objeto', function ($req, $res, $args) {
            $objectType = $args['object_type'];
            $objectId = (int) $args['object_id'];

            // Validar tipo de objeto
            $validTypes = array('societe', 'facture', 'propal', 'commande', 'product');
            if (!in_array($objectType, $validTypes)) {
                return $this->badRequest($res, 'Tipo de objeto no válido. Válidos: ' . implode(', ', $validTypes));
            }

            $schema = array(
                'required' => array('filename', 'content'),
                'properties' => array(
                    'filename' => array('type' => 'string'),
                    'content' => array('type' => 'string')
                )
            );

            $validation = $this->validateBody($req, $schema);
            if (!$validation['valid']) {
                return $this->badRequest($res, $validation['error']);
            }

            $data = $validation['data'];

            // Decodificar base64
            $fileContent = base64_decode($data['content']);
            if ($fileContent === false) {
                return $this->badRequest($res, 'Contenido Base64 inválido');
            }

            // Directorio de documentos del objeto
            $uploadDir = DOL_DATA_ROOT . '/' . $objectType . '/' . $objectId;
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $filename = basename($data['filename']);
            $filePath = $uploadDir . '/' . $filename;

            // Si existe, renombrar
            if (file_exists($filePath)) {
                $filename = date('Ymd_His') . '_' . $filename;
                $filePath = $uploadDir . '/' . $filename;
            }

            $result = file_put_contents($filePath, $fileContent);

            if ($result === false) {
                return $this->error($res, 'Error al guardar el archivo');
            }

            return $this->created($res, array(
                'message' => 'Archivo adjuntado correctamente',
                'object_type' => $objectType,
                'object_id' => $objectId,
                'filename' => $filename,
                'size' => strlen($fileContent)
            ));
        })
        ->pathParams(array(
            'object_type' => array('type' => 'string', 'description' => 'Tipo: societe, facture, propal, commande, product'),
            'object_id' => array('type' => 'integer', 'description' => 'ID del objeto')
        ))
        ->body(array(
            'required' => array('filename', 'content'),
            'properties' => array(
                'filename' => array('type' => 'string', 'description' => 'Nombre del archivo'),
                'content' => array('type' => 'string', 'description' => 'Contenido en Base64')
            )
        ))
        ->tags('Archivos')
        ->describe('Adjunta un archivo a un objeto de Dolibarr (tercero, factura, etc.).');
    }

    /**
     * Formatea bytes a formato legible
     */
    private function formatBytes(int $bytes): string
    {
        $units = array('B', 'KB', 'MB', 'GB');
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
