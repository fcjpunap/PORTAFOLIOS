<?php
// subir_ficha.php - Formulario Inteligente (Soporta PDF, ZIP y URLs externas)
session_set_cookie_params(14400);
ini_set("session.gc_maxlifetime", 14400);
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'estudiante') { header('Location: login.php'); exit; }
require_once 'config/conexion.php';

$estudiante_id = $_SESSION['usuario_id'];
$actividad_id = $_GET['id'] ?? null;
if (!$actividad_id) die("Actividad no especificada.");

// 1. Obtener detalles de la actividad y del estudiante
$stmt_act = $pdo->prepare("SELECT a.*, c.nombre_curso FROM actividades_fichas a JOIN cursos c ON a.curso_id = c.id WHERE a.id = ? AND a.habilitado = 1");
$stmt_act->execute([$actividad_id]);
$actividad = $stmt_act->fetch(PDO::FETCH_ASSOC);
if (!$actividad) die("Actividad no encontrada.");

if ($actividad['fecha_limite'] && strtotime($actividad['fecha_limite']) < time()) {
    die("El plazo para enviar esta actividad ha vencido.");
}

$stmt_est = $pdo->prepare("SELECT codigo_estudiante, apellidos FROM usuarios WHERE id = ?");
$stmt_est->execute([$estudiante_id]);
$datos_estudiante = $stmt_est->fetch(PDO::FETCH_ASSOC);
$codigo_est = $datos_estudiante['codigo_estudiante'] ?: 'SIN_CODIGO';

$es_grupal = ($actividad['tipo_trabajo'] === 'Grupal');

$secciones = [];
if (!empty($actividad['secciones_json'])) {
    $secciones = json_decode($actividad['secciones_json'], true);
} else {
    $secciones = [['titulo' => 'Desarrollo General', 'guia' => 'Escriba aquí su respuesta']];
}

// 2. Obtener compañeros disponibles
$companeros = [];
if ($es_grupal) {
    $sql_comp = "SELECT u.id, u.nombres, u.apellidos FROM usuarios u JOIN matriculas m ON u.id = m.estudiante_id WHERE m.curso_id = ? AND u.id != ? AND u.rol = 'estudiante' AND u.id NOT IN (SELECT estudiante_id FROM envio_integrantes ei JOIN envios_fichas ef ON ei.envio_id = ef.id WHERE ef.actividad_id = ?) ORDER BY u.apellidos ASC";
    $stmt_comp = $pdo->prepare($sql_comp);
    $stmt_comp->execute([$actividad['curso_id'], $estudiante_id, $actividad_id]);
    $companeros = $stmt_comp->fetchAll(PDO::FETCH_ASSOC);
}

// 3. Procesar el envío
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $respuestas_post = $_POST['respuestas'] ?? [];
    $integrantes = $_POST['integrantes'] ?? [];
    $url_externa = trim($_POST['url_externa'] ?? '');
    
    try {
        $pdo->beginTransaction();
        $respuestas_json = json_encode($respuestas_post, JSON_UNESCAPED_UNICODE);

        // Guardar Ficha
        $stmt_ins = $pdo->prepare("INSERT INTO envios_fichas (actividad_id, lider_id, estado, respuestas_json, factum, tipicidad, dogmatica, jurisprudencia, fallo) VALUES (?, ?, 'Enviado', ?, '', '', '', '', '')");
        $stmt_ins->execute([$actividad_id, $estudiante_id, $respuestas_json]);
        $envio_id = $pdo->lastInsertId();

        // Guardar Integrantes
        $stmt_int = $pdo->prepare("INSERT INTO envio_integrantes (envio_id, estudiante_id) VALUES (?, ?)");
        $stmt_int->execute([$envio_id, $estudiante_id]); 
        if ($es_grupal) { foreach ($integrantes as $comp_id) { $stmt_int->execute([$envio_id, $comp_id]); } }

        // PROCESAMIENTO DE ANEXOS: 1. Verifica URL, 2. Verifica Archivos (PDF/ZIP)
        if (!empty($url_externa) && filter_var($url_externa, FILTER_VALIDATE_URL)) {
            // Guardar Enlace Externo
            $pdo->prepare("INSERT INTO anexos (envio_id, nombre_archivo, ruta_archivo, tipo_archivo) VALUES (?, ?, ?, 'url')")
                ->execute([$envio_id, 'Enlace a Expediente Externo', $url_externa]);
        }
        
        if (isset($_FILES['anexos']) && $_FILES['anexos']['error'][0] !== UPLOAD_ERR_NO_FILE) {
            $anio_actual = date('Y');
            $upload_dir_pdf = 'uploads/anexos/';
            $upload_dir_html = 'uploads/expedientes/' . $anio_actual . '/' . strtolower($codigo_est) . '_' . $actividad_id . '/';
            
            if (!file_exists($upload_dir_pdf)) mkdir($upload_dir_pdf, 0777, true);
            
            foreach ($_FILES['anexos']['tmp_name'] as $key => $tmp) {
                if ($_FILES['anexos']['error'][$key] === UPLOAD_ERR_OK && $_FILES['anexos']['size'][$key] > 0) {
                    $nombre_original = $_FILES['anexos']['name'][$key];
                    $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));

                    // Si sube un ZIP (Descomprimir de forma segura y buscar HTML)
                    if ($extension === 'zip') {
                        if (!file_exists($upload_dir_html)) mkdir($upload_dir_html, 0777, true);
                        
                        $zip = new ZipArchive;
                        if ($zip->open($tmp) === TRUE) {
                            $allowed_exts = ['html', 'htm', 'css', 'js', 'json', 'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp', 'pdf', 'txt', 'mp4', 'webm', 'woff', 'woff2', 'ttf'];
                            $disallowed_exts = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'sh', 'exe', 'bat', 'cgi', 'pl', 'py', 'htaccess'];
                            
                            for ($i = 0; $i < $zip->numFiles; $i++) {
                                $stat = $zip->statIndex($i);
                                $filename = ltrim($stat['name'], '/\\');
                                
                                // Ignorar carpetas y rutas que intentan escapar del directorio (directory traversal)
                                if (substr($filename, -1) === '/' || strpos($filename, '../') !== false || strpos($filename, '..\\') !== false) {
                                    continue;
                                }
                                
                                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                                
                                // Extraer solo si NO está en la lista negra y SÍ está en la lista blanca
                                if (!in_array($ext, $disallowed_exts) && in_array($ext, $allowed_exts)) {
                                    $file_content = $zip->getFromIndex($i);
                                    if ($file_content !== false) {
                                        $file_dest_path = $upload_dir_html . $filename;
                                        $file_dest_dir = dirname($file_dest_path);
                                        if (!file_exists($file_dest_dir)) mkdir($file_dest_dir, 0777, true);
                                        file_put_contents($file_dest_path, $file_content);
                                    }
                                }
                            }
                            $zip->close();
                            
                            $ruta_index = $upload_dir_html . 'index.html';
                            if (!file_exists($ruta_index)) {
                                $ruta_index = $upload_dir_html; 
                            }

                            $pdo->prepare("INSERT INTO anexos (envio_id, nombre_archivo, ruta_archivo, tipo_archivo) VALUES (?, ?, ?, 'html')")
                                ->execute([$envio_id, 'Expediente Web', $ruta_index]);
                        }
                    } 
                    // Si sube un PDF normal
                    elseif ($extension === 'pdf') {
                        $nombre_seguro = time() . '_' . preg_replace("/[^a-zA-Z0-9.-]/", "_", $nombre_original);
                        $destino = $upload_dir_pdf . $nombre_seguro;
                        if (move_uploaded_file($tmp, $destino)) {
                            $pdo->prepare("INSERT INTO anexos (envio_id, nombre_archivo, ruta_archivo, tipo_archivo) VALUES (?, ?, ?, 'pdf')")
                                ->execute([$envio_id, $nombre_original, $destino]);
                        }
                    }
                }
            }
        }

        $pdo->commit();
        header('Location: ver_casos.php?curso_id=' . $actividad['curso_id'] . '&msg=enviado');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("Error al enviar: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Subir Resolución | UNAP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> body { background-color: #f8f9fa; } .bg-unap { background-color: #0b2e59; } </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-unap shadow-sm">
        <div class="container"><a class="navbar-brand" href="ver_casos.php?curso_id=<?= $actividad['curso_id'] ?>"><i class="fas fa-arrow-left me-2"></i> Volver</a></div>
    </nav>
    <main class="container py-4">
        <div class="card shadow-sm border-0 p-4 border-top border-4 border-primary">
            <h4 class="fw-bold mb-1"><?= htmlspecialchars($actividad['titulo_caso']) ?></h4>
            <span class="badge bg-<?= $es_grupal ? 'primary' : 'secondary' ?> mb-3"><?= $es_grupal ? 'Trabajo Grupal' : 'Trabajo Individual' ?></span>
            
            <?php if (!empty($actividad['descripcion'])): ?>
            <div class="alert alert-secondary text-dark shadow-sm border-0 mb-4" style="text-align: justify; white-space: pre-line;">
                <strong><i class="fas fa-info-circle me-1"></i> Lineamientos del Caso / Descripción:</strong><br>
                <?= htmlspecialchars($actividad['descripcion']) ?>
            </div>
            <?php endif; ?>
            
            <form action="" method="POST" enctype="multipart/form-data">
                
                <?php if ($es_grupal): ?>
                <div class="alert alert-info p-3 mb-4 shadow-sm border-0">
                    <label class="fw-bold mb-2">Selecciona a tus compañeros:</label>
                    <select class="form-select select2" name="integrantes[]" multiple="multiple">
                        <?php foreach($companeros as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['apellidos'].', '.$c['nombres']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <h5 class="fw-bold border-bottom pb-2 mb-3 mt-4 text-secondary"><i class="fas fa-edit me-2"></i> Desarrollo Estructurado</h5>
                
                <?php foreach($secciones as $sec): ?>
                    <div class="mb-4">
                        <label class="fw-bold text-dark fs-5"><?= htmlspecialchars($sec['titulo']) ?></label>
                        <p class="small text-muted mb-2"><i class="fas fa-info-circle me-1"></i> <?= htmlspecialchars($sec['guia']) ?></p>
                        <textarea class="form-control shadow-sm" name="respuestas[<?= htmlspecialchars($sec['titulo']) ?>]" rows="3" required></textarea>
                    </div>
                <?php endforeach; ?>

                <h5 class="fw-bold border-bottom pb-2 mb-3 mt-5 text-secondary"><i class="fas fa-paperclip me-2"></i> Adjuntar Proyecto (Elige una opción)</h5>

                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="p-4 bg-white rounded border h-100 shadow-sm">
                            <label class="fw-bold text-primary mb-2 fs-5"><i class="fas fa-link me-2"></i> 1. Enlace Externo (Web)</label>
                            <p class="small text-muted mb-3">Si alojaste tu expediente en GitHub Pages, Vercel u otro servidor, pega la ruta aquí.</p>
                            <input type="url" name="url_externa" class="form-control" placeholder="https://mi-expediente.com">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="p-4 bg-white rounded border h-100 shadow-sm">
                            <label class="fw-bold text-success mb-2 fs-5"><i class="fas fa-file-upload me-2"></i> 2. Subir Archivo</label>
                            <p class="small text-muted mb-3">Sube tu documento en formato <strong>PDF</strong> o un <strong>ZIP</strong> con tu web completa.</p>
                            <input type="file" name="anexos[]" class="form-control" accept=".pdf, .zip">
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 fw-bold btn-lg shadow-sm"><i class="fas fa-paper-plane me-2"></i> Enviar Proyecto Final</button>
            </form>
        </div>
    </main>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script> $(document).ready(function() { $('.select2').select2({ placeholder: "Busca apellidos...", theme: "classic" }); }); </script>
</body>
</html>