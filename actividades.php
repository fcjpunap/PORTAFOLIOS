<?php
// actividades.php - Panel Gestor Completo de Casos (Listar, Buscar, Editar, Duplicar, Eliminar y Revisar)
session_start();
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'docente') { header('Location: login.php'); exit; }
require_once 'config/conexion.php';

$docente_id = $_SESSION['usuario_id'];
$mensaje = ''; $tipo_mensaje = '';

// Procesar Acciones (Crear, Editar, Duplicar, Eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    try {
        if ($_POST['accion'] === 'crear' || $_POST['accion'] === 'editar') {
            $curso_id = $_POST['curso_id'];
            $titulo = trim($_POST['titulo_caso']);
            $descripcion = trim($_POST['descripcion']);
            $fecha_cierre = $_POST['fecha_cierre'] ?: date('Y-m-d H:i:s', strtotime('+7 days'));
            $tipo_trabajo = $_POST['tipo_trabajo'];
            $max_integrantes = ($tipo_trabajo === 'Grupal') ? intval($_POST['max_integrantes']) : 1;
            $habilitado = isset($_POST['habilitado']) ? 1 : 0;
            
            // Procesar apartados
            $titulos = $_POST['sec_titulo'] ?? [];
            $guias = $_POST['sec_guia'] ?? [];
            $secciones = [];
            for ($i = 0; $i < count($titulos); $i++) {
                if (trim($titulos[$i]) !== '') {
                    $secciones[] = ['titulo' => trim($titulos[$i]), 'guia' => trim($guias[$i] ?? '')];
                }
            }
            if (empty($secciones)) $secciones[] = ['titulo' => 'Desarrollo General', 'guia' => 'Escriba aquí'];
            $secciones_json = json_encode($secciones, JSON_UNESCAPED_UNICODE);

            if ($_POST['accion'] === 'crear') {
                $stmt = $pdo->prepare("INSERT INTO actividades_fichas (curso_id, titulo_caso, descripcion, fecha_cierre, fecha_limite, tipo_trabajo, max_integrantes, secciones_json, habilitado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$curso_id, $titulo, $descripcion, $fecha_cierre, $fecha_cierre, $tipo_trabajo, $max_integrantes, $secciones_json, $habilitado]);
                $mensaje = "Actividad creada exitosamente."; $tipo_mensaje = "success";
            } else {
                $id_editar = $_POST['actividad_id'];
                $stmt = $pdo->prepare("UPDATE actividades_fichas SET titulo_caso=?, descripcion=?, fecha_cierre=?, fecha_limite=?, tipo_trabajo=?, max_integrantes=?, secciones_json=?, habilitado=? WHERE id=?");
                $stmt->execute([$titulo, $descripcion, $fecha_cierre, $fecha_cierre, $tipo_trabajo, $max_integrantes, $secciones_json, $habilitado, $id_editar]);
                $mensaje = "Actividad actualizada."; $tipo_mensaje = "info";
            }
        } elseif ($_POST['accion'] === 'eliminar') {
            $pdo->prepare("DELETE FROM actividades_fichas WHERE id = ?")->execute([$_POST['actividad_id']]);
            $mensaje = "Actividad eliminada."; $tipo_mensaje = "warning";
            
        } elseif ($_POST['accion'] === 'duplicar') {
            $id_orig = $_POST['actividad_id'];
            $stmt = $pdo->prepare("INSERT INTO actividades_fichas (curso_id, titulo_caso, descripcion, fecha_cierre, fecha_limite, tipo_trabajo, max_integrantes, secciones_json, habilitado) SELECT curso_id, CONCAT(titulo_caso, ' (Copia)'), descripcion, fecha_cierre, fecha_limite, tipo_trabajo, max_integrantes, secciones_json, 0 FROM actividades_fichas WHERE id = ?");
            $stmt->execute([$id_orig]);
            $mensaje = "Caso duplicado con éxito. (Oculto por defecto)"; $tipo_mensaje = "success";
        } elseif ($_POST['accion'] === 'toggle') {
            $pdo->prepare("UPDATE actividades_fichas SET habilitado = NOT habilitado WHERE id = ?")->execute([$_POST['actividad_id']]);
            $mensaje = "Visibilidad del caso actualizada."; $tipo_mensaje = "info";
        }
    } catch (PDOException $e) { $mensaje = "Error DB: " . $e->getMessage(); $tipo_mensaje = "danger"; }
}

// Cargar Cursos
$cursos = $pdo->prepare("SELECT id, nombre_curso FROM cursos WHERE docente_id = ?");
$cursos->execute([$docente_id]);
$mis_cursos = $cursos->fetchAll(PDO::FETCH_ASSOC);
$ids_cursos = array_column($mis_cursos, 'id');

// Listar, Buscar y Paginar
$search = $_GET['buscar'] ?? '';
$pagina = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina < 1) $pagina = 1;
$limite = 8; $offset = ($pagina - 1) * $limite;

$whereSql = ""; $params = [];
if (!empty($ids_cursos)) {
    $placeholders = str_repeat('?,', count($ids_cursos) - 1) . '?';
    $whereSql = " WHERE a.curso_id IN ($placeholders)";
    $params = $ids_cursos;
    
    if ($search !== '') {
        $whereSql .= " AND (a.titulo_caso LIKE ? OR c.nombre_curso LIKE ?)";
        $params[] = "%$search%"; $params[] = "%$search%";
    }

    $stmt_total = $pdo->prepare("SELECT COUNT(*) FROM actividades_fichas a JOIN cursos c ON a.curso_id = c.id $whereSql");
    $stmt_total->execute($params);
    $total_act = $stmt_total->fetchColumn();
    $total_paginas = ceil($total_act / $limite);

    $sql = "SELECT a.*, c.nombre_curso FROM actividades_fichas a JOIN cursos c ON a.curso_id = c.id $whereSql ORDER BY a.id DESC LIMIT $limite OFFSET $offset";
    $stmt_act = $pdo->prepare($sql);
    $stmt_act->execute($params);
    $actividades = $stmt_act->fetchAll(PDO::FETCH_ASSOC);
} else {
    $actividades = []; $total_paginas = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestionar Casos | Docente UNAP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> body { background-color: #f8f9fa; } .bg-unap { background-color: #0b2e59; } </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-unap shadow-sm">
        <div class="container"><a class="navbar-brand" href="dashboard_docente.php"><i class="fas fa-arrow-left me-2"></i> Volver a mi panel</a></div>
    </nav>
    <main class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h3 class="text-secondary mb-0"><i class="fas fa-folder-open text-primary me-2"></i> Gestión de Casos</h3>
            <button class="btn btn-success fw-bold shadow-sm" onclick="abrirModalCrear()"><i class="fas fa-plus me-2"></i> Crear Nuevo Caso</button>
        </div>
        
        <?php if ($mensaje): ?><div class="alert alert-<?= $tipo_mensaje ?> shadow-sm"><i class="fas fa-info-circle me-1"></i> <?= $mensaje ?></div><?php endif; ?>

        <div class="card shadow-sm border-0 border-top border-4 border-primary">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-secondary">Mis Actividades Publicadas</h5>
                <form action="actividades.php" method="GET" class="d-flex" style="max-width: 350px;">
                    <input type="text" name="buscar" class="form-control me-2" placeholder="Buscar por título o curso..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i></button>
                    <?php if ($search): ?><a href="actividades.php" class="btn btn-outline-secondary ms-2"><i class="fas fa-times"></i></a><?php endif; ?>
                </form>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th class="ps-3">Actividad</th><th>Curso</th><th>Tipo</th><th>Vence</th><th class="text-center">Estado</th><th class="text-center">Acciones</th></tr></thead>
                    <tbody>
                        <?php if(empty($actividades)): ?><tr><td colspan="5" class="text-center py-4 text-muted">No tienes actividades publicadas.</td></tr><?php else: ?>
                            <?php foreach($actividades as $a): ?>
                            <tr>
                                <td class="ps-3 fw-bold text-dark"><?= htmlspecialchars($a['titulo_caso']) ?></td>
                                <td class="small"><?= htmlspecialchars($a['nombre_curso']) ?></td>
                                <td><span class="badge bg-<?= $a['tipo_trabajo']=='Grupal'?'info':'secondary' ?>"><?= $a['tipo_trabajo'] ?></span></td>
                                <td class="small text-muted"><i class="far fa-calendar-alt me-1"></i> <?= date('d/m/Y H:i', strtotime($a['fecha_cierre'])) ?></td>
                                <td class="text-center">
                                    <form action="" method="POST" style="display:inline;">
                                        <input type="hidden" name="accion" value="toggle">
                                        <input type="hidden" name="actividad_id" value="<?= $a['id'] ?>">
                                        <button type="submit" class="btn btn-sm <?= $a['habilitado'] ? 'btn-success' : 'btn-secondary' ?>" title="Cambiar visibilidad">
                                            <i class="fas <?= $a['habilitado'] ? 'fa-eye' : 'fa-eye-slash' ?>"></i> <?= $a['habilitado'] ? 'Visible' : 'Oculto' ?>
                                        </button>
                                    </form>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group shadow-sm">
                                        <a href="revisar_trabajos.php?id=<?= $a['id'] ?>" class="btn btn-sm btn-dark" title="Revisar Envíos de Alumnos">
                                            <i class="fas fa-folder-open"></i>
                                        </a>

                                        <button class="btn btn-sm btn-outline-primary" onclick="abrirModalEditar(<?= htmlspecialchars(json_encode($a)) ?>)" title="Editar caso"><i class="fas fa-edit"></i></button>
                                        <form action="" method="POST" style="display:inline;"><input type="hidden" name="accion" value="duplicar"><input type="hidden" name="actividad_id" value="<?= $a['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-success" title="Duplicar"><i class="fas fa-copy"></i></button></form>
                                        <form action="" method="POST" onsubmit="return confirm('¿Eliminar caso? Se borrarán los envíos de los alumnos.');" style="display:inline;"><input type="hidden" name="accion" value="eliminar"><input type="hidden" name="actividad_id" value="<?= $a['id'] ?>"><button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="fas fa-trash"></i></button></form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($total_paginas > 1): ?>
                <div class="card-footer bg-white py-3"><ul class="pagination pagination-sm justify-content-center mb-0"><?php for ($i=1; $i<=$total_paginas; $i++): ?><li class="page-item <?= ($i==$pagina)?'active':'' ?>"><a class="page-link" href="?buscar=<?= urlencode($search) ?>&pagina=<?= $i ?>"><?= $i ?></a></li><?php endfor; ?></ul></div>
            <?php endif; ?>
        </div>
    </main>

    <div class="modal fade" id="modalActividad" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <form action="actividades.php" method="POST" id="formActividad">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold" id="modalTitulo"><i class="fas fa-edit me-2"></i> Crear/Editar Caso</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <input type="hidden" name="accion" id="inputAccion" value="crear">
                        <input type="hidden" name="actividad_id" id="inputId">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3"><label class="fw-bold small text-secondary">Curso *</label><select name="curso_id" id="inputCurso" class="form-select" required><option value="">-- Selecciona --</option><?php foreach($mis_cursos as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre_curso']) ?></option><?php endforeach; ?></select></div>
                            <div class="col-md-6 mb-3"><label class="fw-bold small text-secondary">Título de la Actividad *</label><input type="text" name="titulo_caso" id="inputTitulo" class="form-control" required></div>
                        </div>
                        <div class="mb-3"><label class="fw-bold small text-secondary">Descripción y Lineamientos *</label><textarea name="descripcion" id="inputDesc" class="form-control" rows="3" required></textarea></div>
                        <div class="row mb-3">
                            <div class="col-md-4 mb-3"><label class="fw-bold small text-secondary">Fecha Cierre *</label><input type="datetime-local" name="fecha_cierre" id="inputFecha" class="form-control" required></div>
                            <div class="col-md-4 mb-3"><label class="fw-bold small text-secondary">Tipo</label><select name="tipo_trabajo" id="inputTipo" class="form-select" onchange="toggleInt()" required><option value="Grupal">Grupal</option><option value="Individual">Individual</option></select></div>
                            <div class="col-md-4 mb-3" id="divMaxInt"><label class="fw-bold small text-secondary">Máx Integrantes</label><input type="number" name="max_integrantes" id="inputMax" class="form-control" value="5" min="2" max="10"></div>
                        </div>
                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" name="habilitado" id="inputHabilitado" value="1">
                            <label class="form-check-label fw-bold small text-secondary" for="inputHabilitado">Visible para estudiantes (Habilitado)</label>
                        </div>

                        <div class="card bg-light border-0">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3"><h6 class="fw-bold text-dark mb-0"><i class="fas fa-list-ul text-primary me-2"></i> Estructura de la Ficha</h6><button type="button" class="btn btn-sm btn-outline-primary fw-bold" onclick="addSeccion()"><i class="fas fa-plus"></i> Añadir</button></div>
                                <div id="secciones_container"></div>
                            </div>
                        </div>
                    </div>
                        <div class="modal-footer bg-light"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary fw-bold px-4">Guardar Actividad</button></div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const modalUI = new bootstrap.Modal(document.getElementById('modalActividad'));
        function toggleInt() { document.getElementById("divMaxInt").style.display = (document.getElementById("inputTipo").value === 'Grupal') ? 'block' : 'none'; }
        
        function addSeccion(titulo = '', guia = '') {
            const container = document.getElementById('secciones_container');
            const div = document.createElement('div');
            div.className = 'row g-2 mb-2 pb-2 border-bottom';
            div.innerHTML = `<div class="col-md-4"><input type="text" name="sec_titulo[]" class="form-control fw-bold" placeholder="Título" value="${titulo}" required></div><div class="col-md-7"><input type="text" name="sec_guia[]" class="form-control" placeholder="Instrucción..." value="${guia}" required></div><div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100" onclick="this.parentElement.parentElement.remove()"><i class="fas fa-trash"></i></button></div>`;
            container.appendChild(div);
        }

        function abrirModalCrear() {
            document.getElementById('formActividad').reset();
            document.getElementById('inputAccion').value = 'crear';
            document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-plus-circle me-2"></i> Crear Nuevo Caso';
            document.getElementById('secciones_container').innerHTML = '';
            document.getElementById('inputHabilitado').checked = false;
            
            addSeccion('Factum', 'Resumen material de los hechos');
            addSeccion('Dogmática', 'Análisis de la teoría del delito');
            addSeccion('Tipicidad', 'Subsunción de la conducta al tipo penal');
            addSeccion('Fallo', 'Conclusión o sentencia sugerida');
            toggleInt(); modalUI.show();
        }

        function abrirModalEditar(act) {
            document.getElementById('formActividad').reset();
            document.getElementById('inputAccion').value = 'editar';
            document.getElementById('inputId').value = act.id;
            document.getElementById('modalTitulo').innerHTML = '<i class="fas fa-edit me-2"></i> Editar Caso';
            document.getElementById('inputCurso').value = act.curso_id;
            document.getElementById('inputTitulo').value = act.titulo_caso;
            document.getElementById('inputDesc').value = act.descripcion;
            document.getElementById('inputFecha').value = act.fecha_cierre ? act.fecha_cierre.replace(' ', 'T') : '';
            document.getElementById('inputTipo').value = act.tipo_trabajo;
            document.getElementById('inputMax').value = act.max_integrantes;
            document.getElementById('inputHabilitado').checked = (act.habilitado == 1);
            
            document.getElementById('secciones_container').innerHTML = '';
            let secciones = act.secciones_json ? JSON.parse(act.secciones_json) : [];
            if(secciones.length === 0) { addSeccion('Desarrollo', 'Escriba aquí'); } else { secciones.forEach(s => addSeccion(s.titulo, s.guia)); }
            toggleInt(); modalUI.show();
        }
    </script>
</body>
</html>