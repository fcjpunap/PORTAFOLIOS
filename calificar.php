<?php
// calificar.php - Interfaz de Calificación inteligente (Soporte PDF, Expediente Web y URL Externa)
session_start();
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['docente', 'admin'])) {
    header('Location: login.php');
    exit;
}
require_once 'config/conexion.php';

$envio_id = $_GET['id'] ?? null;
if (!$envio_id) die("ID de envío no especificado.");

$mensaje = ''; $tipo_mensaje = ''; $error_db = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'equipo_actualizado') {
    $mensaje = "Equipo de trabajo actualizado exitosamente.";
    $tipo_mensaje = "success";
}

try {
    // Procesar edición de equipo
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_equipo'])) {
        $nuevos_integrantes = $_POST['integrantes'] ?? [];
        
        $stmt_lider = $pdo->prepare("SELECT lider_id FROM envios_fichas WHERE id = ?");
        $stmt_lider->execute([$envio_id]);
        $lider_id = $stmt_lider->fetchColumn();
        
        $pdo->beginTransaction();
        try {
            $stmt_del = $pdo->prepare("DELETE FROM envio_integrantes WHERE envio_id = ?");
            $stmt_del->execute([$envio_id]);
            
            $stmt_ins = $pdo->prepare("INSERT INTO envio_integrantes (envio_id, estudiante_id) VALUES (?, ?)");
            $stmt_ins->execute([$envio_id, $lider_id]);
            
            foreach ($nuevos_integrantes as $comp_id) {
                if ($comp_id != $lider_id) {
                    $stmt_ins->execute([$envio_id, $comp_id]);
                }
            }
            $pdo->commit();
            header("Location: calificar.php?id=$envio_id&msg=equipo_actualizado");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_db = "Error al actualizar equipo: " . $e->getMessage();
        }
    }

    // Procesar calificación
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calificar'])) {
        $calificacion = $_POST['calificacion'];
        $retroalimentacion = trim($_POST['retroalimentacion']);
        
        $stmt_upd = $pdo->prepare("UPDATE envios_fichas SET calificacion = ?, retroalimentacion = ?, estado = 'Revisado' WHERE id = ?");
        if ($stmt_upd->execute([$calificacion, $retroalimentacion, $envio_id])) {
            $mensaje = "¡Calificación guardada exitosamente!";
            $tipo_mensaje = "success";
        }
    }

    // Obtener datos del envío
    $stmt = $pdo->prepare("
        SELECT e.id, e.actividad_id, e.lider_id, e.calificacion, e.retroalimentacion, e.fecha_envio, e.respuestas_json,
               e.factum, e.tipicidad, e.dogmatica, e.jurisprudencia, e.fallo,
               a.titulo_caso, a.tipo_trabajo, a.curso_id, a.descripcion,
               u.nombres, u.apellidos, u.codigo_estudiante
        FROM envios_fichas e
        JOIN actividades_fichas a ON e.actividad_id = a.id
        LEFT JOIN usuarios u ON e.lider_id = u.id
        WHERE e.id = ?
    ");
    $stmt->execute([$envio_id]);
    $envio = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$envio) die("El trabajo solicitado no existe.");

    // Obtener el archivo adjunto (PDF, HTML/ZIP o URL Externa)
    $stmt_anexo = $pdo->prepare("SELECT nombre_archivo, ruta_archivo, tipo_archivo FROM anexos WHERE envio_id = ? LIMIT 1");
    $stmt_anexo->execute([$envio_id]);
    $anexo = $stmt_anexo->fetch(PDO::FETCH_ASSOC);

    $es_grupal = ($envio['tipo_trabajo'] === 'Grupal');
    
    // Obtener integrantes si es grupal
    $integrantes = [];
    $companeros = [];
    if ($es_grupal) {
        $stmt_int = $pdo->prepare("SELECT u.id, u.nombres, u.apellidos, u.codigo_estudiante FROM envio_integrantes ei JOIN usuarios u ON ei.estudiante_id = u.id WHERE ei.envio_id = ? ORDER BY u.apellidos ASC");
        $stmt_int->execute([$envio_id]);
        $integrantes = $stmt_int->fetchAll(PDO::FETCH_ASSOC);
        
        $sql_comp = "SELECT u.id, u.nombres, u.apellidos FROM usuarios u JOIN matriculas m ON u.id = m.estudiante_id WHERE m.curso_id = ? AND u.rol = 'estudiante' ORDER BY u.apellidos ASC";
        $stmt_comp = $pdo->prepare($sql_comp);
        $stmt_comp->execute([$envio['curso_id']]);
        $companeros = $stmt_comp->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (PDOException $e) { $error_db = "Error: " . $e->getMessage(); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Calificar Trabajo | UNAP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style> body { background-color: #f0f2f5; } .bg-unap { background-color: #0b2e59; } .modal-xl-custom { max-width: 95%; } </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-unap shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= ($_SESSION['rol'] === 'admin') ? 'ver_todos_trabajos.php' : 'revisar_trabajos.php?id='.$envio['actividad_id'] ?>">
                <i class="fas fa-arrow-left me-2"></i> Volver a Envíos
            </a>
            <span class="text-white fw-bold d-none d-md-inline"><i class="fas fa-gavel me-2 text-warning"></i> <?= htmlspecialchars($envio['titulo_caso'] ?? 'Trabajo') ?></span>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <?php if ($error_db): ?><div class="alert alert-danger m-4"><?= $error_db ?></div><?php else: ?>
        <div class="row justify-content-center">
            
            <div class="col-lg-8 mb-4">
                <div class="card shadow-sm border-0 border-top border-4 border-primary">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                        <h4 class="mb-0 fw-bold text-secondary"><i class="fas fa-book-reader text-primary me-2"></i> Desarrollo de la Ficha</h4>
                        
                        <?php if ($anexo): ?>
                            <?php if ($anexo['tipo_archivo'] === 'url'): ?>
                                <a href="<?= htmlspecialchars($anexo['ruta_archivo']) ?>" target="_blank" class="btn btn-info text-white fw-bold shadow-sm">
                                    <i class="fas fa-external-link-alt me-2"></i> Visitar Web Externa
                                </a>
                            <?php elseif ($anexo['tipo_archivo'] === 'html'): ?>
                                <a href="<?= htmlspecialchars($anexo['ruta_archivo']) ?>" target="_blank" class="btn btn-dark fw-bold shadow-sm">
                                    <i class="fas fa-globe me-2"></i> Ver Expediente Web (ZIP)
                                </a>
                            <?php else: ?>
                                <button class="btn btn-danger fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalPDF">
                                    <i class="fas fa-file-pdf me-2"></i> Ver Anexo PDF
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="badge bg-secondary"><i class="fas fa-ban"></i> Sin adjunto extra</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="card-body p-4" style="font-size: 1.05rem; line-height: 1.6;">
                        <?php if (!empty($envio['descripcion'])): ?>
                        <div class="alert alert-secondary text-dark shadow-sm border-0 mb-4" style="text-align: justify; white-space: pre-line;">
                            <strong><i class="fas fa-info-circle me-1"></i> Lineamientos del Caso / Descripción:</strong><br>
                            <?= htmlspecialchars($envio['descripcion']) ?>
                        </div>
                        <?php endif; ?>
                        <?php 
                        // LÓGICA INTELIGENTE: Verifica si hay JSON, si no, busca en las columnas antiguas
                        if (!empty($envio['respuestas_json'])) {
                            $respuestas = json_decode($envio['respuestas_json'], true);
                            if(is_array($respuestas)) {
                                foreach($respuestas as $pregunta => $respuesta) {
                                    echo "<div class='mb-4 p-3 bg-light rounded border border-light-subtle'>";
                                    echo "<h5 class='fw-bold text-primary border-bottom pb-2 mb-3'>" . htmlspecialchars($pregunta) . "</h5>";
                                    echo "<p class='mb-0 text-justify'>" . nl2br(htmlspecialchars($respuesta)) . "</p></div>";
                                }
                            }
                        } elseif (!empty($envio['factum']) || !empty($envio['tipicidad'])) {
                            // RENDERIZADO PARA TRABAJOS ANTIGUOS
                            $legacy = ['Factum' => $envio['factum'], 'Dogmática' => $envio['dogmatica'], 'Tipicidad' => $envio['tipicidad'], 'Jurisprudencia' => $envio['jurisprudencia'], 'Fallo' => $envio['fallo']];
                            foreach($legacy as $titulo => $texto) {
                                if(!empty($texto)) {
                                    echo "<div class='mb-4 p-3 bg-light rounded border border-light-subtle'>";
                                    echo "<h5 class='fw-bold text-primary border-bottom pb-2 mb-3'>" . htmlspecialchars($titulo) . "</h5>";
                                    echo "<p class='mb-0 text-justify'>" . nl2br(htmlspecialchars($texto)) . "</p></div>";
                                }
                            }
                        } else {
                            echo "<div class='text-center py-5 text-muted'><i class='fas fa-info-circle fa-3x mb-3'></i><h5>El estudiante no ingresó textos en el formulario.</h5><p>Verifique el anexo adjunto.</p></div>";
                        }
                        ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm border-0 sticky-top" style="top: 20px;">
                    <div class="card-body p-4">
                        <?php if ($mensaje): ?><div class="alert alert-<?= $tipo_mensaje ?> shadow-sm"><i class="fas fa-check-circle me-1"></i> <?= $mensaje ?></div><?php endif; ?>
                        
                        <?php if ($es_grupal): ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="fw-bold text-secondary text-uppercase small mb-0">Equipo de Trabajo:</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalEditarEquipo">
                                    <i class="fas fa-edit"></i> Editar
                                </button>
                            </div>
                            <ul class="list-group shadow-sm mb-4">
                                <?php foreach($integrantes as $int): ?>
                                    <li class="list-group-item py-2 px-3 <?= ($int['codigo_estudiante'] === $envio['codigo_estudiante']) ? 'bg-light fw-bold border-primary border-start-3' : '' ?>">
                                        <?= htmlspecialchars($int['apellidos'] . ', ' . $int['nombres']) ?> 
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <h6 class="fw-bold text-secondary small mb-1">Autor:</h6>
                            <h5 class="text-dark fw-bold mb-4 border-bottom pb-2"><?= htmlspecialchars($envio['apellidos'] . ' ' . $envio['nombres']) ?></h5>
                        <?php endif; ?>
                        
                        <form action="calificar.php?id=<?= $envio_id ?>" method="POST">
                            <div class="mb-3">
                                <label class="fw-bold text-dark mb-2">Calificación (0-20)</label>
                                <input type="number" name="calificacion" class="form-control fw-bold fs-3 text-center text-primary" min="0" max="20" step="1" required value="<?= $envio['calificacion'] !== null ? htmlspecialchars($envio['calificacion']) : '' ?>">
                            </div>
                            <div class="mb-4">
                                <label class="fw-bold text-dark mb-2">Retroalimentación / Comentarios</label>
                                <textarea name="retroalimentacion" class="form-control shadow-sm" rows="5"><?= htmlspecialchars($envio['retroalimentacion'] ?? '') ?></textarea>
                            </div>
                            <button type="submit" name="calificar" class="btn btn-success btn-lg w-100 fw-bold shadow-sm"><i class="fas fa-save me-2"></i> Guardar Calificación</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($anexo && $anexo['tipo_archivo'] === 'pdf' && file_exists($anexo['ruta_archivo'])): ?>
    <div class="modal fade" id="modalPDF" tabindex="-1">
        <div class="modal-dialog modal-xl-custom modal-dialog-centered" style="height: 95vh;">
            <div class="modal-content h-100 border-0 shadow-lg">
                <div class="modal-header bg-danger text-white border-0"><h5 class="modal-title fw-bold">Anexo: <?= htmlspecialchars($anexo['nombre_archivo']) ?></h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-0 bg-dark"><iframe src="<?= htmlspecialchars($anexo['ruta_archivo']) ?>" width="100%" height="100%" style="border: none;"></iframe></div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <?php if ($es_grupal): ?>
    <div class="modal fade" id="modalEditarEquipo" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <form action="calificar.php?id=<?= $envio_id ?>" method="POST">
                    <div class="modal-header bg-primary text-white border-0">
                        <h5 class="modal-title fw-bold"><i class="fas fa-users-cog me-2"></i> Editar Equipo</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <p class="text-muted small mb-3">Selecciona los integrantes que formarán parte de este equipo. <strong>El líder no puede ser removido.</strong></p>
                        <div class="mb-3">
                            <label class="fw-bold mb-2">Integrantes del Equipo:</label>
                            <select class="form-select select2-equipo w-100" name="integrantes[]" multiple="multiple">
                                <?php 
                                $ids_actuales = array_column($integrantes, 'id');
                                foreach($companeros as $c): 
                                    if ($c['id'] == $envio['lider_id']) continue;
                                    $selected = in_array($c['id'], $ids_actuales) ? 'selected' : '';
                                ?>
                                    <option value="<?= $c['id'] ?>" <?= $selected ?>><?= htmlspecialchars($c['apellidos'].', '.$c['nombres']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer border-0 bg-light">
                        <button type="button" class="btn btn-secondary fw-bold shadow-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" name="editar_equipo" class="btn btn-primary fw-bold shadow-sm"><i class="fas fa-save me-2"></i> Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
    $(document).ready(function() {
        if ($('.select2-equipo').length) {
            $('.select2-equipo').select2({ placeholder: "Busca estudiantes por apellidos...", dropdownParent: $('#modalEditarEquipo') });
        }
    });
    </script>
    <?php endif; ?>
</body>
</html>