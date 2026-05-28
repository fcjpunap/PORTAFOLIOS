<?php
// editar_envio.php - Editar el contenido textual y gestionar el expediente web
session_set_cookie_params(14400);
ini_set("session.gc_maxlifetime", 14400);
session_start();
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['docente', 'admin'])) {
    header('Location: login.php');
    exit;
}
require_once 'config/conexion.php';

$envio_id = $_GET['id'] ?? null;
if (!$envio_id) die("ID de envío no especificado.");

$mensaje = ''; $tipo_mensaje = '';

// 1. Guardar cambios en los textos (JSON o legacy)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_textos'])) {
    if (isset($_POST['respuestas']) && is_array($_POST['respuestas'])) {
        $respuestas_json = json_encode($_POST['respuestas'], JSON_UNESCAPED_UNICODE);
        $stmt_upd = $pdo->prepare("UPDATE envios_fichas SET respuestas_json = ? WHERE id = ?");
        $stmt_upd->execute([$respuestas_json, $envio_id]);
    } else {
        $factum = $_POST['factum'] ?? '';
        $tipicidad = $_POST['tipicidad'] ?? '';
        $dogmatica = $_POST['dogmatica'] ?? '';
        $jurisprudencia = $_POST['jurisprudencia'] ?? '';
        $fallo = $_POST['fallo'] ?? '';
        $stmt_upd = $pdo->prepare("UPDATE envios_fichas SET factum=?, tipicidad=?, dogmatica=?, jurisprudencia=?, fallo=? WHERE id=?");
        $stmt_upd->execute([$factum, $tipicidad, $dogmatica, $jurisprudencia, $fallo, $envio_id]);
    }
    $mensaje = "Textos del envío actualizados correctamente.";
    $tipo_mensaje = "success";
}

// Obtener datos del envío
$stmt = $pdo->prepare("
    SELECT e.id, e.actividad_id, e.respuestas_json, e.factum, e.tipicidad, e.dogmatica, e.jurisprudencia, e.fallo,
           a.titulo_caso, u.nombres, u.apellidos
    FROM envios_fichas e
    JOIN actividades_fichas a ON e.actividad_id = a.id
    LEFT JOIN usuarios u ON e.lider_id = u.id
    WHERE e.id = ?
");
$stmt->execute([$envio_id]);
$envio = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$envio) die("El trabajo solicitado no existe.");

// Verificar si hay expediente Web (HTML) para mostrar el gestor
$stmt_anexos = $pdo->prepare("SELECT ruta_archivo FROM anexos WHERE envio_id = ? AND tipo_archivo = 'html'");
$stmt_anexos->execute([$envio_id]);
$anexo_html = $stmt_anexos->fetch(PDO::FETCH_ASSOC);
$tiene_gestor = ($anexo_html !== false);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Envío | UNAP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> 
        body { background-color: #f0f2f5; } 
        .bg-unap { background-color: #0b2e59; }
        .file-item:hover { background-color: #f8f9fa; }
        .cursor-pointer { cursor: pointer; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-unap shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="calificar.php?id=<?= $envio_id ?>">
                <i class="fas fa-arrow-left me-2"></i> Volver a Calificar
            </a>
            <span class="text-white fw-bold d-none d-md-inline">
                Editar Trabajo de: <?= htmlspecialchars($envio['apellidos'] . ', ' . $envio['nombres']) ?>
            </span>
        </div>
    </nav>

    <div class="container py-4">
        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipo_mensaje ?> shadow-sm"><i class="fas fa-info-circle me-1"></i> <?= $mensaje ?></div>
        <?php endif; ?>

        <div class="row">
            <!-- Sección 1: Editar Ficha de Respuestas -->
            <div class="col-lg-12 mb-4">
                <div class="card shadow-sm border-0 border-top border-4 border-primary">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 fw-bold text-secondary"><i class="fas fa-edit text-primary me-2"></i> Modificar Contenido Textual de la Ficha</h5>
                    </div>
                    <div class="card-body p-4">
                        <form action="" method="POST">
                            <input type="hidden" name="guardar_textos" value="1">
                            <?php 
                            if (!empty($envio['respuestas_json'])) {
                                $respuestas = json_decode($envio['respuestas_json'], true);
                                if (is_array($respuestas)) {
                                    foreach ($respuestas as $pregunta => $respuesta) {
                                        echo "<div class='mb-3'>";
                                        echo "<label class='fw-bold mb-1'>" . htmlspecialchars($pregunta) . "</label>";
                                        echo "<textarea class='form-control shadow-sm' name='respuestas[" . htmlspecialchars($pregunta) . "]' rows='4'>" . htmlspecialchars($respuesta) . "</textarea>";
                                        echo "</div>";
                                    }
                                }
                            } else {
                                $legacy = [
                                    'factum' => ['Factum', $envio['factum']], 
                                    'tipicidad' => ['Tipicidad', $envio['tipicidad']], 
                                    'dogmatica' => ['Dogmática', $envio['dogmatica']], 
                                    'jurisprudencia' => ['Jurisprudencia', $envio['jurisprudencia']], 
                                    'fallo' => ['Fallo', $envio['fallo']]
                                ];
                                foreach ($legacy as $key => $data) {
                                    echo "<div class='mb-3'>";
                                    echo "<label class='fw-bold mb-1'>" . htmlspecialchars($data[0]) . "</label>";
                                    echo "<textarea class='form-control shadow-sm' name='" . htmlspecialchars($key) . "' rows='4'>" . htmlspecialchars($data[1]) . "</textarea>";
                                    echo "</div>";
                                }
                            }
                            ?>
                            <button type="submit" class="btn btn-success fw-bold px-4 shadow-sm"><i class="fas fa-save me-2"></i> Guardar Cambios del Texto</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Sección 2: Gestor de Archivos (Expediente Web) -->
            <?php if ($tiene_gestor): ?>
            <div class="col-lg-12 mb-4">
                <div class="card shadow-sm border-0 border-top border-4 border-info">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="mb-0 fw-bold text-secondary"><i class="fas fa-folder-open text-info me-2"></i> Gestor de Expediente Web (Archivos ZIP)</h5>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-success fw-bold" onclick="showNewFolder()"><i class="fas fa-folder-plus me-1"></i> Nueva Carpeta</button>
                            <button class="btn btn-sm btn-outline-primary fw-bold" onclick="showUpload()"><i class="fas fa-upload me-1"></i> Subir Archivo</button>
                            <button class="btn btn-sm btn-outline-warning fw-bold text-dark" id="btnPaste" style="display:none;" onclick="pasteItem()"><i class="fas fa-paste me-1"></i> Pegar</button>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <!-- Path Breadcrumb -->
                        <div class="bg-light p-2 border-bottom text-muted small d-flex align-items-center">
                            <i class="fas fa-home me-2 text-primary cursor-pointer" onclick="loadDir('')"></i> 
                            <span id="currentPathDisplay">/</span>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3" style="width: 50%;">Nombre</th>
                                        <th>Tamaño</th>
                                        <th class="text-end pe-3">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="fileList">
                                    <tr><td colspan="3" class="text-center py-4 text-muted">Cargando archivos...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($tiene_gestor): ?>
    <!-- Modal Subir Archivo -->
    <div class="modal fade" id="modalUpload" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="uploadForm">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title">Subir Archivo Seguro</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted small">Solo se permiten extensiones web (HTML, CSS, JS, JSON, TXT e imágenes). Ningún script malicioso será aceptado.</p>
                        <input type="file" id="fileUploadInput" class="form-control" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Subir</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts del Gestor de Archivos -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const ENVIO_ID = <?= $envio_id ?>;
        let currentDir = '';
        let clipboard = null; // { action: 'copy'|'move', path: '...' }

        function formatBytes(bytes) {
            if(bytes === 0) return '0 B';
            const k = 1024, dm = 2, sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }

        function loadDir(dir) {
            currentDir = dir;
            $('#currentPathDisplay').text('/' + dir);
            $('#fileList').html('<tr><td colspan="3" class="text-center py-4"><i class="fas fa-spinner fa-spin text-primary"></i> Cargando...</td></tr>');
            
            $.post('api_archivos.php', { action: 'list', envio_id: ENVIO_ID, dir: dir }, function(res) {
                if(res.success) {
                    renderFiles(res.files);
                } else {
                    alert("Error: " + res.error);
                }
            });
        }

        function renderFiles(files) {
            let html = '';
            if (currentDir !== '') {
                // Ir un nivel arriba
                let parentDir = currentDir.substring(0, currentDir.lastIndexOf('/'));
                html += `<tr class="cursor-pointer file-item" onclick="loadDir('${parentDir}')">
                            <td class="ps-3"><i class="fas fa-level-up-alt text-muted me-2"></i> <strong>..</strong></td>
                            <td></td><td></td>
                         </tr>`;
            }
            if (files.length === 0) {
                html += '<tr><td colspan="3" class="text-center py-4 text-muted">La carpeta está vacía.</td></tr>';
            } else {
                files.forEach(f => {
                    let icon = f.is_dir ? '<i class="fas fa-folder text-warning me-2"></i>' : '<i class="fas fa-file-code text-primary me-2"></i>';
                    if (['jpg','jpeg','png','gif','svg','webp'].includes(f.ext)) icon = '<i class="fas fa-file-image text-success me-2"></i>';
                    if (f.ext === 'pdf') icon = '<i class="fas fa-file-pdf text-danger me-2"></i>';

                    let nameHtml = f.is_dir 
                        ? `<span class="cursor-pointer text-dark text-decoration-none" onclick="loadDir('${f.path}')">${icon} <strong>${f.name}</strong></span>`
                        : `<span>${icon} ${f.name}</span>`;
                        
                    html += `<tr class="file-item">
                        <td class="ps-3">${nameHtml}</td>
                        <td class="text-muted small">${f.is_dir ? '-' : formatBytes(f.size)}</td>
                        <td class="text-end pe-3">
                            <div class="dropdown">
                                <button class="btn btn-sm btn-light dropdown-toggle border shadow-sm" type="button" data-bs-toggle="dropdown"><i class="fas fa-ellipsis-v"></i></button>
                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="renameItem('${f.path}', '${f.name}')"><i class="fas fa-edit me-2"></i> Renombrar</a></li>
                                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="setClipboard('copy', '${f.path}')"><i class="fas fa-copy me-2"></i> Copiar</a></li>
                                    <li><a class="dropdown-item" href="javascript:void(0)" onclick="setClipboard('move', '${f.path}')"><i class="fas fa-cut me-2"></i> Cortar</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="deleteItem('${f.path}')"><i class="fas fa-trash-alt me-2"></i> Eliminar</a></li>
                                </ul>
                            </div>
                        </td>
                    </tr>`;
                });
            }
            $('#fileList').html(html);
        }

        // Acciones CRUD
        function deleteItem(path) {
            if(confirm('¿Eliminar este elemento permanentemente?')) {
                $.post('api_archivos.php', { action: 'delete', envio_id: ENVIO_ID, path: path }, function(res) {
                    if(res.success) loadDir(currentDir);
                    else alert(res.error);
                });
            }
        }

        function renameItem(path, oldName) {
            let newName = prompt('Nuevo nombre para: ' + oldName, oldName);
            if(newName && newName !== oldName) {
                $.post('api_archivos.php', { action: 'rename', envio_id: ENVIO_ID, path: path, new_name: newName }, function(res) {
                    if(res.success) loadDir(currentDir);
                    else alert(res.error);
                });
            }
        }

        function showNewFolder() {
            let name = prompt('Nombre de la nueva carpeta:');
            if(name) {
                $.post('api_archivos.php', { action: 'create_dir', envio_id: ENVIO_ID, dir: currentDir, name: name }, function(res) {
                    if(res.success) loadDir(currentDir);
                    else alert(res.error);
                });
            }
        }

        // Clipboard
        function setClipboard(action, path) {
            clipboard = { action: action, path: path };
            $('#btnPaste').show();
            alert((action === 'copy' ? 'Copiado' : 'Cortado') + ' al portapapeles. Ve a otra carpeta y presiona Pegar.');
        }

        function pasteItem() {
            if(!clipboard) return;
            $.post('api_archivos.php', { action: clipboard.action, envio_id: ENVIO_ID, source: clipboard.path, dest_dir: currentDir }, function(res) {
                if(res.success) {
                    if(clipboard.action === 'move') { clipboard = null; $('#btnPaste').hide(); }
                    loadDir(currentDir);
                } else {
                    alert(res.error);
                }
            });
        }

        // Subir archivo
        const uploadModal = new bootstrap.Modal(document.getElementById('modalUpload'));
        function showUpload() {
            $('#fileUploadInput').val('');
            uploadModal.show();
        }

        $('#uploadForm').submit(function(e) {
            e.preventDefault();
            let file = $('#fileUploadInput')[0].files[0];
            if(!file) return;

            let formData = new FormData();
            formData.append('action', 'upload');
            formData.append('envio_id', ENVIO_ID);
            formData.append('dir', currentDir);
            formData.append('file', file);

            $.ajax({
                url: 'api_archivos.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res) {
                    if(res.success) {
                        uploadModal.hide();
                        loadDir(currentDir);
                    } else {
                        alert(res.error);
                    }
                }
            });
        });

        $(document).ready(function() { loadDir(''); });
    </script>
    <?php endif; ?>
</body>
</html>
