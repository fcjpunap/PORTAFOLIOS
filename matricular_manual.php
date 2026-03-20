<?php
// matricular_manual.php - Asignación manual de 1 a 1 con buscador inteligente
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php');
    exit;
}
require_once 'config/conexion.php';

$mensaje = '';

// Procesar Matrícula o Desmatrícula
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $curso_id = $_POST['curso_id'] ?? '';
    $estudiante_id = $_POST['estudiante_id'] ?? '';

    if (!empty($curso_id) && !empty($estudiante_id)) {
        try {
            if ($_POST['accion'] === 'matricular') {
                // Verificar si ya está matriculado
                $stmt_check = $pdo->prepare("SELECT 1 FROM matriculas WHERE curso_id = ? AND estudiante_id = ?");
                $stmt_check->execute([$curso_id, $estudiante_id]);
                
                if ($stmt_check->fetch()) {
                    $mensaje = "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle me-2'></i> El estudiante ya se encuentra en este curso.</div>";
                } else {
                    $stmt_ins = $pdo->prepare("INSERT INTO matriculas (curso_id, estudiante_id) VALUES (?, ?)");
                    $stmt_ins->execute([$curso_id, $estudiante_id]);
                    $mensaje = "<div class='alert alert-success'><i class='fas fa-check-circle me-2'></i> Alumno matriculado exitosamente.</div>";
                }
            } elseif ($_POST['accion'] === 'desmatricular') {
                $stmt_del = $pdo->prepare("DELETE FROM matriculas WHERE curso_id = ? AND estudiante_id = ?");
                $stmt_del->execute([$curso_id, $estudiante_id]);
                $mensaje = "<div class='alert alert-info'><i class='fas fa-info-circle me-2'></i> Alumno retirado del curso.</div>";
            }
        } catch (PDOException $e) {
            $mensaje = "<div class='alert alert-danger'>Error de base de datos: " . $e->getMessage() . "</div>";
        }
    }
}

// Mantener el curso seleccionado en la vista
$curso_seleccionado = $_GET['curso_id'] ?? ($_POST['curso_id'] ?? '');

// Obtener lista de cursos
$stmt_cursos = $pdo->query("SELECT c.id, c.nombre_curso, u.apellidos as doc_ape FROM cursos c JOIN usuarios u ON c.docente_id = u.id ORDER BY c.nombre_curso ASC");
$cursos = $stmt_cursos->fetchAll(PDO::FETCH_ASSOC);

// Si hay un curso seleccionado, obtener alumnos matriculados y lista de TODOS los estudiantes
$alumnos_matriculados = [];
$todos_estudiantes = [];

if ($curso_seleccionado) {
    // Alumnos YA en el curso
    $stmt_mat = $pdo->prepare("SELECT u.id, u.nombres, u.apellidos, u.codigo_estudiante FROM matriculas m JOIN usuarios u ON m.estudiante_id = u.id WHERE m.curso_id = ? ORDER BY u.apellidos ASC");
    $stmt_mat->execute([$curso_seleccionado]);
    $alumnos_matriculados = $stmt_mat->fetchAll(PDO::FETCH_ASSOC);

    // Todos los estudiantes para el buscador
    $stmt_est = $pdo->query("SELECT id, nombres, apellidos, codigo_estudiante FROM usuarios WHERE rol = 'estudiante' ORDER BY apellidos ASC");
    $todos_estudiantes = $stmt_est->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Matrícula Manual | Admin UNAP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <style> body { background-color: #f8f9fa; } .bg-unap { background-color: #0b2e59; } </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-unap shadow-sm">
        <div class="container"><a class="navbar-brand" href="dashboard_admin.php"><i class="fas fa-arrow-left me-2"></i> Volver al Panel</a></div>
    </nav>
    <main class="container py-4">
        <h3 class="mb-4 text-secondary"><i class="fas fa-user-plus text-primary me-2"></i> Matrícula Manual</h3>
        <?= $mensaje ?>

        <div class="row">
            <div class="col-md-5 mb-4">
                
                <div class="card shadow-sm border-0 border-top border-4 border-primary mb-4">
                    <div class="card-body">
                        <h5 class="card-title fw-bold mb-3">1. Seleccionar Asignatura</h5>
                        <form action="matricular_manual.php" method="GET">
                            <div class="input-group">
                                <select name="curso_id" class="form-select" onchange="this.form.submit()" required>
                                    <option value="">-- Elige un curso --</option>
                                    <?php foreach($cursos as $c): ?>
                                        <option value="<?= $c['id'] ?>" <?= ($curso_seleccionado == $c['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($c['nombre_curso']) ?> (Doc: <?= htmlspecialchars($c['doc_ape']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if ($curso_seleccionado): ?>
                <div class="card shadow-sm border-0 border-top border-4 border-success">
                    <div class="card-body">
                        <h5 class="card-title fw-bold mb-3">2. Buscar y Añadir Estudiante</h5>
                        <form action="matricular_manual.php" method="POST">
                            <input type="hidden" name="accion" value="matricular">
                            <input type="hidden" name="curso_id" value="<?= htmlspecialchars($curso_seleccionado) ?>">
                            
                            <div class="mb-4">
                                <label class="small fw-bold text-muted mb-2">Escribe apellido o código:</label>
                                <select name="estudiante_id" id="buscador_alumnos" class="form-select" required>
                                    <option value=""></option> <?php foreach($todos_estudiantes as $e): ?>
                                        <option value="<?= $e['id'] ?>">
                                            <?= htmlspecialchars($e['codigo_estudiante'] . ' - ' . $e['apellidos'] . ', ' . $e['nombres']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-success w-100 fw-bold"><i class="fas fa-plus me-2"></i> Matricular al Curso</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

            </div>

            <div class="col-md-7">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold text-secondary">Estudiantes en el Curso</h5>
                        <?php if ($curso_seleccionado): ?>
                            <span class="badge bg-primary rounded-pill"><?= count($alumnos_matriculados) ?> matriculados</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <?php if (!$curso_seleccionado): ?>
                            <div class="p-5 text-center text-muted">
                                <i class="fas fa-hand-point-left fa-3x mb-3 opacity-50"></i>
                                <h5>Selecciona un curso a la izquierda</h5>
                                <p>Para ver la lista de alumnos o matricular nuevos.</p>
                            </div>
                        <?php else: ?>
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Código</th>
                                        <th>Apellidos y Nombres</th>
                                        <th class="text-center">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($alumnos_matriculados)): ?>
                                        <tr><td colspan="3" class="text-center py-4 text-muted">Este curso aún no tiene alumnos matriculados.</td></tr>
                                    <?php else: ?>
                                        <?php foreach($alumnos_matriculados as $am): ?>
                                        <tr>
                                            <td class="ps-3 fw-bold text-secondary"><?= htmlspecialchars($am['codigo_estudiante'] ?: 'S/C') ?></td>
                                            <td class="fw-bold"><?= htmlspecialchars($am['apellidos'] . ', ' . $am['nombres']) ?></td>
                                            <td class="text-center">
                                                <form action="matricular_manual.php" method="POST" onsubmit="return confirm('¿Retirar a este alumno del curso? Perderá acceso a las fichas.');">
                                                    <input type="hidden" name="accion" value="desmatricular">
                                                    <input type="hidden" name="curso_id" value="<?= htmlspecialchars($curso_seleccionado) ?>">
                                                    <input type="hidden" name="estudiante_id" value="<?= $am['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Desmatricular">Retirar <i class="fas fa-times ms-1"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Inicializar el buscador inteligente con tema de Bootstrap 5
            $('#buscador_alumnos').select2({
                theme: 'bootstrap-5',
                placeholder: "Buscar por apellidos o código...",
                allowClear: true,
                language: {
                    noResults: function() {
                        return "No se encontraron estudiantes";
                    }
                }
            });
        });
    </script>
</body>
</html>