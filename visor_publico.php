<?php
// visor_publico.php - Acceso sin restricciones (Para Código QR - Soporta Global y Específico)
require_once 'config/conexion.php';

$curso_id = $_GET['curso_id'] ?? null;
$estudiante_id = $_GET['estudiante_id'] ?? null;

if (!$curso_id) {
    die("<h2 style='text-align:center; margin-top:50px; font-family:sans-serif;'>Enlace inválido o incompleto.</h2>");
}

$nombre_curso_mostrar = "";
$docente_mostrar = "";

// Lógica Inteligente
if ($curso_id !== 'todos') {
    $stmt_curso = $pdo->prepare("SELECT c.nombre_curso, u.nombres as doc_nom, u.apellidos as doc_ape FROM cursos c JOIN usuarios u ON c.docente_id = u.id WHERE c.id = ?");
    $stmt_curso->execute([$curso_id]);
    $curso = $stmt_curso->fetch(PDO::FETCH_ASSOC);

    if (!$curso) {
        die("<h2 style='text-align:center; margin-top:50px; font-family:sans-serif;'>El curso solicitado no existe.</h2>");
    }
    $nombre_curso_mostrar = $curso['nombre_curso'];
    $docente_mostrar = $curso['doc_ape'] . ', ' . $curso['doc_nom'];
} else {
    $nombre_curso_mostrar = "Consolidado Global (Múltiples Asignaturas)";
    $docente_mostrar = "Varios Docentes";
}

$nombre_estudiante = "Todos los estudiantes";
$params = [];

$sql_envios = "
    SELECT e.id, e.fecha_envio, e.respuestas_json, e.factum, e.tipicidad, e.dogmatica, e.fallo,
           a.titulo_caso, a.tipo_trabajo, c.nombre_curso,
           u.nombres as lider_nom, u.apellidos as lider_ape
    FROM envios_fichas e
    JOIN actividades_fichas a ON e.actividad_id = a.id
    JOIN cursos c ON a.curso_id = c.id
    JOIN usuarios u ON e.lider_id = u.id
    WHERE 1=1
";

if ($curso_id !== 'todos') {
    $sql_envios .= " AND a.curso_id = ?";
    $params[] = $curso_id;
}

if ($estudiante_id) {
    $stmt_est = $pdo->prepare("SELECT nombres, apellidos, codigo_estudiante FROM usuarios WHERE id = ?");
    $stmt_est->execute([$estudiante_id]);
    if ($est = $stmt_est->fetch(PDO::FETCH_ASSOC)) {
        $nombre_estudiante = $est['apellidos'] . " " . $est['nombres'] . " (" . $est['codigo_estudiante'] . ")";
    }
    $sql_envios .= " AND e.id IN (SELECT envio_id FROM envio_integrantes WHERE estudiante_id = ?)";
    $params[] = $estudiante_id;
}

$sql_envios .= " ORDER BY c.nombre_curso ASC, a.fecha_cierre ASC, e.id DESC";
$stmt_envios = $pdo->prepare($sql_envios);
$stmt_envios->execute($params);
$envios = $stmt_envios->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visor Público | UNAP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> 
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; } 
        .bg-unap { background-color: #0b2e59; }
        .respuesta-box { line-height: 1.7; text-align: justify; color: #444; }
    </style>
</head>
<body class="d-flex flex-column min-vh-100">
    <nav class="navbar navbar-dark bg-unap shadow-sm">
        <div class="container py-2">
            <span class="navbar-brand mb-0 h1 d-flex align-items-center">
                <i class="fas fa-university fa-2x me-3 text-warning"></i> 
                <div>
                    <div class="fw-bold">Portafolio Digital</div>
                    <div style="font-size: 0.8rem; opacity: 0.8;">Facultad de Ciencias Jurídicas y Políticas - UNAP</div>
                </div>
            </span>
        </div>
    </nav>

    <div class="bg-white border-bottom shadow-sm mb-5">
        <div class="container py-4">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="text-muted text-uppercase small fw-bold mb-1">Expediente Académico</h5>
                    <h2 class="fw-bold text-dark mb-3"><?= htmlspecialchars($nombre_curso_mostrar) ?></h2>
                    <p class="mb-1"><i class="fas fa-chalkboard-teacher text-primary me-2"></i> <strong>Docente:</strong> <?= htmlspecialchars($docente_mostrar) ?></p>
                    <p class="mb-0"><i class="fas fa-user-graduate text-success me-2"></i> <strong>Alcance:</strong> <?= htmlspecialchars($nombre_estudiante) ?></p>
                </div>
                <div class="col-md-4 text-md-end mt-3 mt-md-0">
                    <span class="badge bg-success fs-6 px-3 py-2"><i class="fas fa-check-circle me-1"></i> Documento Verificado</span>
                </div>
            </div>
        </div>
    </div>

    <main class="container mb-5">
        <?php if(empty($envios)): ?>
            <div class="text-center py-5">
                <i class="fas fa-folder-open fa-4x text-muted mb-3 opacity-50"></i>
                <h4 class="text-secondary">No hay trabajos públicos registrados.</h4>
            </div>
        <?php else: ?>
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <?php foreach($envios as $e): 
                        $autores = $e['lider_ape'] . ' ' . $e['lider_nom'];
                        if ($e['tipo_trabajo'] === 'Grupal') {
                            $stmt_int = $pdo->prepare("SELECT u.nombres, u.apellidos FROM envio_integrantes ei JOIN usuarios u ON ei.estudiante_id = u.id WHERE ei.envio_id = ? ORDER BY u.apellidos");
                            $stmt_int->execute([$e['id']]);
                            $integrantes = $stmt_int->fetchAll(PDO::FETCH_ASSOC);
                            $lista_int = [];
                            foreach ($integrantes as $int) { $lista_int[] = $int['apellidos'] . ' ' . $int['nombres']; }
                            if (!empty($lista_int)) { $autores = implode(" &bull; ", $lista_int); }
                        }
                    ?>
                        <div class="card shadow-sm border-0 mb-4 rounded-4 overflow-hidden">
                            <div class="card-header bg-light border-bottom p-4">
                                <h4 class="fw-bold text-dark mb-2"><?= htmlspecialchars($e['titulo_caso']) ?></h4>
                                <?php if($curso_id === 'todos'): ?><div class="text-primary fw-bold small mb-2"><i class="fas fa-book me-1"></i> <?= htmlspecialchars($e['nombre_curso']) ?></div><?php endif; ?>
                                <div class="text-muted small"><i class="fas fa-users me-1"></i> <strong>Autores:</strong> <?= $autores ?></div>
                                <div class="text-muted small mt-1"><i class="far fa-calendar-alt me-1"></i> <strong>Fecha de Envío:</strong> <?= date('d M Y', strtotime($e['fecha_envio'])) ?></div>
                            </div>
                            <div class="card-body p-4 respuesta-box bg-white">
                                <?php 
                                if (!empty($e['respuestas_json'])) {
                                    $respuestas = json_decode($e['respuestas_json'], true);
                                    if(is_array($respuestas)) {
                                        foreach($respuestas as $pregunta => $respuesta) {
                                            echo "<h6 class='fw-bold text-primary mt-4 mb-2 text-uppercase'>" . htmlspecialchars($pregunta) . "</h6>";
                                            echo "<p>" . nl2br(htmlspecialchars($respuesta)) . "</p>";
                                        }
                                    }
                                } elseif (!empty($e['factum']) || !empty($e['tipicidad'])) {
                                    $legacy = ['Factum' => $e['factum'], 'Dogmática' => $e['dogmatica'], 'Tipicidad' => $e['tipicidad'], 'Fallo' => $e['fallo']];
                                    foreach($legacy as $titulo => $texto) {
                                        if(!empty($texto)) {
                                            echo "<h6 class='fw-bold text-primary mt-4 mb-2 text-uppercase'>" . htmlspecialchars($titulo) . "</h6>";
                                            echo "<p>" . nl2br(htmlspecialchars($texto)) . "</p>";
                                        }
                                    }
                                } else { echo "<p class='fst-italic text-center text-muted'>Resolución complementaria en formato físico/PDF.</p>"; }
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <footer class="bg-white border-top mt-auto pt-4 pb-3">
        <div class="container">
            <p class="text-muted small mb-3" style="font-size: 0.75rem; line-height: 1.5; text-align: justify;">
                <i class="fas fa-balance-scale me-1"></i> <strong>Descargo de Responsabilidad:</strong> Los contenidos, opiniones, expresiones y archivos alojados en los expedientes y portafolios digitales son de exclusiva responsabilidad de los estudiantes autores de los mismos. La Facultad de Ciencias Jurídicas y Políticas, la Universidad Nacional del Altiplano (UNAP) y el equipo docente no suscriben, avalan, ni se hacen responsables necesariamente de dichas posturas, siendo este un espacio estrictamente de ejercicio académico.
            </p>
            <hr class="text-black-50">
            <div class="text-center">
                <small class="text-muted fw-bold">&copy; <?= date('Y') ?> Portafolio Digital | FCJP - Universidad Nacional del Altiplano</small>
            </div>
        </div>
    </footer>
</body>
</html>