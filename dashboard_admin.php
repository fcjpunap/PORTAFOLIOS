<?php
// dashboard_admin.php - Panel Principal del Administrador
session_start();

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php');
    exit;
}

require_once 'config/conexion.php';
$admin_nombre = $_SESSION['nombre_completo'] ?? 'Administrador';

// Obtener estadísticas rápidas (KPIs)
try {
    $total_docentes = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'docente'")->fetchColumn();
    $total_estudiantes = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'estudiante'")->fetchColumn();
    $total_cursos = $pdo->query("SELECT COUNT(*) FROM cursos")->fetchColumn();
    $total_casos = $pdo->query("SELECT COUNT(*) FROM actividades_fichas")->fetchColumn();
} catch (PDOException $e) {
    $error_db = "Error cargando datos: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Administrador | Portafolio UNAP</title>
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
            <a class="navbar-brand fw-bold" href="dashboard_admin.php">
                <i class="fas fa-shield-alt text-warning me-2"></i> Administrador FCJP
            </a>
            <div class="d-flex text-white align-items-center ms-auto">
                <span class="me-3 fw-bold d-none d-md-inline"><i class="fas fa-user-shield me-1"></i> <?= htmlspecialchars($admin_nombre) ?></span>
                <a href="cambiar_password.php" class="btn btn-outline-warning btn-sm rounded-pill me-2"><i class="fas fa-key"></i> <span class="d-none d-md-inline">Clave</span></a>
                <a href="logout.php" class="btn btn-outline-light btn-sm rounded-pill"><i class="fas fa-sign-out-alt"></i> Salir</a>
            </div>
        </div>
    </nav>

    <main class="container py-5">
        <h2 class="text-secondary fw-bold mb-4">Panel de Control General</h2>

        <?php if (isset($error_db)): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?= $error_db ?></div>
        <?php endif; ?>

        <div class="row mb-5">
            <div class="col-md-3 mb-3">
                <div class="card card-dash border-0 shadow-sm border-bottom border-4 border-primary h-100 p-3 bg-white">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-primary bg-opacity-10 text-primary fs-4"><i class="fas fa-chalkboard-teacher"></i></div>
                        <div class="ms-3"><h6 class="text-muted mb-0 small">Docentes</h6><h3 class="fw-bold mb-0 text-dark"><?= $total_docentes ?></h3></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card card-dash border-0 shadow-sm border-bottom border-4 border-success h-100 p-3 bg-white">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-success bg-opacity-10 text-success fs-4"><i class="fas fa-user-graduate"></i></div>
                        <div class="ms-3"><h6 class="text-muted mb-0 small">Estudiantes</h6><h3 class="fw-bold mb-0 text-dark"><?= $total_estudiantes ?></h3></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card card-dash border-0 shadow-sm border-bottom border-4 border-info h-100 p-3 bg-white">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-info bg-opacity-10 text-info fs-4"><i class="fas fa-book"></i></div>
                        <div class="ms-3"><h6 class="text-muted mb-0 small">Cursos Activos</h6><h3 class="fw-bold mb-0 text-dark"><?= $total_cursos ?></h3></div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card card-dash border-0 shadow-sm border-bottom border-4 border-warning h-100 p-3 bg-white">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-warning bg-opacity-10 text-warning fs-4"><i class="fas fa-folder-open"></i></div>
                        <div class="ms-3"><h6 class="text-muted mb-0 small">Casos Globales</h6><h3 class="fw-bold mb-0 text-dark"><?= $total_casos ?></h3></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <h5 class="fw-bold text-secondary mb-3"><i class="fas fa-cogs text-warning me-2"></i> Herramientas de Administración</h5>
                
                <div class="list-group shadow-sm border-0 mb-4">
                    <a href="reportes_docente.php" class="list-group-item list-group-item-action p-4 d-flex align-items-center bg-primary text-white" style="border-radius: 10px 10px 0 0;">
                        <div class="icon-box bg-white text-primary me-3" style="width: 50px; height: 50px;"><i class="fas fa-chart-pie fa-lg"></i></div>
                        <div><h5 class="fw-bold mb-1">Diagnosticador BI y Reportes</h5><span class="text-white-50">Auditoría global de rendimiento, riesgo académico y descarga de expedientes de toda la facultad.</span></div>
                    </a>
                    
                    <a href="gestionar_usuarios.php" class="list-group-item list-group-item-action p-4 d-flex align-items-center">
                        <div class="icon-box bg-info text-white me-3" style="width: 50px; height: 50px;"><i class="fas fa-users-cog fa-lg"></i></div>
                        <div><h5 class="fw-bold mb-1">Gestionar Usuarios</h5><span class="text-muted small">Registrar, editar y eliminar estudiantes, docentes y administradores.</span></div>
                    </a>
                    
                    <a href="gestionar_cursos.php" class="list-group-item list-group-item-action p-4 d-flex align-items-center">
                        <div class="icon-box bg-success text-white me-3" style="width: 50px; height: 50px;"><i class="fas fa-book-reader fa-lg"></i></div>
                        <div><h5 class="fw-bold mb-1">Gestionar Cursos</h5><span class="text-muted small">Crear asignaturas y asignar docentes responsables.</span></div>
                    </a>

                    <a href="gestionar_matriculas.php" class="list-group-item list-group-item-action p-4 d-flex align-items-center">
                        <div class="icon-box bg-warning text-dark me-3" style="width: 50px; height: 50px;"><i class="fas fa-user-plus fa-lg"></i></div>
                        <div><h5 class="fw-bold mb-1">Matrículas Globales</h5><span class="text-muted small">Inscribir estudiantes en cualquier curso de la facultad o realizar importaciones masivas.</span></div>
                    </a>

                    <a href="ver_todos_trabajos.php" class="list-group-item list-group-item-action p-4 d-flex align-items-center" style="border-radius: 0 0 10px 10px;">
                        <div class="icon-box bg-dark text-white me-3" style="width: 50px; height: 50px;"><i class="fas fa-inbox fa-lg"></i></div>
                        <div><h5 class="fw-bold mb-1">Bandeja de Trabajos Global</h5><span class="text-muted small">Monitoreo y auditoría de todos los trabajos enviados en la plataforma.</span></div>
                    </a>
                </div>
            </div>
        </div>
    </main>
</body>
</html>