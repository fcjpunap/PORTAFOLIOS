<?php
// dashboard_docente.php - Panel Principal del Docente
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'docente') {
    header('Location: login.php');
    exit;
}

require_once 'config/conexion.php';
$docente_id = $_SESSION['usuario_id'];
$docente_nombre = $_SESSION['nombre_completo'] ?? 'Docente';

// Obtener estadísticas rápidas (KPIs)
try {
    // 1. Total de cursos asignados
    $stmt_cursos = $pdo->prepare("SELECT COUNT(*) FROM cursos WHERE docente_id = ?");
    $stmt_cursos->execute([$docente_id]);
    $total_cursos = $stmt_cursos->fetchColumn();

    // 2. Total de alumnos matriculados en sus cursos
    $stmt_alumnos = $pdo->prepare("SELECT COUNT(DISTINCT m.estudiante_id) FROM matriculas m JOIN cursos c ON m.curso_id = c.id WHERE c.docente_id = ?");
    $stmt_alumnos->execute([$docente_id]);
    $total_alumnos = $stmt_alumnos->fetchColumn();

    // 3. Casos/Actividades publicadas
    $stmt_act = $pdo->prepare("SELECT COUNT(*) FROM actividades_fichas a JOIN cursos c ON a.curso_id = c.id WHERE c.docente_id = ?");
    $stmt_act->execute([$docente_id]);
    $total_actividades = $stmt_act->fetchColumn();

    // 4. Trabajos pendientes de calificar
    $stmt_pendientes = $pdo->prepare("SELECT COUNT(*) FROM envios_fichas e JOIN actividades_fichas a ON e.actividad_id = a.id JOIN cursos c ON a.curso_id = c.id WHERE c.docente_id = ? AND (e.estado != 'Revisado' OR e.calificacion IS NULL)");
    $stmt_pendientes->execute([$docente_id]);
    $total_pendientes = $stmt_pendientes->fetchColumn();

    // 5. Obtener los últimos 5 envíos recientes para la tabla
    $stmt_recientes = $pdo->prepare("
        SELECT e.id as envio_id, e.fecha_envio, e.estado, a.titulo_caso, u.nombres, u.apellidos 
        FROM envios_fichas e 
        JOIN actividades_fichas a ON e.actividad_id = a.id 
        JOIN cursos c ON a.curso_id = c.id 
        JOIN usuarios u ON e.lider_id = u.id 
        WHERE c.docente_id = ? 
        ORDER BY e.fecha_envio DESC LIMIT 5
    ");
    $stmt_recientes->execute([$docente_id]);
    $envios_recientes = $stmt_recientes->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_db = "Error cargando datos: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Docente | Portafolio UNAP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; display: flex; flex-direction: column; min-height: 100vh; }
        main { flex: 1; }
        .bg-unap { background-color: #0b2e59; }
        .card-dash { transition: transform 0.2s; border-radius: 12px; }
        .card-dash:hover { transform: translateY(-5px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
        .icon-box { width: 48px; height: 48px; display: flex; align-items: center; justify-content: center; border-radius: 10px; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-unap shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard_docente.php">
                <i class="fas fa-chalkboard-teacher text-warning me-2"></i> Docente FCJP
            </a>
            <div class="d-flex text-white align-items-center ms-auto">
                <span class="me-3 fw-bold d-none d-md-inline"><i class="fas fa-user-circle me-1"></i> <?= htmlspecialchars($docente_nombre) ?></span>
                <a href="cambiar_password.php" class="btn btn-outline-warning btn-sm rounded-pill me-2"><i class="fas fa-key"></i> <span class="d-none d-md-inline">Clave</span></a>
                <a href="logout.php" class="btn btn-outline-light btn-sm rounded-pill"><i class="fas fa-sign-out-alt"></i> Salir</a>
            </div>
        </div>
    </nav>

    <main class="container py-5">
        <h2 class="text-secondary fw-bold mb-4">Bienvenido, <?= htmlspecialchars($docente_nombre) ?></h2>

        <?php if (isset($error_db)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= $error_db ?></div>
        <?php endif; ?>

        <div class="row mb-5">
            <div class="col-md-3 mb-3">
                <div class="card card-dash border-0 shadow-sm border-bottom border-4 border-primary h-100 p-3 bg-white">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-primary bg-opacity-10 text-primary fs-4"><i class="fas fa-book"></i></div>
                        <div class="ms-3"><h6 class="text-muted mb-0 small">Mis Cursos</h6><h3 class="fw-bold mb-0 text-dark"><?= $total_cursos ?></h3></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card card-dash border-0 shadow-sm border-bottom border-4 border-success h-100 p-3 bg-white">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-success bg-opacity-10 text-success fs-4"><i class="fas fa-users"></i></div>
                        <div class="ms-3"><h6 class="text-muted mb-0 small">Estudiantes</h6><h3 class="fw-bold mb-0 text-dark"><?= $total_alumnos ?></h3></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card card-dash border-0 shadow-sm border-bottom border-4 border-info h-100 p-3 bg-white">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-info bg-opacity-10 text-info fs-4"><i class="fas fa-folder-open"></i></div>
                        <div class="ms-3"><h6 class="text-muted mb-0 small">Casos Creados</h6><h3 class="fw-bold mb-0 text-dark"><?= $total_actividades ?></h3></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card card-dash border-0 shadow-sm border-bottom border-4 border-warning h-100 p-3 bg-white">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-warning bg-opacity-10 text-warning fs-4"><i class="fas fa-exclamation-circle"></i></div>
                        <div class="ms-3"><h6 class="text-muted mb-0 small">Por Calificar</h6><h3 class="fw-bold mb-0 text-danger"><?= $total_pendientes ?></h3></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <h5 class="fw-bold text-secondary mb-3"><i class="fas fa-bolt text-warning me-2"></i> Accesos Rápidos</h5>
                <div class="list-group shadow-sm border-0 mb-4">
                    <a href="reportes_docente.php" class="list-group-item list-group-item-action p-3 d-flex align-items-center bg-primary text-white" style="border-radius: 10px 10px 0 0;">
                        <div class="icon-box bg-white text-primary me-3" style="width: 40px; height: 40px;"><i class="fas fa-brain"></i></div>
                        <div><h6 class="fw-bold mb-0">Diagnosticador BI</h6><small class="text-white-50">Reportes, gráficos y riesgo académico.</small></div>
                    </a>
                    <a href="actividades.php" class="list-group-item list-group-item-action p-3 d-flex align-items-center">
                        <div class="icon-box bg-primary text-white me-3" style="width: 40px; height: 40px;"><i class="fas fa-folder-plus"></i></div>
                        <div><h6 class="fw-bold mb-0">Gestionar Casos</h6><small class="text-muted">Crear y editar actividades.</small></div>
                    </a>
                    <a href="gestionar_matriculas.php" class="list-group-item list-group-item-action p-3 d-flex align-items-center">
                        <div class="icon-box bg-success text-white me-3" style="width: 40px; height: 40px;"><i class="fas fa-user-plus"></i></div>
                        <div><h6 class="fw-bold mb-0">Matricular Alumnos</h6><small class="text-muted">Añadir alumnos a tus cursos.</small></div>
                    </a>
                    <a href="exportar_notas.php" class="list-group-item list-group-item-action p-3 d-flex align-items-center" style="border-radius: 0 0 10px 10px;">
                        <div class="icon-box bg-dark text-white me-3" style="width: 40px; height: 40px;"><i class="fas fa-file-excel"></i></div>
                        <div><h6 class="fw-bold mb-0">Registro de Notas</h6><small class="text-muted">Descargar actas en Excel.</small></div>
                    </a>
                </div>
            </div>

            <div class="col-md-8">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold text-secondary mb-0"><i class="fas fa-clock text-info me-2"></i> Últimos Trabajos Recibidos</h5>
                    
                    <a href="ver_todos_trabajos.php" class="btn btn-outline-primary btn-sm fw-bold">Ver todos los trabajos <i class="fas fa-arrow-right ms-1"></i></a>
                </div>
                
                <div class="card shadow-sm border-0">
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Estudiante / Líder</th>
                                    <th>Caso / Actividad</th>
                                    <th>Fecha</th>
                                    <th class="text-end pe-4">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($envios_recientes)): ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted">Aún no has recibido nuevos trabajos.</td></tr>
                                <?php else: ?>
                                    <?php foreach($envios_recientes as $envio): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-dark"><?= htmlspecialchars($envio['apellidos'] . ', ' . $envio['nombres']) ?></td>
                                        <td class="small text-muted"><?= htmlspecialchars($envio['titulo_caso']) ?></td>
                                        <td class="small">
                                            <?= date('d/m/Y H:i', strtotime($envio['fecha_envio'])) ?><br>
                                            <?php if($envio['estado'] == 'Revisado'): ?>
                                                <span class="badge bg-success" style="font-size: 0.7rem;">Revisado</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark" style="font-size: 0.7rem;">Pendiente</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="calificar.php?id=<?= $envio['envio_id'] ?>" class="btn btn-sm btn-primary shadow-sm" title="Revisar trabajo">
                                                <i class="fas fa-gavel"></i> Calificar
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>