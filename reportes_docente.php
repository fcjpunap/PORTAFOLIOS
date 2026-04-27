<?php
// reportes_docente.php - Dashboard BI (Adaptado para Docentes y Administradores)
session_set_cookie_params(14400);
ini_set("session.gc_maxlifetime", 14400);
session_start();
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['docente', 'admin'])) { 
    header('Location: login.php'); 
    exit; 
}
require_once 'config/conexion.php';

$usuario_id = $_SESSION['usuario_id'];
$es_admin = ($_SESSION['rol'] === 'admin');
$curso_filtro = $_GET['curso_id'] ?? 'todos';

// 1. Obtener los cursos (Si es admin ve TODOS, si es docente ve los SUYOS)
if ($es_admin) {
    $stmt_cursos = $pdo->query("SELECT id, nombre_curso FROM cursos ORDER BY nombre_curso");
    $cursos_disponibles = $stmt_cursos->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt_cursos = $pdo->prepare("SELECT id, nombre_curso FROM cursos WHERE docente_id = ? ORDER BY nombre_curso");
    $stmt_cursos->execute([$usuario_id]);
    $cursos_disponibles = $stmt_cursos->fetchAll(PDO::FETCH_ASSOC);
}

$ids_cursos = array_column($cursos_disponibles, 'id');

if (empty($ids_cursos)) {
    die("<div style='padding:50px; text-align:center; font-family:sans-serif;'><h3>No hay cursos disponibles para analizar.</h3></div>");
}

// Filtros SQL
$where_curso = ($curso_filtro !== 'todos') ? " AND c.id = " . (int)$curso_filtro : " AND c.id IN (" . implode(',', $ids_cursos) . ")";

// ==========================================
// MOTOR DEL DIAGNOSTICADOR PEDAGÓGICO
// ==========================================
$promedio_global = 0; $total_alumnos = 0; $actividad_critica = ['titulo' => 'Ninguna', 'promedio' => 20];
$alumnos_riesgo = 0; $alumnos_excelentes = 0;

// Calcular promedio global
$stmt_prom = $pdo->query("SELECT AVG(e.calificacion) FROM envios_fichas e JOIN actividades_fichas a ON e.actividad_id = a.id JOIN cursos c ON a.curso_id = c.id WHERE e.calificacion IS NOT NULL $where_curso");
$promedio_global = round((float)$stmt_prom->fetchColumn(), 2);

// Detectar Actividad más difícil (Menor Promedio)
$stmt_dificil = $pdo->query("
    SELECT a.titulo_caso, AVG(e.calificacion) as prom 
    FROM actividades_fichas a 
    JOIN envios_fichas e ON a.id = e.actividad_id 
    JOIN cursos c ON a.curso_id = c.id 
    WHERE e.calificacion IS NOT NULL $where_curso 
    GROUP BY a.id ORDER BY prom ASC LIMIT 1
");
if ($row = $stmt_dificil->fetch(PDO::FETCH_ASSOC)) { $actividad_critica = ['titulo' => $row['titulo_caso'], 'promedio' => round($row['prom'], 2)]; }

// Rendimiento por Estudiante
$sql_estudiantes = "
    SELECT u.id as estudiante_id, u.codigo_estudiante, u.nombres, u.apellidos, c.id as curso_id, c.nombre_curso,
           COUNT(DISTINCT a.id) as total_actividades_curso,
           COUNT(DISTINCT e.id) as actividades_entregadas,
           AVG(e.calificacion) as promedio_alumno
    FROM matriculas m
    JOIN usuarios u ON m.estudiante_id = u.id
    JOIN cursos c ON m.curso_id = c.id
    LEFT JOIN actividades_fichas a ON c.id = a.curso_id
    LEFT JOIN envios_fichas e ON a.id = e.actividad_id AND (e.lider_id = u.id OR e.id IN (SELECT envio_id FROM envio_integrantes WHERE estudiante_id = u.id))
    WHERE 1=1 $where_curso
    GROUP BY u.id, c.id
";
$estudiantes_data = $pdo->query($sql_estudiantes)->fetchAll(PDO::FETCH_ASSOC);

$datos_grafico_barras = ['labels' => [], 'data' => []];

foreach ($estudiantes_data as $est) {
    $prom = round((float)$est['promedio_alumno'], 2);
    if ($prom > 0 && $prom < 11.5) { $alumnos_riesgo++; }
    if ($prom >= 16) { $alumnos_excelentes++; }
    $total_alumnos++;
}

// Datos para Gráfico
$sql_graf_act = "SELECT a.titulo_caso, AVG(e.calificacion) as prom FROM actividades_fichas a LEFT JOIN envios_fichas e ON a.id = e.actividad_id JOIN cursos c ON a.curso_id = c.id WHERE 1=1 $where_curso GROUP BY a.id ORDER BY a.fecha_cierre ASC LIMIT 10";
$stmt_ga = $pdo->query($sql_graf_act);
while($row = $stmt_ga->fetch(PDO::FETCH_ASSOC)) {
    $datos_grafico_barras['labels'][] = substr($row['titulo_caso'], 0, 15) . '...';
    $datos_grafico_barras['data'][] = round((float)$row['prom'], 2);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>BI y Diagnóstico | UNAP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style> 
        body { background-color: #f4f7f6; } 
        .bg-unap { background-color: #0b2e59; } 
        .card-bi { border-radius: 12px; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .diagnostic-box { border-left: 5px solid #6f42c1; background: #f8f9fa; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-unap shadow-sm mb-4">
        <div class="container">
            <a class="navbar-brand" href="<?= $es_admin ? 'dashboard_admin.php' : 'dashboard_docente.php' ?>">
                <i class="fas fa-arrow-left me-2"></i> Volver al Panel
            </a>
            <span class="text-white fw-bold"><i class="fas fa-brain text-warning me-2"></i> Inteligencia Pedagógica <?= $es_admin ? '(Admin)' : '' ?></span>
        </div>
    </nav>

    <main class="container mb-5">
        <div class="card card-bi mb-4 border-top border-4 border-primary">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div><h5 class="fw-bold text-secondary mb-0"><i class="fas fa-filter text-primary me-2"></i> Filtro de Análisis</h5></div>
                <form method="GET" class="d-flex" style="min-width: 300px;">
                    <select name="curso_id" class="form-select me-2" onchange="this.form.submit()">
                        <option value="todos">Todos los cursos (Global)</option>
                        <?php foreach($cursos_disponibles as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($curso_filtro == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['nombre_curso']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>

        <div class="card card-bi diagnostic-box p-4 mb-4">
            <h5 class="fw-bold text-dark mb-3"><i class="fas fa-robot text-primary me-2"></i> Diagnóstico del Sistema</h5>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="p-3 bg-white rounded shadow-sm border border-danger border-opacity-25 h-100">
                        <h6 class="text-danger fw-bold"><i class="fas fa-exclamation-triangle me-1"></i> Riesgo Académico</h6>
                        <h2 class="fw-bold mb-0"><?= $alumnos_riesgo ?> <span class="fs-6 text-muted fw-normal">alumnos</span></h2>
                        <p class="small text-muted mb-0 mt-2">Promedio menor a 11.5. Requieren atención.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="p-3 bg-white rounded shadow-sm border border-warning border-opacity-25 h-100">
                        <h6 class="text-warning fw-bold text-dark"><i class="fas fa-chart-line me-1"></i> Tema más difícil</h6>
                        <h5 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($actividad_critica['titulo']) ?></h5>
                        <p class="small text-muted mb-0 mt-2">Promedio: <strong><?= $actividad_critica['promedio'] ?></strong>.</p>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="p-3 bg-white rounded shadow-sm border border-success border-opacity-25 h-100">
                        <h6 class="text-success fw-bold"><i class="fas fa-star me-1"></i> Rendimiento Global</h6>
                        <h2 class="fw-bold mb-0"><?= $promedio_global ?> <span class="fs-6 text-muted fw-normal">/ 20</span></h2>
                        <p class="small text-muted mb-0 mt-2"><?= $alumnos_excelentes ?> casos de excelencia.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-5">
            <div class="col-12">
                <div class="card card-bi p-4 h-100">
                    <h6 class="fw-bold text-secondary mb-4">Evolución de Calificaciones por Actividad</h6>
                    <div style="height: 300px;"><canvas id="graficoEvolucion"></canvas></div>
                </div>
            </div>
        </div>

        <div class="card card-bi border-top border-4 border-success">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0 fw-bold text-secondary"><i class="fas fa-users text-success me-2"></i> Buscador de Estudiantes y Expedientes</h5>
            </div>
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table id="tablaEstudiantes" class="table table-hover align-middle w-100">
                        <thead class="table-light">
                            <tr>
                                <th>Código</th>
                                <th>Estudiante</th>
                                <th>Curso</th>
                                <th>Entregas</th>
                                <th>Promedio</th>
                                <th>Estado</th>
                                <th class="text-center">Generar Portafolio</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($estudiantes_data as $e): 
                                $prom = round((float)$e['promedio_alumno'], 2);
                                $entregas = $e['actividades_entregadas'] . '/' . $e['total_actividades_curso'];
                                $estado = 'Regular'; $color = 'secondary';
                                if ($prom >= 14) { $estado = 'Destacado'; $color = 'success'; }
                                elseif ($prom > 0 && $prom < 11.5) { $estado = 'En Riesgo'; $color = 'danger'; }
                                elseif ($prom == 0) { $estado = 'Sin Datos'; $color = 'warning text-dark'; }
                            ?>
                            <tr>
                                <td class="fw-bold text-muted"><?= $e['codigo_estudiante'] ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($e['apellidos'] . ', ' . $e['nombres']) ?></td>
                                <td class="small"><?= htmlspecialchars($e['nombre_curso']) ?></td>
                                <td><?= $entregas ?></td>
                                <td><span class="fw-bold text-<?= $color ?>"><?= $prom > 0 ? $prom : '--' ?></span></td>
                                <td><span class="badge bg-<?= $color ?>"><?= $estado ?></span></td>
                                <td class="text-center">
                                    <div class="btn-group shadow-sm">
                                        <a href="generar_portafolio.php?curso_id=<?= $e['curso_id'] ?>&estudiante_id=<?= $e['estudiante_id'] ?>" target="_blank" class="btn btn-sm btn-outline-primary" title="Portafolio del Curso">
                                            <i class="fas fa-file-pdf"></i> Del Curso
                                        </a>
                                        <a href="generar_portafolio.php?curso_id=todos&estudiante_id=<?= $e['estudiante_id'] ?>" target="_blank" class="btn btn-sm btn-dark" title="Portafolio Global">
                                            <i class="fas fa-globe"></i> Global
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        $(document).ready(function() {
            $('#tablaEstudiantes').DataTable({ language: { url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json' }, pageLength: 10 });
        });
        const ctx = document.getElementById('graficoEvolucion').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode($datos_grafico_barras['labels']) ?>,
                datasets: [{ label: 'Promedio de Calificación', data: <?= json_encode($datos_grafico_barras['data']) ?>, borderColor: '#0b2e59', backgroundColor: 'rgba(11, 46, 89, 0.2)', borderWidth: 3, pointBackgroundColor: '#ffc107', pointRadius: 5, fill: true, tension: 0.3 }]
            },
            options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true, max: 20 } } }
        });
    </script>
</body>
</html>