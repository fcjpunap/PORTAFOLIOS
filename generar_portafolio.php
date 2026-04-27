<?php
// generar_portafolio.php - Expediente HTML para PDF (Soporta filtrado por Curso o Global)
session_set_cookie_params(14400);
ini_set("session.gc_maxlifetime", 14400);
session_start();
require_once 'config/conexion.php';

$curso_id = $_GET['curso_id'] ?? null; // Puede ser un ID numérico o la palabra "todos"
$estudiante_id = $_GET['estudiante_id'] ?? null;
$docente_id = $_SESSION['usuario_id'] ?? null;

if (!$curso_id) die("Curso no especificado.");

$titulo_documento = "Portafolio Académico";
$nombre_estudiante = "Todos los estudiantes";
$nombre_curso_imprimir = "";
$nombre_docente_imprimir = "Varios Docentes";

// Si es un curso específico
if ($curso_id !== 'todos') {
    $stmt_curso = $pdo->prepare("SELECT c.nombre_curso, u.nombres as doc_nom, u.apellidos as doc_ape FROM cursos c JOIN usuarios u ON c.docente_id = u.id WHERE c.id = ?");
    $stmt_curso->execute([$curso_id]);
    if ($curso = $stmt_curso->fetch(PDO::FETCH_ASSOC)) {
        $nombre_curso_imprimir = $curso['nombre_curso'];
        $nombre_docente_imprimir = $curso['doc_ape'] . ', ' . $curso['doc_nom'];
    }
} else {
    $nombre_curso_imprimir = "Consolidado Global (Múltiples Asignaturas)";
    // Sacar el nombre del docente logueado
    $stmt_doc = $pdo->prepare("SELECT nombres, apellidos FROM usuarios WHERE id = ?");
    $stmt_doc->execute([$docente_id]);
    if ($doc = $stmt_doc->fetch(PDO::FETCH_ASSOC)) $nombre_docente_imprimir = $doc['apellidos'] . ', ' . $doc['nombres'];
}

// LÓGICA DE CONSULTA DE ENVÍOS
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

// Filtrar por curso si no es global
if ($curso_id !== 'todos') {
    $sql_envios .= " AND a.curso_id = ?";
    $params[] = $curso_id;
} elseif ($docente_id && $_SESSION['rol'] === 'docente') {
    // Si es global, mostrar solo los cursos de este docente
    $sql_envios .= " AND c.docente_id = ?";
    $params[] = $docente_id;
}

// Filtrar por estudiante
if ($estudiante_id) {
    $stmt_est = $pdo->prepare("SELECT nombres, apellidos, codigo_estudiante FROM usuarios WHERE id = ?");
    $stmt_est->execute([$estudiante_id]);
    if ($est = $stmt_est->fetch(PDO::FETCH_ASSOC)) {
        $nombre_estudiante = $est['apellidos'] . " " . $est['nombres'] . " (" . $est['codigo_estudiante'] . ")";
    }
    $titulo_documento = ($curso_id === 'todos') ? "Expediente Global del Estudiante" : "Expediente del Estudiante";
    
    $sql_envios .= " AND e.id IN (SELECT envio_id FROM envio_integrantes WHERE estudiante_id = ?)";
    $params[] = $estudiante_id;
}

$sql_envios .= " ORDER BY c.nombre_curso ASC, a.fecha_cierre ASC";
$stmt_envios = $pdo->prepare($sql_envios);
$stmt_envios->execute($params);
$envios = $stmt_envios->fetchAll(PDO::FETCH_ASSOC);

// Generar URL pública
$protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$base_url = $protocolo . "://" . $_SERVER['HTTP_HOST'] . "/portafolios/visor_publico.php"; 
$url_publica = $base_url . "?curso_id=" . $curso_id;
if ($estudiante_id) { $url_publica .= "&estudiante_id=" . $estudiante_id; }
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=" . urlencode($url_publica);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= $titulo_documento ?> | UNAP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; color: #333; font-family: 'Times New Roman', Times, serif; }
        .page { background: white; margin: 20px auto; padding: 40px 60px; max-width: 900px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .portada { display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; height: 950px; }
        .logo-unap { width: 140px; margin-bottom: 25px; }
        .title-main { font-size: 2.2rem; font-weight: bold; color: #0b2e59; margin-bottom: 10px; text-transform: uppercase; }
        .subtitle { font-size: 1.4rem; color: #555; margin-bottom: 40px; }
        .datos-curso { font-size: 1.2rem; margin-bottom: 40px; width: 100%; text-align: left; padding: 30px; border: 2px solid #0b2e59; border-radius: 10px; }
        .qr-container { margin-top: auto; margin-bottom: 30px; text-align: center; }
        .qr-code { width: 160px; height: 160px; border: 5px solid white; box-shadow: 0 0 5px rgba(0,0,0,0.2); }
        
        .caso-header { border-bottom: 3px solid #0b2e59; padding-bottom: 10px; margin-bottom: 20px; page-break-after: avoid; }
        .caso-body { margin-bottom: 40px; }
        .apartado-title { font-weight: bold; color: #0b2e59; font-size: 1.1rem; margin-top: 15px; margin-bottom: 5px; }
        .apartado-content { text-align: justify; line-height: 1.6; font-size: 1rem; margin-bottom: 15px; }
        
        @media print {
            body { background: white; font-size: 12pt; }
            .page { margin: 0; padding: 0; box-shadow: none; max-width: 100%; }
            .no-print { display: none !important; }
            .page-break { page-break-before: always; }
            .portada { height: 100vh; }
        }
    </style>
</head>
<body>
    <div class="text-center my-4 no-print">
        <button onclick="window.print()" class="btn btn-danger btn-lg fw-bold shadow">
            <i class="fas fa-file-pdf me-2"></i> Imprimir a PDF
        </button>
    </div>

    <div class="page">
        <div class="portada">
            <svg class="logo-unap" viewBox="0 0 100 100" fill="#0b2e59" xmlns="http://www.w3.org/2000/svg">
                <circle cx="50" cy="50" r="45" stroke="#0b2e59" stroke-width="5" fill="none"/>
                <path d="M30 70 L50 30 L70 70 Z" fill="#0b2e59"/>
            </svg>
            <h1 class="title-main">Universidad Nacional del Altiplano</h1>
            <h2 class="subtitle">Facultad de Ciencias Jurídicas y Políticas</h2>
            
            <div class="datos-curso mt-4">
                <p><strong>Documento:</strong> <?= $titulo_documento ?></p>
                <p><strong>Asignatura:</strong> <?= htmlspecialchars($nombre_curso_imprimir) ?></p>
                <p><strong>Docente:</strong> <?= htmlspecialchars($nombre_docente_imprimir) ?></p>
                <?php if ($estudiante_id): ?>
                    <p><strong>Estudiante:</strong> <?= htmlspecialchars($nombre_estudiante) ?></p>
                <?php else: ?>
                    <p><strong>Alcance:</strong> Consolidado Global de Casos</p>
                <?php endif; ?>
                <p><strong>Año Académico:</strong> <?= date('Y') ?></p>
            </div>

            <div class="qr-container">
                <img src="<?= $qr_url ?>" alt="QR Público" class="qr-code">
                <p class="small text-muted mt-2 fw-bold">Escanee para verificación pública</p>
                <a href="<?= $url_publica ?>" target="_blank" class="small text-decoration-none no-print"><?= $url_publica ?></a>
            </div>
        </div>
    </div>

    <?php if (empty($envios)): ?>
        <div class="page page-break text-center py-5"><h3>No hay trabajos registrados.</h3></div>
    <?php else: ?>
        <?php foreach ($envios as $index => $e): 
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
            <div class="page <?= $index === 0 ? 'page-break' : '' ?>">
                <div class="caso-header">
                    <h3 class="fw-bold text-dark mb-1"><?= htmlspecialchars($e['titulo_caso']) ?></h3>
                    <?php if($curso_id === 'todos'): ?>
                        <div class="text-primary small mb-2"><i class="fas fa-book me-1"></i> Curso: <?= htmlspecialchars($e['nombre_curso']) ?></div>
                    <?php endif; ?>
                    <div class="text-muted small mb-1"><strong>Autor(es):</strong> <?= htmlspecialchars($autores) ?></div>
                    <div class="text-muted small"><strong>Fecha de Registro:</strong> <?= date('d/m/Y', strtotime($e['fecha_envio'])) ?></div>
                </div>

                <div class="caso-body">
                    <?php 
                    if (!empty($e['respuestas_json'])) {
                        $respuestas = json_decode($e['respuestas_json'], true);
                        if(is_array($respuestas)) {
                            foreach($respuestas as $pregunta => $respuesta) {
                                echo "<div class='apartado-title'>" . htmlspecialchars($pregunta) . "</div>";
                                echo "<div class='apartado-content'>" . nl2br(htmlspecialchars($respuesta)) . "</div>";
                            }
                        }
                    } elseif (!empty($e['factum']) || !empty($e['tipicidad'])) {
                        $legacy = ['Factum' => $e['factum'], 'Dogmática' => $e['dogmatica'], 'Tipicidad' => $e['tipicidad'], 'Fallo' => $e['fallo']];
                        foreach($legacy as $titulo => $texto) {
                            if(!empty($texto)) {
                                echo "<div class='apartado-title'>" . htmlspecialchars($titulo) . "</div>";
                                echo "<div class='apartado-content'>" . nl2br(htmlspecialchars($texto)) . "</div>";
                            }
                        }
                    } else {
                        echo "<p class='text-muted fst-italic'>Resolución adjunta en PDF físico.</p>";
                    }
                    ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
</body>
</html>