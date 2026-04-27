<?php
// dashboard_estudiante.php - Panel principal del estudiante
session_set_cookie_params(14400);
ini_set("session.gc_maxlifetime", 14400);
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'estudiante') {
    header('Location: login.php');
    exit;
}

require_once 'config/conexion.php';
$estudiante_id = $_SESSION['usuario_id'];
$nombre_estudiante = $_SESSION['nombre_completo'] ?? 'Estudiante';

$cursos_matriculados = [];
$error_db = '';

// Usamos try-catch para evitar la pantalla blanca si hay error SQL
try {
    // Se quitó 'c.codigo_curso' por si no existe en la tabla cursos
    $sql_cursos = "SELECT c.id, c.nombre_curso, u.nombres as docente_nom, u.apellidos as docente_ape 
                   FROM cursos c 
                   JOIN matriculas m ON c.id = m.curso_id 
                   JOIN usuarios u ON c.docente_id = u.id 
                   WHERE m.estudiante_id = ?";
    $stmt_cursos = $pdo->prepare($sql_cursos);
    $stmt_cursos->execute([$estudiante_id]);
    $cursos_matriculados = $stmt_cursos->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Si hay un error, lo guardamos para mostrarlo en pantalla en lugar de colapsar
    $error_db = "Error en la base de datos: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel Estudiante | UNAP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> 
        body { background-color: #f8f9fa; } 
        .bg-unap { background-color: #0b2e59; } 
        .hover-shadow { transition: transform 0.3s ease, box-shadow 0.3s ease; }
        .hover-shadow:hover { transform: translateY(-3px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-unap shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard_estudiante.php"><i class="fas fa-user-graduate me-2"></i> Mi Portafolio FCJP</a>
            
            <div class="d-flex text-white align-items-center ms-auto">
                <span class="me-3 fw-bold d-none d-md-inline"><i class="fas fa-user-circle me-1"></i> <?= htmlspecialchars($nombre_estudiante) ?></span>
                
                <a href="cambiar_password.php" class="btn btn-outline-warning btn-sm rounded-pill me-2" title="Cambiar mi contraseña">
                    <i class="fas fa-key"></i> <span class="d-none d-md-inline">Contraseña</span>
                </a>
                
                <a href="logout.php" class="btn btn-outline-light btn-sm rounded-pill">
                    <i class="fas fa-sign-out-alt"></i> <span class="d-none d-md-inline">Salir</span>
                </a>
            </div>
        </div>
    </nav>

    <main class="container py-5">
        <div class="d-flex justify-content-between align-items-end mb-4 border-bottom pb-2">
            <h3 class="fw-bold text-secondary mb-0"><i class="fas fa-book-reader text-primary me-2"></i> Mis Cursos</h3>
        </div>

        <?php if ($error_db): ?>
            <div class="alert alert-danger shadow-sm">
                <i class="fas fa-exclamation-triangle me-2"></i> <strong>¡Ups! Algo salió mal:</strong><br>
                <?= htmlspecialchars($error_db) ?>
            </div>
        <?php elseif (empty($cursos_matriculados)): ?>
            <div class="alert alert-info shadow-sm text-center py-5 border-0">
                <i class="fas fa-info-circle fa-3x mb-3 opacity-50 text-primary"></i>
                <h5 class="fw-bold">Aún no estás matriculado en ningún curso.</h5>
                <p class="mb-0 text-muted">Tu docente debe agregarte a su lista mediante el sistema.</p>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <?php foreach($cursos_matriculados as $curso): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card shadow-sm border-0 h-100 border-start border-4 border-primary hover-shadow">
                            <div class="card-body">
                                <span class="badge bg-primary mb-2">Asignatura</span>
                                <h5 class="card-title fw-bold text-dark mb-3"><?= htmlspecialchars($curso['nombre_curso']) ?></h5>
                                <p class="card-text text-muted small mb-0">
                                    <i class="fas fa-chalkboard-teacher me-1"></i> Docente: <span class="fw-bold"><?= htmlspecialchars($curso['docente_ape'] . ', ' . $curso['docente_nom']) ?></span>
                                </p>
                            </div>
                            <div class="card-footer bg-white border-0 text-center pb-4 pt-0">
                                <a href="ver_casos.php?curso_id=<?= $curso['id'] ?>" class="btn btn-outline-primary w-100 fw-bold rounded-pill">
                                    <i class="fas fa-arrow-right me-1"></i> Ver Casos y Trabajos
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>