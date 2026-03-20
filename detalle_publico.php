<?php
// detalle_publico.php - Vista pública de un caso resuelto con metadatos y renderizado dinámico
session_start();
require_once 'config/conexion.php';

$envio_id = $_GET['id'] ?? null;

if (!$envio_id) {
    die("ID de caso no especificado.");
}

// Consultar datos del envío, actividad, curso, SEMESTRE, docente y estudiantes
$sql = "
    SELECT e.factum, e.tipicidad, e.dogmatica, e.jurisprudencia, e.fallo, e.fecha_envio, e.respuestas_json,
           a.titulo_caso, c.nombre_curso, c.semestre,
           d.nombres as docente_nombres, d.apellidos as docente_apellidos,
           (SELECT GROUP_CONCAT(CONCAT(u.nombres, ' ', u.apellidos) SEPARATOR ', ')
            FROM envio_integrantes ei
            JOIN usuarios u ON ei.estudiante_id = u.id
            WHERE ei.envio_id = e.id) as estudiantes_autores
    FROM envios_fichas e
    JOIN actividades_fichas a ON e.actividad_id = a.id
    JOIN cursos c ON a.curso_id = c.id
    JOIN usuarios d ON c.docente_id = d.id 
    WHERE e.id = ? AND e.estado = 'Revisado'
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$envio_id]);
$caso = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$caso) {
    die("El caso solicitado no existe o aún no ha sido aprobado para su publicación.");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($caso['titulo_caso']) ?> | Portafolio UNAP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f7f6; display: flex; flex-direction: column; min-height: 100vh; }
        main { flex: 1; }
        .bg-unap { background-color: #0b2e59; }
        .text-unap { color: #0b2e59; }
        .section-title { border-bottom: 2px solid #ffc107; padding-bottom: 5px; margin-bottom: 15px; color: #0b2e59; }
        .content-box { background: white; border-radius: 8px; padding: 30px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .texto-academico { text-align: justify; font-family: 'Times New Roman', Times, serif; font-size: 1.15rem; line-height: 1.6; }
        .metadata-box { background-color: #fff; border-radius: 10px; border: 1px solid #eee !important; max-width: 650px; margin: 0 auto; }
    </style>
</head>
<body>

    <nav class="navbar navbar-dark bg-unap shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="fas fa-arrow-left me-2"></i> Volver al Portafolio</a>
        </div>
    </nav>

    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                
                <div class="text-center mb-5">
                    <span class="badge bg-primary px-3 py-2 rounded-pill mb-3 fs-6"><?= htmlspecialchars($caso['nombre_curso']) ?></span>
                    <h1 class="fw-bold text-unap mb-3 display-6"><?= htmlspecialchars($caso['titulo_caso']) ?></h1>
                    
                    <div class="card metadata-box shadow-sm d-inline-block p-4 mt-2 text-start w-100">
                        <div class="row">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <strong class="d-block text-secondary mb-1"><i class="fas fa-users me-1"></i> Autores:</strong>
                                <span class="text-dark"><?= htmlspecialchars($caso['estudiantes_autores'] ?? 'No especificados') ?></span>
                            </div>
                            <div class="col-md-6">
                                <strong class="d-block text-secondary mb-1"><i class="fas fa-chalkboard-teacher me-1"></i> Docente a cargo:</strong>
                                <span class="text-dark">Prof. <?= htmlspecialchars($caso['docente_nombres'] . ' ' . $caso['docente_apellidos']) ?></span>
                            </div>
                        </div>
                        <hr class="my-3 opacity-25">
                        <div class="row">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <strong class="d-block text-secondary mb-1"><i class="fas fa-calendar-alt me-1"></i> Semestre:</strong>
                                <span class="text-dark"><?= htmlspecialchars($caso['semestre'] ?? 'No especificado') ?></span>
                            </div>
                            <div class="col-md-6">
                                <strong class="d-block text-secondary mb-1"><i class="fas fa-clock me-1"></i> Fecha de publicación:</strong>
                                <span class="text-dark"><?= date('d/m/Y', strtotime($caso['fecha_envio'])) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="respuestas-container">
                    <?php 
                    // 1. Si existe el JSON multidisciplinario
                    if (!empty($caso['respuestas_json'])): 
                        $respuestas = json_decode($caso['respuestas_json'], true);
                        $contador = 1;
                        
                        // Array de colores para darle un toque visual agradable a cada apartado
                        $colores = ['primary', 'success', 'info', 'secondary', 'danger', 'warning', 'dark'];
                        
                        foreach ($respuestas as $titulo => $contenido):
                            $color_actual = $colores[($contador - 1) % count($colores)];
                    ?>
                        <div class="content-box border-start border-4 border-<?= $color_actual ?>">
                            <h4 class="section-title text-<?= $color_actual ?> border-<?= $color_actual ?>"><i class="fas fa-bookmark me-2"></i><?= $contador ?>. <?= htmlspecialchars($titulo) ?></h4>
                            <p class="texto-academico text-dark"><?= nl2br(htmlspecialchars($contenido)) ?></p>
                        </div>
                    <?php 
                        $contador++;
                        endforeach; 

                    // 2. Fallback para los casos clásicos de Derecho Penal
                    else: 
                    ?>
                        <div class="content-box border-start border-4 border-primary">
                            <h4 class="section-title"><i class="fas fa-list-ul text-warning"></i> 1. Factum (Hechos)</h4>
                            <p class="texto-academico"><?= nl2br(htmlspecialchars($caso['factum'])) ?></p>
                        </div>

                        <div class="content-box border-start border-4 border-success">
                            <h4 class="section-title"><i class="fas fa-balance-scale text-warning"></i> 2. Juicio de Tipicidad</h4>
                            <p class="texto-academico"><?= nl2br(htmlspecialchars($caso['tipicidad'])) ?></p>
                        </div>

                        <div class="content-box border-start border-4 border-info">
                            <h4 class="section-title"><i class="fas fa-book text-warning"></i> 3. Análisis Dogmático</h4>
                            <p class="texto-academico"><?= nl2br(htmlspecialchars($caso['dogmatica'])) ?></p>
                        </div>

                        <?php if(!empty($caso['jurisprudencia'])): ?>
                        <div class="content-box border-start border-4 border-secondary">
                            <h4 class="section-title"><i class="fas fa-gavel text-warning"></i> 4. Jurisprudencia Aplicada</h4>
                            <p class="texto-academico"><?= nl2br(htmlspecialchars($caso['jurisprudencia'])) ?></p>
                        </div>
                        <?php endif; ?>

                        <div class="content-box border-start border-4 border-danger">
                            <h4 class="section-title"><i class="fas fa-flag-checkered text-warning"></i> 5. Fallo / Conclusión</h4>
                            <p class="texto-academico fw-bold"><?= nl2br(htmlspecialchars($caso['fallo'])) ?></p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </main>

    <footer class="bg-white py-4 border-top mt-auto shadow-sm">
        <div class="container text-center text-muted small">
            <p class="mb-0">&copy; <?= date('Y') ?> Portafolio Digital UNAP. Facultad de Ciencias Jurídicas y Políticas.</p>
        </div>
    </footer>

</body>
</html>