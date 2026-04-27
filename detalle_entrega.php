<?php
// detalle_entrega.php - Vista del estudiante para ver su trabajo enviado y su nota
session_set_cookie_params(14400);
ini_set("session.gc_maxlifetime", 14400);
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'estudiante') {
    header('Location: login.php');
    exit;
}

require_once 'config/conexion.php';
$estudiante_id = $_SESSION['usuario_id'];
$envio_id = $_GET['id'] ?? null;

if (!$envio_id) { die("ID de envío no especificado."); }

// Verificar de forma segura que este estudiante pertenece a este envío
$stmt_check = $pdo->prepare("SELECT 1 FROM envio_integrantes WHERE envio_id = ? AND estudiante_id = ?");
$stmt_check->execute([$envio_id, $estudiante_id]);
if (!$stmt_check->fetch()) {
    die("Acceso denegado: No eres integrante de este grupo de trabajo.");
}

// Obtener datos del envío (Traemos tanto las columnas antiguas como la nueva JSON)
$sql = "
    SELECT e.factum, e.tipicidad, e.dogmatica, e.jurisprudencia, e.fallo, e.fecha_envio, e.respuestas_json, e.estado, e.calificacion, e.retroalimentacion,
           a.titulo_caso, c.nombre_curso,
           (SELECT GROUP_CONCAT(CONCAT(u.nombres, ' ', u.apellidos) SEPARATOR ', ')
            FROM envio_integrantes ei JOIN usuarios u ON ei.estudiante_id = u.id WHERE ei.envio_id = e.id) as estudiantes_autores
    FROM envios_fichas e
    JOIN actividades_fichas a ON e.actividad_id = a.id
    JOIN cursos c ON a.curso_id = c.id
    WHERE e.id = ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$envio_id]);
$caso = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$caso) { die("El envío no existe."); }

$stmt_anexos = $pdo->prepare("SELECT nombre_archivo, ruta_archivo, tipo_archivo FROM anexos WHERE envio_id = ?");
$stmt_anexos->execute([$envio_id]);
$anexos = $stmt_anexos->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Entrega | Portafolio UNAP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; }
        .bg-unap { background-color: #0b2e59; }
        .text-unap { color: #0b2e59; }
        .content-box { background: white; border-radius: 8px; padding: 30px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .texto-academico { text-align: justify; font-family: 'Times New Roman', Times, serif; font-size: 1.15rem; line-height: 1.6; }
    </style>
</head>
<body>

    <nav class="navbar navbar-dark bg-unap shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard_estudiante.php"><i class="fas fa-arrow-left me-2"></i> Volver a mi panel</a>
        </div>
    </nav>

    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                
                <div class="card shadow-sm border-0 mb-4 bg-white">
                    <div class="card-body p-4 text-center">
                        <span class="badge bg-primary px-3 py-2 rounded-pill mb-2"><?= htmlspecialchars($caso['nombre_curso']) ?></span>
                        <h2 class="fw-bold text-unap mb-3"><?= htmlspecialchars($caso['titulo_caso']) ?></h2>
                        <p class="text-muted mb-2"><i class="fas fa-users"></i> <strong>Mi Grupo:</strong> <?= htmlspecialchars($caso['estudiantes_autores']) ?></p>
                        
                        <hr class="w-50 mx-auto my-3">
                        
                        <?php if($caso['estado'] === 'Revisado'): ?>
                            <div class="d-inline-block px-4 py-2 bg-success bg-opacity-10 border border-success rounded-3 mb-3">
                                <h6 class="text-success fw-bold mb-1"><i class="fas fa-check-double"></i> Trabajo Calificado</h6>
                                <h3 class="fw-bold text-success mb-0">Nota: <?= htmlspecialchars($caso['calificacion']) ?> / 20</h3>
                            </div>
                            <?php if(!empty($caso['retroalimentacion'])): ?>
                            <div class="alert alert-info shadow-sm mt-3 text-start">
                                <h6 class="fw-bold text-info-emphasis mb-2"><i class="fas fa-comment-dots text-info me-2"></i>Retroalimentación del Docente:</h6>
                                <div class="px-2 py-1" style="font-size: 1.05rem; line-height: 1.5; color: #333; text-align: justify; white-space: pre-line;"><?= htmlspecialchars($caso['retroalimentacion']) ?></div>
                            </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="d-inline-block px-4 py-2 bg-warning bg-opacity-10 border border-warning rounded-3 mb-3">
                                <h6 class="text-warning text-dark fw-bold mb-0"><i class="fas fa-hourglass-half"></i> Pendiente de Calificación</h6>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($anexos)): ?>
                    <?php foreach ($anexos as $index => $anexo): ?>
                    <div class="card shadow-sm border-0 mb-4 bg-white border-top border-4 border-info">
                        <div class="card-body p-4 d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold text-secondary mb-0"><i class="fas fa-paperclip me-2 text-info"></i> Archivo Adjunto: <?= htmlspecialchars($anexo['nombre_archivo']) ?></h5>
                            <?php if ($anexo['tipo_archivo'] === 'url'): ?>
                                <a href="<?= htmlspecialchars($anexo['ruta_archivo']) ?>" target="_blank" class="btn btn-info text-white fw-bold shadow-sm">
                                    <i class="fas fa-external-link-alt me-2"></i> Visitar Web Externa
                                </a>
                            <?php elseif ($anexo['tipo_archivo'] === 'html'): ?>
                                <a href="<?= htmlspecialchars($anexo['ruta_archivo']) ?>" target="_blank" class="btn btn-dark fw-bold shadow-sm">
                                    <i class="fas fa-globe me-2"></i> Ver Expediente Web (ZIP)
                                </a>
                            <?php else: ?>
                                <button class="btn btn-danger fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalPDF_<?= $index ?>">
                                    <i class="fas fa-file-pdf me-2"></i> Ver Anexo PDF
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <div class="respuestas-container">
                    <?php 
                    // 1. SI ES UN TRABAJO NUEVO (Formato Dinámico JSON)
                    if (!empty($caso['respuestas_json'])): 
                        $respuestas = json_decode($caso['respuestas_json'], true);
                        $contador = 1;
                        $colores = ['primary', 'success', 'info', 'secondary', 'danger', 'warning', 'dark'];
                        
                        foreach ($respuestas as $titulo => $contenido):
                            $color_actual = $colores[($contador - 1) % count($colores)];
                    ?>
                        <div class="content-box border-start border-4 border-<?= $color_actual ?>">
                            <h4 class="text-<?= $color_actual ?> mb-3 border-bottom pb-2 fw-bold"><?= $contador ?>. <?= htmlspecialchars($titulo) ?></h4>
                            <p class="texto-academico text-dark"><?= nl2br(htmlspecialchars($contenido)) ?></p>
                        </div>
                    <?php 
                        $contador++;
                        endforeach; 

                    // 2. SI ES UN TRABAJO ANTIGUO (Formato Clásico de 5 partes)
                    else: 
                    ?>
                        <div class="content-box border-start border-4 border-primary">
                            <h4 class="text-primary mb-3 border-bottom pb-2 fw-bold">1. Factum (Hechos)</h4>
                            <p class="texto-academico text-dark"><?= nl2br(htmlspecialchars($caso['factum'])) ?></p>
                        </div>

                        <div class="content-box border-start border-4 border-success">
                            <h4 class="text-success mb-3 border-bottom pb-2 fw-bold">2. Juicio de Tipicidad</h4>
                            <p class="texto-academico text-dark"><?= nl2br(htmlspecialchars($caso['tipicidad'])) ?></p>
                        </div>

                        <div class="content-box border-start border-4 border-info">
                            <h4 class="text-info mb-3 border-bottom pb-2 fw-bold">3. Análisis Dogmático</h4>
                            <p class="texto-academico text-dark"><?= nl2br(htmlspecialchars($caso['dogmatica'])) ?></p>
                        </div>

                        <?php if(!empty($caso['jurisprudencia'])): ?>
                        <div class="content-box border-start border-4 border-secondary">
                            <h4 class="text-secondary mb-3 border-bottom pb-2 fw-bold">4. Jurisprudencia Aplicada</h4>
                            <p class="texto-academico text-dark"><?= nl2br(htmlspecialchars($caso['jurisprudencia'])) ?></p>
                        </div>
                        <?php endif; ?>

                        <div class="content-box border-start border-4 border-danger">
                            <h4 class="text-danger mb-3 border-bottom pb-2 fw-bold">5. Fallo / Conclusión</h4>
                            <p class="texto-academico fw-bold text-dark"><?= nl2br(htmlspecialchars($caso['fallo'])) ?></p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </main>

    <?php if (!empty($anexos)): ?>
        <?php foreach ($anexos as $index => $anexo): ?>
            <?php if ($anexo['tipo_archivo'] === 'pdf'): ?>
            <div class="modal fade" id="modalPDF_<?= $index ?>" tabindex="-1">
                <div class="modal-dialog modal-xl modal-dialog-centered" style="height: 95vh; max-width: 95%;">
                    <div class="modal-content h-100 border-0 shadow-lg">
                        <div class="modal-header bg-danger text-white border-0"><h5 class="modal-title fw-bold">Anexo: <?= htmlspecialchars($anexo['nombre_archivo']) ?></h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                        <div class="modal-body p-0 bg-dark"><iframe src="<?= htmlspecialchars($anexo['ruta_archivo']) ?>" width="100%" height="100%" style="border: none;"></iframe></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <?php endif; ?>
</body>
</html>