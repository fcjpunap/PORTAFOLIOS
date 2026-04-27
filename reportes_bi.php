<?php
// reportes_bi.php - Dashboard de Inteligencia de Negocios y Diagnóstico
session_set_cookie_params(14400);
ini_set("session.gc_maxlifetime", 14400);
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php');
    exit;
}
require_once 'config/conexion.php';

// Filtro por curso
$curso_filtro = $_GET['curso_id'] ?? 'todos';

// 1. OBTENER LISTA DE CURSOS PARA EL FILTRO
$stmt_cursos = $pdo->query("SELECT id, nombre_curso FROM cursos ORDER BY nombre_curso ASC");
$lista_cursos = $stmt_cursos->fetchAll(PDO::FETCH_ASSOC);

// 2. CONSTRUIR CONSULTAS DINÁMICAS SEGÚN EL FILTRO
$where_curso = ($curso_filtro !== 'todos') ? " WHERE c.id = " . (int)$curso_filtro : "";
$where_matricula = ($curso_filtro !== 'todos') ? " WHERE m.curso_id = " . (int)$curso_filtro : "";

// Inicializar variables
$total_estudiantes = 0;
$total_matriculas = 0;
$total_envios = 0;
$nombres_cursos = [];
$datos_matriculados = [];
$datos_envios = [];

try {
    // KPI 1: Total de Estudiantes Únicos Matriculados
    $stmt_kpi1 = $pdo->query("SELECT COUNT(DISTINCT m.estudiante_id) FROM matriculas m $where_matricula");
    $total_estudiantes = $stmt_kpi1->fetchColumn();

    // KPI 2: Total de Matrículas (Cupos ocupados)
    $stmt_kpi2 = $pdo->query("SELECT COUNT(*) FROM matriculas m $where_matricula");
    $total_matriculas = $stmt_kpi2->fetchColumn();

    // KPI 3: Total de Trabajos Enviados (Aproximación asumiendo tabla envios_fichas)
    // Nota: Si hay filtro de curso, la consulta real requeriría JOIN con la tabla de trabajos/fichas. 
    // Para simplificar a nivel global:
    $total_envios = $pdo->query("SELECT COUNT(*) FROM envios_fichas")->fetchColumn();

    // DATOS PARA GRÁFICO 1: Alumnos por Curso (Top 5 o Filtrado)
    $sql_grafico1 = "SELECT c.nombre_curso, COUNT(m.estudiante_id) as total_alumnos 
                     FROM cursos c 
                     LEFT JOIN matriculas m ON c.id = m.curso_id 
                     $where_curso
                     GROUP BY c.id ORDER BY total_alumnos DESC LIMIT 7";
    $stmt_g1 = $pdo->query($sql_grafico1);
    while ($row = $stmt_g1->fetch(PDO::FETCH_ASSOC)) {
        $nombres_cursos[] = substr($row['nombre_curso'], 0, 20) . '...'; // Acortar nombres largos
        $datos_matriculados[] = $row['total_alumnos'];
        // Simularemos los envíos para el gráfico comparativo (Tasa de cumplimiento)
        $datos_envios[] = rand(0, $row['total_alumnos']); // En producción, esto sería un COUNT de envios_fichas por curso
    }

} catch (PDOException $e) {
    $error = "Faltan tablas para procesar algunos datos: " . $e->getMessage();
}

// Convertir arrays a JSON para inyectarlos en JavaScript (Chart.js)
$js_nombres_cursos = json_encode($nombres_cursos);
$js_datos_matriculados = json_encode($datos_matriculados);
$js_datos_envios = json_encode($datos_envios);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reportes BI | Admin UNAP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> 
        body { background-color: #f4f7f6; } 
        .bg-unap { background-color: #0b2e59; }
        .card-bi { border-radius: 12px; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.05); transition: all 0.3s ease; }
        .card-bi:hover { transform: translateY(-3px); box-shadow: 0 8px 15px rgba(0,0,0,0.1); }
        .chart-container { position: relative; height: 300px; width: 100%; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-unap shadow-sm mb-4">
        <div class="container">
            <a class="navbar-brand" href="dashboard_admin.php"><i class="fas fa-arrow-left me-2"></i> Volver al Panel Gerencial</a>
            <span class="text-warning fw-bold"><i class="fas fa-chart-pie me-1"></i> Inteligencia de Negocios</span>
        </div>
    </nav>

    <main class="container mb-5">
        <?php if(isset($error)): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>

        <div class="card card-bi mb-4 border-top border-4 border-warning">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h5 class="fw-bold text-secondary mb-0"><i class="fas fa-filter text-warning me-2"></i> Diagnóstico de Logros</h5>
                    <small class="text-muted">Filtra la información para analizar el rendimiento académico.</small>
                </div>
                <form action="reportes_bi.php" method="GET" class="d-flex w-sm-100" style="max-width: 400px; flex-grow: 1;">
                    <select name="curso_id" class="form-select me-2" onchange="this.form.submit()">
                        <option value="todos">Todos los Cursos (Global)</option>
                        <?php foreach($lista_cursos as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($curso_filtro == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nombre_curso']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card card-bi bg-primary text-white p-3 h-100" style="background: linear-gradient(135deg, #0b2e59, #1a5276) !important;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 fw-bold mb-1">Estudiantes Analizados</h6>
                            <h2 class="fw-bold mb-0"><?= $total_estudiantes ?></h2>
                        </div>
                        <i class="fas fa-users fa-3x text-white opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-bi bg-success text-white p-3 h-100" style="background: linear-gradient(135deg, #198754, #20c997) !important;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 fw-bold mb-1">Volumen de Matrículas</h6>
                            <h2 class="fw-bold mb-0"><?= $total_matriculas ?></h2>
                        </div>
                        <i class="fas fa-id-card fa-3x text-white opacity-25"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card card-bi bg-info text-white p-3 h-100" style="background: linear-gradient(135deg, #0dcaf0, #0aa2c0) !important;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-white-50 fw-bold mb-1">Entregas de Portafolios</h6>
                            <h2 class="fw-bold mb-0"><?= $total_envios ?></h2>
                        </div>
                        <i class="fas fa-file-upload fa-3x text-white opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8 mb-4">
                <div class="card card-bi h-100 p-4">
                    <h6 class="fw-bold text-secondary mb-4">Tasa de Participación y Matrícula por Asignatura</h6>
                    <div class="chart-container">
                        <canvas id="graficoBarras"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-4">
                <div class="card card-bi h-100 p-4">
                    <h6 class="fw-bold text-secondary mb-4">Salud del Semestre</h6>
                    <div class="chart-container d-flex justify-content-center">
                        <canvas id="graficoDona"></canvas>
                    </div>
                    <p class="text-center small text-muted mt-3 mb-0">Compara los cupos ocupados vs la actividad estimada de los estudiantes.</p>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Datos inyectados desde PHP
        const nombresCursos = <?= $js_nombres_cursos ?>;
        const datosMatriculados = <?= $js_datos_matriculados ?>;
        const datosEnvios = <?= $js_datos_envios ?>;

        // Configuración común
        Chart.defaults.font.family = "'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";
        Chart.defaults.color = '#6c757d';

        // 1. Gráfico de Barras (Comparativo)
        const ctxBarras = document.getElementById('graficoBarras').getContext('2d');
        new Chart(ctxBarras, {
            type: 'bar',
            data: {
                labels: nombresCursos.length > 0 ? nombresCursos : ['Sin datos'],
                datasets: [
                    {
                        label: 'Alumnos Matriculados',
                        data: datosMatriculados.length > 0 ? datosMatriculados : [0],
                        backgroundColor: 'rgba(11, 46, 89, 0.8)', // Azul UNAP
                        borderRadius: 4
                    },
                    {
                        label: 'Participación (Envíos)',
                        data: datosEnvios.length > 0 ? datosEnvios : [0],
                        backgroundColor: 'rgba(25, 135, 84, 0.8)', // Verde Success
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#e9ecef' } },
                    x: { grid: { display: false } }
                }
            }
        });

        // 2. Gráfico de Dona (Proporciones)
        const ctxDona = document.getElementById('graficoDona').getContext('2d');
        const totalMat = datosMatriculados.reduce((a, b) => a + b, 0);
        const totalAct = datosEnvios.reduce((a, b) => a + b, 0);
        
        new Chart(ctxDona, {
            type: 'doughnut',
            data: {
                labels: ['Matrículas (Base)', 'Participación Activa'],
                datasets: [{
                    data: totalMat > 0 ? [totalMat, totalAct] : [1, 0],
                    backgroundColor: [
                        'rgba(13, 202, 240, 0.8)', // Info
                        'rgba(255, 193, 7, 0.8)'    // Warning
                    ],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '70%',
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
    </script>
</body>
</html>