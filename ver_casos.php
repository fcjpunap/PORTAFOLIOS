<?php
// ver_casos.php - Vista del estudiante para listar y enviar las tareas de un curso específico
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'estudiante') {
    header('Location: login.php');
    exit;
}

require_once 'config/conexion.php';
$estudiante_id = $_SESSION['usuario_id'];
$curso_id = $_GET['curso_id'] ?? null;
$error = '';

if (!$curso_id) {
    die("ID de curso no especificado.");
}

// 1. VALIDACIÓN DE SEGURIDAD: Verificar que el estudiante esté realmente matriculado en este curso
$stmt_check = $pdo->prepare("SELECT 1 FROM matriculas WHERE curso_id = ? AND estudiante_id = ?");
$stmt_check->execute([$curso_id, $estudiante_id]);
if (!$stmt_check->fetch()) {
    die("Acceso denegado: No estás matriculado en este curso.");
}

// 2. Obtener información del curso
$stmt_curso = $pdo->prepare("SELECT c.nombre_curso, u.nombres, u.apellidos FROM cursos c JOIN usuarios u ON c.docente_id = u.id WHERE c.id = ?");
$stmt_curso->execute([$curso_id]);
$curso_info = $stmt_curso->fetch(PDO::FETCH_ASSOC);

// 3. Obtener todas las actividades del curso y cruzar con los envíos del estudiante (como líder o integrante)
$sql_casos = "
    SELECT a.id as actividad_id, a.titulo_caso, a.descripcion, a.fecha_limite,
           e.id as envio_id, e.estado, e.calificacion, e.fecha_envio
    FROM actividades_fichas a
    LEFT JOIN envios_fichas e ON a.id = e.actividad_id 
         AND e.id IN (SELECT envio_id FROM envio_integrantes WHERE estudiante_id = ?)
    WHERE a.curso_id = ?
    ORDER BY a.fecha_limite ASC
";
$stmt_casos = $pdo->prepare($sql_casos);
$stmt_casos->execute([$estudiante_id, $curso_id]);
$casos = $stmt_casos->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Casos del Curso | Portafolio UNAP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> 
        body { background-color: #f4f7f6; } 
        .bg-unap { background-color: #0b2e59; } 
        .text-unap { color: #0b2e59; }
        .card-caso { transition: transform 0.2s, box-shadow 0.2s; }
        .card-caso:hover { transform: translateY(-3px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
    </style>
</head>
<body>

    <nav class="navbar navbar-dark bg-unap shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard_estudiante.php">
                <i class="fas fa-arrow-left me-2"></i> Volver a Mis Cursos
            </a>
            <span class="text-white d-none d-md-block"><i class="fas fa-user-graduate me-1"></i> <?= htmlspecialchars($_SESSION['nombre_completo']) ?></span>
        </div>
    </nav>

    <main class="container py-5">
        
        <div class="card shadow-sm border-0 mb-5 border-start border-4 border-primary bg-white">
            <div class="card-body p-4">
                <span class="badge bg-primary mb-2">Casos Prácticos</span>
                <h2 class="fw-bold text-dark mb-1"><?= htmlspecialchars($curso_info['nombre_curso']) ?></h2>
                <p class="text-muted mb-0"><i class="fas fa-chalkboard-teacher me-1"></i> Docente: <?= htmlspecialchars($curso_info['apellidos'] . ', ' . $curso_info['nombres']) ?></p>
            </div>
        </div>

        <h4 class="fw-bold text-secondary mb-4"><i class="fas fa-tasks text-primary me-2"></i> Actividades Asignadas</h4>

        <?php if (empty($casos)): ?>
            <div class="alert alert-info shadow-sm text-center py-5">
                <i class="fas fa-folder-open fa-3x mb-3 opacity-50"></i>
                <h5>El docente aún no ha publicado casos para este curso.</h5>
                <p class="mb-0 text-muted">Vuelve a revisar más adelante.</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach($casos as $caso): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card shadow-sm border-0 h-100 card-caso">
                            <div class="card-body d-flex flex-column">
                                <h5 class="fw-bold text-unap border-bottom pb-2 mb-3"><?= htmlspecialchars($caso['titulo_caso']) ?></h5>
                                <p class="text-muted small flex-grow-1"><?= htmlspecialchars(substr($caso['descripcion'], 0, 120)) ?>...</p>
                                
                                <div class="mb-3 small">
                                    <i class="far fa-calendar-alt text-danger me-1"></i> <strong>Cierre:</strong> 
                                    <?= $caso['fecha_limite'] ? date('d/m/Y h:i A', strtotime($caso['fecha_limite'])) : 'Sin fecha' ?>
                                </div>

                                <?php if (!$caso['envio_id']): ?>
                                    <div class="alert alert-warning py-2 px-3 small text-center fw-bold mb-3 border-warning">
                                        <i class="fas fa-exclamation-triangle me-1"></i> Pendiente de envío
                                    </div>
                                    <a href="subir_ficha.php?id=<?= $caso['actividad_id'] ?>" class="btn btn-primary w-100 fw-bold">
                                        <i class="fas fa-cloud-upload-alt me-1"></i> Resolver y Subir
                                    </a>
                                
                                <?php elseif ($caso['estado'] === 'Enviado'): ?>
                                    <div class="alert alert-info py-2 px-3 small text-center fw-bold mb-3 border-info">
                                        <i class="fas fa-clock me-1"></i> Enviado. Esperando nota
                                    </div>
                                    <a href="detalle_entrega.php?id=<?= $caso['envio_id'] ?>" class="btn btn-outline-info w-100 fw-bold">
                                        <i class="fas fa-eye me-1"></i> Ver mi entrega
                                    </a>
                                
                                <?php elseif ($caso['estado'] === 'Revisado'): ?>
                                    <div class="alert alert-success py-2 px-3 small text-center fw-bold mb-3 border-success d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-check-double me-1"></i> Calificado</span>
                                        <span class="fs-6 border border-success px-2 rounded bg-white">Nota: <?= $caso['calificacion'] ?></span>
                                    </div>
                                    <a href="detalle_entrega.php?id=<?= $caso['envio_id'] ?>" class="btn btn-success w-100 fw-bold">
                                        <i class="fas fa-star me-1"></i> Ver Corrección
                                    </a>
                                <?php endif; ?>

                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>