<?php
// index.php - Portafolio Digital Institucional UNAP - FCJP
session_set_cookie_params(14400);
ini_set("session.gc_maxlifetime", 14400);
session_start();
require_once 'config/conexion.php';

// 1. Configuración de Paginación y Filtros
$articulos_por_pagina = 9;
$pagina_actual = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * $articulos_por_pagina;

$curso_nombre_filtro = isset($_GET['curso_nombre']) ? trim($_GET['curso_nombre']) : '';
$busqueda_general = isset($_GET['q']) ? trim($_GET['q']) : '';

// 2. Obtener lista de nombres de cursos para el datalist
$stmt_lista_cursos = $pdo->query("SELECT DISTINCT nombre_curso FROM cursos ORDER BY nombre_curso ASC");
$nombres_cursos = $stmt_lista_cursos->fetchAll(PDO::FETCH_COLUMN);

// 3. Construir consulta con filtros y orden
$params = [];
$where_sql = "WHERE e.estado = 'Revisado'";

if ($curso_nombre_filtro !== '') {
    $where_sql .= " AND c.nombre_curso LIKE ?";
    $params[] = "%$curso_nombre_filtro%";
}

if ($busqueda_general !== '') {
    $where_sql .= " AND (a.titulo_caso LIKE ? OR e.factum LIKE ? OR e.dogmatica LIKE ?)";
    $bus_param = "%$busqueda_general%";
    $params = array_merge($params, [$bus_param, $bus_param, $bus_param]);
}

// Contar total para paginación
$sql_count = "SELECT COUNT(*) FROM envios_fichas e INNER JOIN actividades_fichas a ON e.actividad_id = a.id INNER JOIN cursos c ON a.curso_id = c.id $where_sql";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_articulos = $stmt_count->fetchColumn();
$total_paginas = ceil($total_articulos / $articulos_por_pagina);

// Consulta de datos
$sql_repositorio = "
    SELECT e.id as envio_id, a.titulo_caso, e.factum, e.dogmatica, c.nombre_curso, e.fecha_envio
    FROM envios_fichas e
    INNER JOIN actividades_fichas a ON e.actividad_id = a.id
    INNER JOIN cursos c ON a.curso_id = c.id
    $where_sql
    ORDER BY e.fecha_envio DESC 
    LIMIT $articulos_por_pagina OFFSET $offset
";
$stmt_res = $pdo->prepare($sql_repositorio);
$stmt_res->execute($params);
$casos_resueltos = $stmt_res->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portafolio Digital | UNAP - FCJP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { display: flex; flex-direction: column; min-height: 100vh; background-color: #f4f7f6; }
        main { flex: 1; }
        .bg-unap { background-color: #0b2e59; }
        .search-container { background: linear-gradient(135deg, #0b2e59 0%, #1a4a82 100%); padding: 3rem 1rem; color: white; border-bottom: 5px solid #ffc107; }
        
        /* Estilo Mejorado de la Cabecera */
        .navbar-logo { height: 75px; width: auto; margin-right: 15px; }
        .brand-text-container { line-height: 1.1; }
        .brand-title { font-size: 1.5rem; font-weight: 800; letter-spacing: 1px; color: #ffffff; display: block; }
        .brand-sub { font-size: 0.85rem; font-weight: 500; color: #ffc107; display: block; }
        .brand-college { font-size: 0.75rem; color: rgba(255,255,255,0.8); display: block; margin-top: 2px; }

        .caso-card { transition: transform 0.2s; border-radius: 12px; overflow: hidden; height: 100%; border: none; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .caso-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .text-justify { text-align: justify; }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-unap shadow-sm py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index.php">
                <img src="https://derecho.unap.edu.pe/portafolios/logofcjpcolor.png" alt="Logo FCJP" class="navbar-logo">
                <div class="brand-text-container">
                    <span class="brand-title">PORTAFOLIO DIGITAL</span>
                    <span class="brand-sub">UNIVERSIDAD NACIONAL DEL ALTIPLANO</span>
                    <span class="brand-college">Facultad de Ciencias Jurídicas y Políticas</span>
                </div>
            </a>
            <div class="ms-auto">
                <?php if(isset($_SESSION['usuario_id'])): 
                    $url = ($_SESSION['rol'] === 'admin') ? 'dashboard_admin.php' : (($_SESSION['rol'] === 'docente') ? 'dashboard_docente.php' : 'dashboard_estudiante.php');
                ?>
                    <a class="btn btn-warning fw-bold px-4 rounded-pill shadow-sm" href="<?= $url ?>"><i class="fas fa-tachometer-alt me-1"></i> Mi Panel</a>
                <?php else: ?>
                    <a class="btn btn-outline-warning fw-bold px-4 rounded-pill" href="login.php"><i class="fas fa-sign-in-alt me-1"></i> Acceso</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <header class="search-container text-center">
        <div class="container">
            <h1 class="h3 fw-bold mb-4">Explorar Casos Jurídicos Resueltos y otras evidencias de desempeño de los estudiantes de la FCJP</h1>
            
            <form action="index.php" method="GET" class="row g-2 justify-content-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-0"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="q" class="form-control border-0" placeholder="Buscar por tema o palabra clave..." value="<?= htmlspecialchars($busqueda_general) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-0"><i class="fas fa-book text-muted"></i></span>
                        <input list="cursosData" name="curso_nombre" class="form-control border-0" placeholder="Escribir nombre del curso..." value="<?= htmlspecialchars($curso_nombre_filtro) ?>">
                        <datalist id="cursosData">
                            <?php foreach($nombres_cursos as $nom): ?>
                                <option value="<?= htmlspecialchars($nom) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-warning w-100 fw-bold shadow-sm">Filtrar</button>
                </div>
                <?php if($curso_nombre_filtro !== '' || $busqueda_general !== ''): ?>
                    <div class="col-md-1">
                        <a href="index.php" class="btn btn-danger w-100" title="Limpiar Filtros"><i class="fas fa-times"></i></a>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </header>

    <main class="container py-5">
        <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
            <?php if(empty($casos_resueltos)): ?>
                <div class="col-12 text-center py-5">
                    <i class="fas fa-folder-open fa-3x text-muted mb-3 opacity-50"></i>
                    <p class="text-muted">No se encontraron resultados para la búsqueda actual.</p>
                </div>
            <?php else: ?>
                <?php foreach ($casos_resueltos as $caso): ?>
                    <div class="col">
                        <div class="card caso-card shadow-sm">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="badge bg-primary bg-opacity-10 text-primary small"><?= htmlspecialchars($caso['nombre_curso']) ?></span>
                                    <small class="text-muted"><i class="far fa-calendar-alt"></i> <?= date('d/m/Y', strtotime($caso['fecha_envio'])) ?></small>
                                </div>
                                <h5 class="card-title text-dark fw-bold" style="font-size: 1.05rem;"><?= htmlspecialchars($caso['titulo_caso']) ?></h5>
                                <p class="card-text text-muted small text-justify">
                                    <?= htmlspecialchars(mb_substr($caso['factum'], 0, 160)) ?>...
                                </p>
                                <div class="mt-auto pt-3">
                                    <a href="detalle_publico.php?id=<?= $caso['envio_id'] ?>" class="btn btn-sm btn-outline-primary w-100 rounded-pill fw-bold">Ver Evidencia Completa</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($total_paginas > 1): ?>
            <nav class="mt-5">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $total_paginas; $i++): 
                        $query_string = http_build_query([
                            'p' => $i,
                            'curso_nombre' => $curso_nombre_filtro,
                            'q' => $busqueda_general
                        ]);
                    ?>
                        <li class="page-item <?= ($i == $pagina_actual) ? 'active' : '' ?>">
                            <a class="page-link shadow-sm" href="?<?= $query_string ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </main>

    <div class="container mb-4">
        <div class="p-3 bg-white rounded border border-light shadow-sm">
            <p class="text-muted small mb-0 text-justify" style="line-height: 1.4;">
                <strong>Aviso Legal:</strong> Los contenidos y archivos publicados en este portafolio son de exclusiva responsabilidad de los estudiantes autores. La Facultad de Ciencias Jurídicas y Políticas y la Universidad Nacional del Altiplano no suscriben necesariamente las opiniones aquí vertidas, las cuales tienen un fin estrictamente pedagógico y de acreditación académica.
            </p>
        </div>
    </div>

    <footer class="bg-white py-4 border-top shadow-sm">
        <div class="container text-center text-muted small">
            <p class="mb-1 fw-bold text-dark">&copy; <?= date('Y') ?> UNIVERSIDAD NACIONAL DEL ALTIPLANO</p>
            <p class="mb-0">Facultad de Ciencias Jurídicas y Políticas - Puno, Perú</p>
            <div class="mt-3" style="font-size: 0.75rem;">
                Diseñado por <strong class="text-primary">Michael Espinoza Coila</strong> asistido por <strong class="text-primary">Gemini Pro</strong>.
            </div>
        </div>
    </footer>
</body>
</html>