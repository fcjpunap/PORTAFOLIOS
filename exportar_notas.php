<?php
// exportar_notas.php - Selector y Generador de Excel/CSV de Notas
session_start();
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['docente', 'admin'])) {
    header('Location: login.php');
    exit;
}
require_once 'config/conexion.php';

$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];
$es_admin = ($rol === 'admin');

// Si viene la orden de descargar el CSV:
if (isset($_GET['descargar']) && !empty($_GET['curso_id'])) {
    $curso_id = $_GET['curso_id'];

    // Validar propiedad del curso (si no es admin)
    if (!$es_admin) {
        $check = $pdo->prepare("SELECT id FROM cursos WHERE id = ? AND docente_id = ?");
        $check->execute([$curso_id, $usuario_id]);
        if (!$check->fetch()) die("Acceso denegado a este curso.");
    }

    $stmtC = $pdo->prepare("SELECT nombre_curso FROM cursos WHERE id = ?");
    $stmtC->execute([$curso_id]);
    $nombre_curso = $stmtC->fetchColumn();

    $filename = "Notas_" . preg_replace('/[^a-zA-Z0-9]/', '_', $nombre_curso) . "_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $salida = fopen('php://output', 'w');
    fprintf($salida, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8 para Excel

    // Cabeceras: Obtener actividades del curso
    $stmtA = $pdo->prepare("SELECT id, titulo_caso, tipo_trabajo FROM actividades_fichas WHERE curso_id = ? ORDER BY id ASC");
    $stmtA->execute([$curso_id]);
    $actividades = $stmtA->fetchAll(PDO::FETCH_ASSOC);

    $cabecera = ['Código', 'Apellidos y Nombres'];
    foreach ($actividades as $act) {
        $cabecera[] = $act['titulo_caso'] . " (" . $act['tipo_trabajo'] . ")";
    }
    $cabecera[] = 'Promedio Final';
    fputcsv($salida, $cabecera);

    // Obtener todos los alumnos del curso
    $stmtU = $pdo->prepare("
        SELECT u.id, u.codigo_estudiante, u.apellidos, u.nombres
        FROM matriculas m
        JOIN usuarios u ON m.estudiante_id = u.id
        WHERE m.curso_id = ?
        ORDER BY u.apellidos ASC, u.nombres ASC
    ");
    $stmtU->execute([$curso_id]);
    $alumnos = $stmtU->fetchAll(PDO::FETCH_ASSOC);

    foreach ($alumnos as $alumno) {
        $fila = [$alumno['codigo_estudiante'] ?? 'S/C', $alumno['apellidos'] . ' ' . $alumno['nombres']];
        $suma_notas = 0; $act_evaluadas = 0;

        foreach ($actividades as $act) {
            // Buscar nota (si es líder o si fue agregado al grupo)
            $stmtN = $pdo->prepare("
                SELECT e.calificacion 
                FROM envios_fichas e
                LEFT JOIN envio_integrantes ei ON e.id = ei.envio_id
                WHERE e.actividad_id = ? AND (e.lider_id = ? OR ei.estudiante_id = ?)
                LIMIT 1
            ");
            $stmtN->execute([$act['id'], $alumno['id'], $alumno['id']]);
            $nota = $stmtN->fetchColumn();
            
            if ($nota !== false && $nota !== null) {
                $fila[] = $nota;
                $suma_notas += $nota;
                $act_evaluadas++;
            } else {
                $fila[] = '0'; // Cero si no envió
                $act_evaluadas++;
            }
        }
        
        $promedio = $act_evaluadas > 0 ? round($suma_notas / $act_evaluadas, 2) : 0;
        $fila[] = $promedio;
        fputcsv($salida, $fila);
    }
    fclose($salida);
    exit;
}

// ==========================================
// VISTA GRÁFICA: SELECTOR DE CURSOS
// ==========================================
$cursos = [];
if ($es_admin) {
    $cursos = $pdo->query("SELECT id, nombre_curso FROM cursos ORDER BY nombre_curso ASC")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare("SELECT id, nombre_curso FROM cursos WHERE docente_id = ?");
    $stmt->execute([$usuario_id]);
    $cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Exportar Notas | UNAP</title>
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
    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow border-0 border-top border-4 border-warning">
                    <div class="card-body p-4 text-center">
                        <i class="fas fa-file-csv fa-3x text-warning mb-3"></i>
                        <h4 class="fw-bold text-secondary mb-3">Descargar Registro de Notas</h4>
                        <p class="text-muted small mb-4">Selecciona el curso del cual deseas descargar el acta de calificaciones. El sistema calculará el promedio automáticamente cruzando los trabajos individuales y grupales.</p>
                        
                        <form action="exportar_notas.php" method="GET">
                            <input type="hidden" name="descargar" value="1">
                            <div class="mb-4 text-start">
                                <label class="fw-bold text-secondary mb-2">Asignatura</label>
                                <select class="form-select form-select-lg" name="curso_id" required>
                                    <option value="">-- Elige un curso --</option>
                                    <?php foreach($cursos as $c): ?>
                                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre_curso']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-warning btn-lg w-100 fw-bold shadow-sm"><i class="fas fa-download me-2"></i> Generar Excel</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>