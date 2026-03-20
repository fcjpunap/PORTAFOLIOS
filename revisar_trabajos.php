<?php
// revisar_trabajos.php - Lista de envíos con opciones de Borrado y Portafolio
session_start();
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['docente', 'admin'])) {
    header('Location: login.php');
    exit;
}
require_once 'config/conexion.php';

$actividad_id = $_GET['id'] ?? null;
if (!$actividad_id) die("Actividad no especificada.");

$mensaje = ''; $tipo_mensaje = '';

// Función para borrar carpetas con contenido (Expedientes Web)
function eliminarDirectorioRecursivo($dir) {
    if (!is_dir($dir)) return false;
    $items = array_diff(scandir($dir), array('.','..'));
    foreach ($items as $item) {
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        is_dir($path) ? eliminarDirectorioRecursivo($path) : @unlink($path);
    }
    return @rmdir($dir);
}

// Procesar Eliminación de un Envío
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'eliminar_envio') {
    $envio_id_eliminar = $_POST['envio_id'];
    try {
        // 1. Obtener y borrar el archivo físico (PDF) o Carpeta (HTML)
        $stmt_archivo = $pdo->prepare("SELECT ruta_archivo, tipo_archivo FROM anexos WHERE envio_id = ?");
        $stmt_archivo->execute([$envio_id_eliminar]);
        while ($anexo = $stmt_archivo->fetch(PDO::FETCH_ASSOC)) {
            if ($anexo['tipo_archivo'] === 'html') {
                $dir = is_dir($anexo['ruta_archivo']) ? $anexo['ruta_archivo'] : dirname($anexo['ruta_archivo']);
                if (strpos($dir, 'uploads/expedientes') !== false) {
                    eliminarDirectorioRecursivo($dir);
                }
            } else {
                if (file_exists($anexo['ruta_archivo'])) {
                    @unlink($anexo['ruta_archivo']);
                }
            }
        }
        // 2. Borrar de la base de datos (ON DELETE CASCADE borrará integrantes y anexos)
        $stmt_del = $pdo->prepare("DELETE FROM envios_fichas WHERE id = ?");
        $stmt_del->execute([$envio_id_eliminar]);
        $mensaje = "El envío ha sido eliminado. El estudiante ahora puede volver a enviar su trabajo.";
        $tipo_mensaje = "success";
    } catch (PDOException $e) {
        $mensaje = "Error al eliminar: " . $e->getMessage();
        $tipo_mensaje = "danger";
    }
}

// Obtener datos de la actividad y del curso
$stmt_act = $pdo->prepare("SELECT a.*, c.nombre_curso, c.id as curso_id FROM actividades_fichas a JOIN cursos c ON a.curso_id = c.id WHERE a.id = ?");
$stmt_act->execute([$actividad_id]);
$actividad = $stmt_act->fetch(PDO::FETCH_ASSOC);

if (!$actividad) die("Actividad no encontrada.");

// Obtener todos los envíos para esta actividad
$stmt_envios = $pdo->prepare("
    SELECT e.id, e.fecha_envio, e.estado, e.calificacion, u.id as estudiante_id, u.nombres, u.apellidos, u.codigo_estudiante
    FROM envios_fichas e
    JOIN usuarios u ON e.lider_id = u.id
    WHERE e.actividad_id = ?
    ORDER BY e.fecha_envio DESC
");
$stmt_envios->execute([$actividad_id]);
$envios = $stmt_envios->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Revisar Trabajos | UNAP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> body { background-color: #f8f9fa; } .bg-unap { background-color: #0b2e59; } </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-unap shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="actividades.php"><i class="fas fa-arrow-left me-2"></i> Volver a Actividades</a>
        </div>
    </nav>
    <main class="container py-4">
        
        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipo_mensaje ?> shadow-sm alert-dismissible fade show">
                <i class="fas fa-check-circle me-1"></i> <?= $mensaje ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 border-top border-4 border-primary mb-4">
            <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <span class="badge bg-info mb-2">Curso: <?= htmlspecialchars($actividad['nombre_curso']) ?></span>
                    <h4 class="fw-bold text-secondary mb-0"><?= htmlspecialchars($actividad['titulo_caso']) ?></h4>
                    <p class="text-muted small mb-0 mt-1"><i class="fas fa-users"></i> Modalidad: <?= $actividad['tipo_trabajo'] ?></p>
                </div>
                <div>
                    <a href="generar_portafolio.php?curso_id=<?= $actividad['curso_id'] ?>" target="_blank" class="btn btn-dark fw-bold shadow-sm">
                        <i class="fas fa-book-open me-2"></i> Portafolio Completo del Curso
                    </a>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body table-responsive p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Autor / Líder</th>
                            <th>Código</th>
                            <th>Fecha de Envío</th>
                            <th>Estado</th>
                            <th>Nota</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($envios)): ?>
                            <tr><td colspan="6" class="text-center py-5 text-muted">Aún no hay trabajos enviados para esta actividad.</td></tr>
                        <?php else: ?>
                            <?php foreach($envios as $e): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-dark"><?= htmlspecialchars($e['apellidos'] . ', ' . $e['nombres']) ?></td>
                                <td><?= htmlspecialchars($e['codigo_estudiante']) ?></td>
                                <td class="small text-muted"><?= date('d/m/Y h:i A', strtotime($e['fecha_envio'])) ?></td>
                                <td>
                                    <?php if($e['estado'] == 'Revisado'): ?>
                                        <span class="badge bg-success"><i class="fas fa-check-double me-1"></i> Revisado</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i> Por revisar</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $e['calificacion'] >= 14 ? 'bg-primary' : ($e['calificacion'] !== null ? 'bg-danger' : 'bg-secondary') ?> fs-6">
                                        <?= $e['calificacion'] !== null ? $e['calificacion'] : '--' ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group shadow-sm">
                                        <a href="calificar.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-primary" title="Revisar y Calificar">
                                            <i class="fas fa-gavel"></i>
                                        </a>
                                        <a href="generar_portafolio.php?curso_id=<?= $actividad['curso_id'] ?>&estudiante_id=<?= $e['estudiante_id'] ?>" target="_blank" class="btn btn-sm btn-dark" title="Generar Expediente del Alumno">
                                            <i class="fas fa-file-pdf"></i>
                                        </a>
                                        <form action="" method="POST" onsubmit="return confirm('¿Estás seguro de ELIMINAR este trabajo? Se borrarán sus respuestas y el PDF, permitiendo al alumno volver a enviarlo.');" style="display:inline;">
                                            <input type="hidden" name="accion" value="eliminar_envio">
                                            <input type="hidden" name="envio_id" value="<?= $e['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Eliminar para Recuperación">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>