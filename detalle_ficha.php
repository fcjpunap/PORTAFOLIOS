<?php
// detalle_ficha.php - Vista del docente para leer la resolución completa
session_start();

// Solo Docentes o Administradores pueden ver esta página de forma privada
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['docente', 'admin'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/conexion.php';

$envio_id = $_GET['id'] ?? null;
if (!$envio_id) {
    die("ID de envío no especificado.");
}

// Obtener todos los detalles del envío, la actividad y el curso
$sql = "
    SELECT e.id as envio_id, e.factum, e.tipicidad, e.dogmatica, e.jurisprudencia, e.fallo, 
           e.fecha_envio, e.estado, e.calificacion, e.respuestas_json,
           a.titulo_caso, a.descripcion, c.nombre_curso,
           (SELECT GROUP_CONCAT(CONCAT(u.nombres, ' ', u.apellidos) SEPARATOR '<br>') 
            FROM envio_integrantes ei 
            JOIN usuarios u ON ei.estudiante_id = u.id 
            WHERE ei.envio_id = e.id) as estudiantes_autores
    FROM envios_fichas e
    JOIN actividades_fichas a ON e.actividad_id = a.id
    JOIN cursos c ON a.curso_id = c.id
    WHERE e.id = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$envio_id]);
$envio = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$envio) {
    die("El envío solicitado no existe.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluar Ficha | Portafolios UNAP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; }
        .bg-unap { background-color: #0b2e59; }
        .texto-academico { font-family: 'Times New Roman', Times, serif; font-size: 1.15rem; text-align: justify; line-height: 1.6; }
        .apartado-titulo { color: #0b2e59; border-bottom: 2px solid #ffc107; padding-bottom: 5px; margin-bottom: 15px; font-weight: bold; }
    </style>
</head>
<body>

    <nav class="navbar navbar-dark bg-unap shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="dashboard_docente.php"><i class="fas fa-arrow-left"></i> Volver a mi Mesa de Trabajo</a>
        </div>
    </nav>

    <main class="container py-4">
        <div class="row">
            
            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header bg-white py-3">
                        <span class="badge bg-primary mb-2"><?= htmlspecialchars($envio['nombre_curso']) ?></span>
                        <h5 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($envio['titulo_caso']) ?></h5>
                    </div>
                    <div class="card-body bg-light small">
                        <strong><i class="fas fa-align-left text-muted"></i> Hechos del Caso (Profesor):</strong>
                        <p class="text-muted mt-1 mb-0" style="white-space: pre-wrap;"><?= htmlspecialchars($envio['descripcion']) ?></p>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-3 border-start border-4 border-info">
                    <div class="card-body">
                        <h6 class="fw-bold text-secondary"><i class="fas fa-users text-info"></i> Presentado por:</h6>
                        <div class="ms-4 mb-3"><?= $envio['estudiantes_autores'] ?></div>
                        
                        <h6 class="fw-bold text-secondary"><i class="fas fa-clock text-info"></i> Fecha de Entrega:</h6>
                        <p class="ms-4 mb-0"><?= date('d/m/Y h:i A', strtotime($envio['fecha_envio'])) ?></p>
                    </div>
                </div>

                <div class="card shadow-sm border-0 bg-white text-center p-3">
                    <?php if ($envio['estado'] === 'Revisado'): ?>
                        <h6 class="text-uppercase text-muted fw-bold">Calificación Actual</h6>
                        <h1 class="display-4 text-success fw-bold mb-0"><?= htmlspecialchars($envio['calificacion']) ?></h1>
                        <p class="text-success fw-bold"><i class="fas fa-check-double"></i> Revisado</p>
                        <button class="btn btn-outline-warning w-100" data-bs-toggle="modal" data-bs-target="#modalCalificar"><i class="fas fa-edit"></i> Modificar Nota</button>
                    <?php else: ?>
                        <h6 class="text-uppercase text-muted fw-bold">Estado</h6>
                        <p class="text-danger fw-bold mb-3"><i class="fas fa-exclamation-circle"></i> Pendiente de Revisión</p>
                        <button class="btn btn-success w-100 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCalificar"><i class="fas fa-star"></i> Calificar Trabajo Ahora</button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h4 class="mb-0 text-secondary"><i class="fas fa-file-signature text-primary"></i> Análisis y Resolución del Estudiante</h4>
                    </div>
                    <div class="card-body p-4 bg-white">
                        
                        <?php 
                        // --- MOTOR DE RENDERIZADO DINÁMICO ---
                        
                        // 1. Si existe JSON (Es un caso nuevo multidisciplinario)
                        if (!empty($envio['respuestas_json'])): 
                            $respuestas = json_decode($envio['respuestas_json'], true);
                            $contador = 1;
                            foreach ($respuestas as $titulo => $contenido):
                        ?>
                            <div class="mb-4">
                                <h5 class="apartado-titulo"><?= $contador ?>. <?= htmlspecialchars($titulo) ?></h5>
                                <p class="texto-academico"><?= nl2br(htmlspecialchars($contenido)) ?></p>
                            </div>
                        <?php 
                            $contador++;
                            endforeach; 

                        // 2. Si NO existe JSON (Es un caso antiguo del sistema clásico de Penal)
                        else: 
                        ?>
                            <div class="mb-4">
                                <h5 class="apartado-titulo">1. Factum (Hechos Relevantes)</h5>
                                <p class="texto-academico"><?= nl2br(htmlspecialchars($envio['factum'])) ?></p>
                            </div>
                            <div class="mb-4">
                                <h5 class="apartado-titulo">2. Juicio de Tipicidad</h5>
                                <p class="texto-academico"><?= nl2br(htmlspecialchars($envio['tipicidad'])) ?></p>
                            </div>
                            <div class="mb-4">
                                <h5 class="apartado-titulo">3. Análisis Dogmático</h5>
                                <p class="texto-academico"><?= nl2br(htmlspecialchars($envio['dogmatica'])) ?></p>
                            </div>
                            <?php if (!empty($envio['jurisprudencia'])): ?>
                            <div class="mb-4">
                                <h5 class="apartado-titulo">4. Jurisprudencia Aplicable</h5>
                                <p class="texto-academico"><?= nl2br(htmlspecialchars($envio['jurisprudencia'])) ?></p>
                            </div>
                            <?php endif; ?>
                            <div class="mb-4">
                                <h5 class="apartado-titulo">5. Conclusión / Fallo</h5>
                                <p class="texto-academico fw-bold"><?= nl2br(htmlspecialchars($envio['fallo'])) ?></p>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </main>

    <div class="modal fade" id="modalCalificar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold"><i class="fas fa-gavel"></i> Emitir Calificación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="procesar_calificacion.php" method="POST">
                    <div class="modal-body bg-light">
                        <input type="hidden" name="envio_id" value="<?= $envio_id ?>">
                        <input type="hidden" name="origen" value="detalle_ficha"> 
                        
                        <div class="mb-3 text-center">
                            <label class="form-label fw-bold text-secondary">Nota Vigesimal (0 - 20)</label>
                            <input type="number" class="form-control form-control-lg text-center mx-auto fw-bold text-primary" name="nota" min="0" max="20" step="0.5" style="max-width: 150px; font-size: 2.5rem;" value="<?= htmlspecialchars($envio['calificacion'] ?? '') ?>" required>
                        </div>
                        <div class="mb-1">
                            <label class="form-label fw-bold text-secondary">Retroalimentación (Opcional)</label>
                            <textarea class="form-control" name="feedback" rows="3" placeholder="Comentarios para el alumno..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-top-0 bg-light">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success fw-bold"><i class="fas fa-save"></i> Guardar Calificación</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>