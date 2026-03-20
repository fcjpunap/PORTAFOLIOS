<?php
// ver_todos_trabajos.php - Bandeja global de todos los envíos (Buscador y Paginación)
session_start();
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['docente', 'admin'])) {
    header('Location: login.php');
    exit;
}
require_once 'config/conexion.php';

$docente_id = $_SESSION['usuario_id'];
$es_admin = ($_SESSION['rol'] === 'admin');
$mensaje = ''; $tipo_mensaje = '';

// Función para borrar carpetas recursivamente
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
        $stmt_archivo = $pdo->prepare("SELECT ruta_archivo, tipo_archivo FROM anexos WHERE envio_id = ?");
        $stmt_archivo->execute([$envio_id_eliminar]);
        while ($anexo = $stmt_archivo->fetch(PDO::FETCH_ASSOC)) {
            if ($anexo['tipo_archivo'] === 'html') {
                $directorio = is_dir($anexo['ruta_archivo']) ? $anexo['ruta_archivo'] : dirname($anexo['ruta_archivo']);
                if (strpos($directorio, 'uploads/expedientes') !== false) {
                    eliminarDirectorioRecursivo($directorio);
                }
            } else {
                if (file_exists($anexo['ruta_archivo'])) {
                    @unlink($anexo['ruta_archivo']);
                }
            }
        }
        $stmt_del = $pdo->prepare("DELETE FROM envios_fichas WHERE id = ?");
        $stmt_del->execute([$envio_id_eliminar]);
        $mensaje = "El envío ha sido eliminado correctamente del sistema.";
        $tipo_mensaje = "success";
    } catch (PDOException $e) {
        $mensaje = "Error al eliminar: " . $e->getMessage();
        $tipo_mensaje = "danger";
    }
}
// ... [CONTINÚA EL RESTO DEL CÓDIGO DE BUSCADOR Y PAGINACIÓN EXACTAMENTE IGUAL AL QUE ENVIASTE] ...
$search = $_GET['buscar'] ?? '';
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina < 1) $pagina = 1;
$limite = 15; 
$offset = ($pagina - 1) * $limite;

$whereSql = "WHERE 1=1";
$params = [];

if (!$es_admin) {
    $whereSql .= " AND c.docente_id = ?";
    $params[] = $docente_id;
}

if ($search !== '') {
    $whereSql .= " AND (u.nombres LIKE ? OR u.apellidos LIKE ? OR a.titulo_caso LIKE ? OR c.nombre_curso LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

$sql_count = "SELECT COUNT(*) FROM envios_fichas e JOIN actividades_fichas a ON e.actividad_id = a.id JOIN cursos c ON a.curso_id = c.id JOIN usuarios u ON e.lider_id = u.id $whereSql";
$stmt_total = $pdo->prepare($sql_count);
$stmt_total->execute($params);
$total_envios = $stmt_total->fetchColumn();
$total_paginas = ceil($total_envios / $limite);

$sql_data = "
    SELECT e.id, e.fecha_envio, e.estado, e.calificacion, u.nombres, u.apellidos, u.codigo_estudiante,
           a.titulo_caso, a.tipo_trabajo, c.nombre_curso
    FROM envios_fichas e
    JOIN actividades_fichas a ON e.actividad_id = a.id
    JOIN cursos c ON a.curso_id = c.id
    JOIN usuarios u ON e.lider_id = u.id
    $whereSql
    ORDER BY e.fecha_envio DESC
    LIMIT $limite OFFSET $offset
";
$stmt_envios = $pdo->prepare($sql_data);
$stmt_envios->execute($params);
$envios = $stmt_envios->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Bandeja de Trabajos | UNAP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> body { background-color: #f8f9fa; } .bg-unap { background-color: #0b2e59; } </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-unap shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="<?= $es_admin ? 'dashboard_admin.php' : 'dashboard_docente.php' ?>"><i class="fas fa-arrow-left me-2"></i> Volver al panel</a>
        </div>
    </nav>
    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h3 class="text-secondary mb-0"><i class="fas fa-inbox text-primary me-2"></i> Todos los Trabajos Enviados</h3>
        </div>
        
        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipo_mensaje ?> shadow-sm alert-dismissible fade show">
                <i class="fas fa-check-circle me-1"></i> <?= $mensaje ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 border-top border-4 border-info">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0 fw-bold text-secondary">Bandeja de Entrada Global</h5>
                <form action="ver_todos_trabajos.php" method="GET" class="d-flex" style="min-width: 300px;">
                    <input type="text" name="buscar" class="form-control me-2" placeholder="Buscar alumno, curso o caso..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-info text-white"><i class="fas fa-search"></i></button>
                    <?php if ($search): ?><a href="ver_todos_trabajos.php" class="btn btn-outline-secondary ms-2"><i class="fas fa-times"></i></a><?php endif; ?>
                </form>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Estudiante / Grupo</th>
                            <th>Actividad y Curso</th>
                            <th>Fecha Envío</th>
                            <th>Estado</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($envios)): ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">No se encontraron trabajos.</td></tr>
                        <?php else: ?>
                            <?php foreach($envios as $e): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($e['apellidos'] . ', ' . $e['nombres']) ?></div>
                                    <div class="small text-muted"><i class="fas fa-hashtag"></i> <?= htmlspecialchars($e['codigo_estudiante']) ?> (<?= $e['tipo_trabajo'] ?>)</div>
                                </td>
                                <td>
                                    <div class="fw-bold text-secondary"><?= htmlspecialchars($e['titulo_caso']) ?></div>
                                    <div class="small text-muted"><i class="fas fa-book"></i> <?= htmlspecialchars($e['nombre_curso']) ?></div>
                                </td>
                                <td class="small text-muted"><?= date('d/m/Y h:i A', strtotime($e['fecha_envio'])) ?></td>
                                <td>
                                    <?php if($e['estado'] == 'Revisado'): ?>
                                        <span class="badge bg-success">Nota: <?= $e['calificacion'] ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group shadow-sm">
                                        <a href="calificar.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-primary" title="Revisar / Calificar">
                                            <i class="fas fa-gavel"></i>
                                        </a>
                                        <form action="ver_todos_trabajos.php" method="POST" onsubmit="return confirm('¿Estás seguro de ELIMINAR este trabajo? El estudiante podrá volver a enviar su ficha.');" style="display:inline;">
                                            <input type="hidden" name="accion" value="eliminar_envio">
                                            <input type="hidden" name="envio_id" value="<?= $e['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Eliminar envío para corrección">
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
            <?php if ($total_paginas > 1): ?>
                <div class="card-footer bg-white py-3">
                    <ul class="pagination pagination-sm justify-content-center mb-0">
                        <?php for ($i=1; $i<=$total_paginas; $i++): ?>
                            <li class="page-item <?= ($i==$pagina)?'active':'' ?>"><a class="page-link" href="?buscar=<?= urlencode($search) ?>&pagina=<?= $i ?>"><?= $i ?></a></li>
                        <?php endfor; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>