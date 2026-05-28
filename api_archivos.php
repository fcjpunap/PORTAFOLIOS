<?php
// api_archivos.php - API backend para el gestor de archivos
session_set_cookie_params(14400);
ini_set("session.gc_maxlifetime", 14400);
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['docente', 'admin'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

require_once 'config/conexion.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$envio_id = $_POST['envio_id'] ?? $_GET['envio_id'] ?? null;

if (!$envio_id) {
    echo json_encode(['success' => false, 'error' => 'ID de envío no especificado']);
    exit;
}

// Obtener la ruta base permitida para este envío (la carpeta HTML/ZIP extraída)
$stmt = $pdo->prepare("SELECT ruta_archivo FROM anexos WHERE envio_id = ? AND tipo_archivo = 'html'");
$stmt->execute([$envio_id]);
$anexo_html = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$anexo_html) {
    echo json_encode(['success' => false, 'error' => 'No hay expediente web (HTML/ZIP) asociado a este envío']);
    exit;
}

$ruta_base = is_dir($anexo_html['ruta_archivo']) ? $anexo_html['ruta_archivo'] : dirname($anexo_html['ruta_archivo']);
$base_path_real = realpath($ruta_base);

if (!$base_path_real || !is_dir($base_path_real)) {
    echo json_encode(['success' => false, 'error' => 'Directorio base no encontrado']);
    exit;
}

// Utilidades de seguridad
function get_secure_path($base_dir, $requested_path) {
    $requested_path = trim($requested_path, '/\\');
    $target_path = $base_dir . DIRECTORY_SEPARATOR . $requested_path;
    // Si el archivo no existe, realpath devolverá false, así que solo chequeamos el directorio padre
    $parent_dir = dirname($target_path);
    $real_parent = realpath($parent_dir);
    
    if ($real_parent === false || strpos($real_parent, $base_dir) !== 0) {
        return false;
    }
    return $target_path;
}

function is_allowed_extension($filename) {
    $allowed = ['html', 'htm', 'css', 'js', 'json', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'txt'];
    $disallowed = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'sh', 'exe', 'bat', 'cgi', 'pl', 'py', 'htaccess'];
    
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if (empty($ext) && is_dir($filename)) return true; // Directorios están bien
    if (in_array($ext, $disallowed)) return false;
    if (in_array($ext, $allowed)) return true;
    
    return false; // Por defecto rechazar si no está en lista blanca
}

function delete_dir($dirPath) {
    if (!is_dir($dirPath)) return false;
    if (substr($dirPath, strlen($dirPath) - 1, 1) != '/') $dirPath .= '/';
    $files = glob($dirPath . '*', GLOB_MARK);
    foreach ($files as $file) {
        if (is_dir($file)) delete_dir($file);
        else unlink($file);
    }
    return rmdir($dirPath);
}

// Procesar acciones
switch ($action) {
    case 'list':
        $dir_param = $_GET['dir'] ?? '';
        $target_dir = get_secure_path($base_path_real, $dir_param);
        
        if (!$target_dir || !is_dir($target_dir)) {
            echo json_encode(['success' => false, 'error' => 'Directorio inválido']);
            exit;
        }
        
        $files = [];
        $items = array_diff(scandir($target_dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $target_dir . DIRECTORY_SEPARATOR . $item;
            $is_dir = is_dir($path);
            $files[] = [
                'name' => $item,
                'is_dir' => $is_dir,
                'path' => ltrim($dir_param . '/' . $item, '/'),
                'size' => $is_dir ? 0 : filesize($path),
                'ext' => $is_dir ? '' : strtolower(pathinfo($item, PATHINFO_EXTENSION))
            ];
        }
        
        // Sort: folders first, then files alphabetically
        usort($files, function($a, $b) {
            if ($a['is_dir'] && !$b['is_dir']) return -1;
            if (!$a['is_dir'] && $b['is_dir']) return 1;
            return strcasecmp($a['name'], $b['name']);
        });
        
        echo json_encode(['success' => true, 'files' => $files]);
        break;

    case 'upload':
        $dir_param = $_POST['dir'] ?? '';
        $target_dir = get_secure_path($base_path_real, $dir_param);
        
        if (!$target_dir || !is_dir($target_dir)) {
            echo json_encode(['success' => false, 'error' => 'Directorio destino inválido']);
            exit;
        }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Error al subir el archivo']);
            exit;
        }

        $filename = basename($_FILES['file']['name']);
        if (!is_allowed_extension($filename)) {
            echo json_encode(['success' => false, 'error' => 'Tipo de archivo no permitido. Solo se aceptan: html, css, js, json, txt e imágenes.']);
            exit;
        }

        $dest_path = $target_dir . DIRECTORY_SEPARATOR . $filename;
        if (move_uploaded_file($_FILES['file']['tmp_name'], $dest_path)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No se pudo guardar el archivo']);
        }
        break;

    case 'create_dir':
        $dir_param = $_POST['dir'] ?? '';
        $new_name = trim($_POST['name'] ?? '');
        
        if (empty($new_name) || strpos($new_name, '/') !== false || strpos($new_name, '\\') !== false) {
            echo json_encode(['success' => false, 'error' => 'Nombre de carpeta inválido']);
            exit;
        }
        
        $target_dir = get_secure_path($base_path_real, $dir_param);
        if (!$target_dir || !is_dir($target_dir)) {
            echo json_encode(['success' => false, 'error' => 'Directorio base inválido']);
            exit;
        }

        $new_dir_path = $target_dir . DIRECTORY_SEPARATOR . $new_name;
        if (file_exists($new_dir_path)) {
            echo json_encode(['success' => false, 'error' => 'La carpeta ya existe']);
            exit;
        }

        if (mkdir($new_dir_path, 0777, true)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Error al crear la carpeta']);
        }
        break;

    case 'delete':
        $path_param = $_POST['path'] ?? '';
        $target_path = get_secure_path($base_path_real, $path_param);
        $real_target = realpath($target_path);
        
        // Evitar borrar el directorio raíz del alumno
        if ($real_target === $base_path_real) {
            echo json_encode(['success' => false, 'error' => 'No puedes eliminar el directorio principal']);
            exit;
        }

        if (!$real_target || strpos($real_target, $base_path_real) !== 0) {
            echo json_encode(['success' => false, 'error' => 'Archivo o directorio inválido']);
            exit;
        }

        if (is_dir($real_target)) {
            $success = delete_dir($real_target);
        } else {
            $success = unlink($real_target);
        }

        echo json_encode(['success' => $success, 'error' => $success ? '' : 'No se pudo eliminar']);
        break;

    case 'rename':
        $path_param = $_POST['path'] ?? '';
        $new_name = trim($_POST['new_name'] ?? '');
        
        if (empty($new_name) || strpos($new_name, '/') !== false || strpos($new_name, '\\') !== false) {
            echo json_encode(['success' => false, 'error' => 'Nombre inválido']);
            exit;
        }
        
        $target_path = get_secure_path($base_path_real, $path_param);
        $real_target = realpath($target_path);

        if ($real_target === $base_path_real) {
            echo json_encode(['success' => false, 'error' => 'No puedes renombrar el directorio principal']);
            exit;
        }

        if (!$real_target || strpos($real_target, $base_path_real) !== 0) {
            echo json_encode(['success' => false, 'error' => 'Archivo o directorio inválido']);
            exit;
        }

        // Verificar extensión del nuevo nombre si es un archivo
        if (!is_dir($real_target) && !is_allowed_extension($new_name)) {
             echo json_encode(['success' => false, 'error' => 'Extensión no permitida para renombrar']);
             exit;
        }

        $new_path = dirname($real_target) . DIRECTORY_SEPARATOR . $new_name;
        if (file_exists($new_path)) {
            echo json_encode(['success' => false, 'error' => 'Ya existe un archivo con ese nombre']);
            exit;
        }

        if (rename($real_target, $new_path)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No se pudo renombrar']);
        }
        break;

    case 'move':
    case 'copy':
        $source_param = $_POST['source'] ?? '';
        $dest_dir_param = $_POST['dest_dir'] ?? '';
        
        $source_path = get_secure_path($base_path_real, $source_param);
        $dest_dir = get_secure_path($base_path_real, $dest_dir_param);
        
        $real_source = realpath($source_path);
        if (!$real_source || strpos($real_source, $base_path_real) !== 0 || $real_source === $base_path_real) {
            echo json_encode(['success' => false, 'error' => 'Ruta de origen inválida']);
            exit;
        }
        
        if (!$dest_dir || !is_dir($dest_dir)) {
            echo json_encode(['success' => false, 'error' => 'Directorio destino inválido']);
            exit;
        }
        
        $filename = basename($real_source);
        $dest_path = $dest_dir . DIRECTORY_SEPARATOR . $filename;
        
        if (file_exists($dest_path)) {
            echo json_encode(['success' => false, 'error' => 'Ya existe un archivo con ese nombre en el destino']);
            exit;
        }

        if ($action === 'move') {
            $success = rename($real_source, $dest_path);
        } else {
            // copy
            if (is_dir($real_source)) {
                // Función simple para copiar directorio (no recursiva para evitar loops/problemas o requeriría más lógica, 
                // pero si quieren copiar directorios, hagámoslo recursivo).
                function xcopy($src, $dest) {
                    mkdir($dest, 0777, true);
                    foreach (scandir($src) as $file) {
                        if ($file === '.' || $file === '..') continue;
                        $path = $src . DIRECTORY_SEPARATOR . $file;
                        if (is_dir($path)) xcopy($path, $dest . DIRECTORY_SEPARATOR . $file);
                        else copy($path, $dest . DIRECTORY_SEPARATOR . $file);
                    }
                }
                xcopy($real_source, $dest_path);
                $success = true;
            } else {
                $success = copy($real_source, $dest_path);
            }
        }
        
        echo json_encode(['success' => $success, 'error' => $success ? '' : "No se pudo realizar la operación"]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Acción desconocida']);
        break;
}
