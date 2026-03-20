<?php
// Descomentar estas 3 líneas si en el futuro vuelve a salir pantalla blanca para ver el error:
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);

require_once 'config/conexion.php';

// Configuración del Paginador
$casos_por_pagina = 6;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * $casos_por_pagina;

$busqueda = isset($_GET['codigo_estudiante']) ? trim($_GET['codigo_estudiante']) : '';

// Variables para la consulta
$parametros = [];
$where_sql = "";

if ($busqueda !== '') {
    $where_sql = "WHERE u.codigo_estudiante = :codigo";
    $parametros[':codigo'] = $busqueda;
}

// 1. Contar el total de registros para calcular las páginas
$sql_count = "SELECT COUNT(DISTINCT f.id) 
              FROM envios_fichas f
              INNER JOIN envio_integrantes ei ON f.id = ei.envio_id
              INNER JOIN usuarios u ON ei.estudiante_id = u.id 
              $where_sql";
$stmt_count = $pdo->prepare($sql_count);
$stmt_count->execute($parametros);
$total_casos = $stmt_count->fetchColumn();
$total_paginas = ceil($total_casos / $casos_por_pagina);

// 2. Obtener los registros (CORREGIDO: Se eliminó a.semestre que no existía en esa tabla)
$sql = "SELECT DISTINCT f.id, a.titulo_caso, f.factum, f.fallo, f.fecha_envio 
        FROM envios_fichas f
        INNER JOIN actividades_fichas a ON f.actividad_id = a.id
        INNER JOIN envio_integrantes ei ON f.id = ei.envio_id
        INNER JOIN usuarios u ON ei.estudiante_id = u.id
        $where_sql
        ORDER BY f.fecha_envio DESC 
        LIMIT $casos_por_pagina OFFSET $offset";

$stmt = $pdo->prepare($sql);
if ($busqueda !== '') {
    $stmt->bindValue(':codigo', $busqueda, PDO::PARAM_STR);
}
$stmt->execute();
$portafolios = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Repositorio Público | Derecho UNAP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card-hover:hover { transform: translateY(-3px); transition: 0.3s; box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
        .bg-unap { background-color: #0b2e59; }
    </style>
</head>
<body class="bg-light d-flex flex-column min-vh-100">

    <nav class="navbar navbar-dark bg-unap shadow-sm">
        <div class="container">
            <span class="navbar-brand mb-0 h1"><i class="fas fa-balance-scale"></i> Repositorio de Casos Penales UNAP</span>
            <a href="login.php" class="btn btn-outline-light btn-sm"><i class="fas fa-lock"></i> Acceso Docente</a>
        </div>
    </nav>

    <main class="container mt-5 flex-grow-1">
        <div class="row mb-4 align-items-center">
            <div class="col-md-6">
                <h2 class="text-primary fw-bold"><i class="fas fa-folder-open"></i> Índice de Portafolios (2026)</h2>
                <p class="text-muted">Evidencias de aprendizaje del curso de Derecho Penal Especial III.</p>
            </div>
            <div class="col-md-6 text-md-end">
                <form method="GET" action="vista_publica.php" class="d-flex justify-content-md-end">
                    <div class="input-group" style="max-width: 350px;">
                        <input type="text" name="codigo_estudiante" class="form-control" placeholder="Buscar por código (Ej. 123456)" value="<?= htmlspecialchars($busqueda) ?>">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                        <?php if($busqueda !== ''): ?>
                            <a href="vista_publica.php" class="btn btn-outline-secondary"><i class="fas fa-times"></i></a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>

        <hr class="mb-4">

        <?php if (count($portafolios) > 0): ?>
            <div class="row g-4">
                <?php foreach ($portafolios as $ficha): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 shadow-sm card-hover border-0 border-top border-primary border-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge bg-secondary"><i class="far fa-calendar-alt"></i> <?= date('d/m/Y', strtotime($ficha['fecha_envio'])) ?></span>
                                </div>
                                <h5 class="card-title text-dark fw-bold"><?= htmlspecialchars($ficha['titulo_caso']) ?></h5>
                                <p class="card-text text-muted small" style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                                    <strong>Hechos:</strong> <?= htmlspecialchars($ficha['factum']) ?>
                                </p>
                                <p class="card-text small mb-0"><strong class="text-success">Fallo:</strong> <span class="fst-italic"><?= htmlspecialchars(strlen($ficha['fallo']) > 60 ? substr($ficha['fallo'], 0, 60).'...' : $ficha['fallo']) ?></span></p>
                            </div>
                            <div class="card-footer bg-white border-0 text-center pb-3">
                                <a href="detalle_ficha.php?id=<?= $ficha['id'] ?>" class="btn btn-outline-primary btn-sm w-100"><i class="fas fa-book-reader"></i> Leer Resolución Completa</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($total_paginas > 1): ?>
                <nav aria-label="Navegación de páginas" class="mt-5">
                    <ul class="pagination justify-content-center">
                        <li class="page-item <?= ($pagina_actual <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?pagina=<?= $pagina_actual - 1 ?><?= ($busqueda) ? '&codigo_estudiante='.$busqueda : '' ?>">Anterior</a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <li class="page-item <?= ($pagina_actual == $i) ? 'active' : '' ?>">
                                <a class="page-link" href="?pagina=<?= $i ?><?= ($busqueda) ? '&codigo_estudiante='.$busqueda : '' ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?= ($pagina_actual >= $total_paginas) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?pagina=<?= $pagina_actual + 1 ?><?= ($busqueda) ? '&codigo_estudiante='.$busqueda : '' ?>">Siguiente</a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>

        <?php else: ?>
            <div class="alert alert-warning text-center p-5 shadow-sm rounded">
                <i class="fas fa-search fa-3x mb-3 text-muted"></i>
                <h4>No se encontraron evidencias</h4>
                <p>Aún no hay fichas publicadas en el repositorio o el código de estudiante no tiene casos asociados.</p>
                <a href="vista_publica.php" class="btn btn-primary mt-2">Ver todo el repositorio</a>
            </div>
        <?php endif; ?>
    </main>

    <footer class="text-center py-4 mt-auto border-top bg-white shadow-sm">
        <div class="container">
            <p class="text-muted small mb-0">
                <i class="fas fa-university"></i> Universidad Nacional del Altiplano - Facultad de Ciencias Jurídicas y Políticas (2026)<br>
                <strong>Desarrollado por Michael Espinoza Coila con asistencia de Gemini Pro 3.1</strong>
            </p>
        </div>
    </footer>
</body>
</html>