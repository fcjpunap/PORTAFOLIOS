<?php
// estudiantes.php - Gestión Académica de Estudiantes
session_set_cookie_params(14400);
ini_set("session.gc_maxlifetime", 14400);
session_start();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/conexion.php';

$mensaje = '';
$tipo_mensaje = '';

// --- 1. PROCESAR FORMULARIOS (CREAR, EDITAR, ELIMINAR) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    $id_estudiante = $_POST['id_estudiante'] ?? '';
    $nombres = $_POST['nombres'] ?? '';
    $apellidos = $_POST['apellidos'] ?? '';
    $email = $_POST['email'] ?? '';
    $codigo = $_POST['codigo_estudiante'] ?? '';
    $estado = $_POST['estado'] ?? 1;
    $password = $_POST['password'] ?? '';

    if ($accion === 'guardar') {
        if (empty($id_estudiante)) {
            // CREAR NUEVO ESTUDIANTE (Forzamos rol = 'estudiante')
            try {
                $hash = password_hash(empty($password) ? 'estudiante2026' : $password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO usuarios (nombres, apellidos, email, rol, codigo_estudiante, estado, password) VALUES (?, ?, ?, 'estudiante', ?, ?, ?)");
                $stmt->execute([$nombres, $apellidos, $email, $codigo, $estado, $hash]);
                $mensaje = "¡Estudiante matriculado exitosamente! (Contraseña por defecto: estudiante2026)";
                $tipo_mensaje = "success";
            } catch (PDOException $e) {
                $mensaje = "Error al registrar (¿El correo o código ya existe?): " . $e->getMessage();
                $tipo_mensaje = "danger";
            }
        } else {
            // EDITAR ESTUDIANTE EXISTENTE
            try {
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE usuarios SET nombres=?, apellidos=?, email=?, codigo_estudiante=?, estado=?, password=? WHERE id=? AND rol='estudiante'");
                    $stmt->execute([$nombres, $apellidos, $email, $codigo, $estado, $hash, $id_estudiante]);
                } else {
                    $stmt = $pdo->prepare("UPDATE usuarios SET nombres=?, apellidos=?, email=?, codigo_estudiante=?, estado=? WHERE id=? AND rol='estudiante'");
                    $stmt->execute([$nombres, $apellidos, $email, $codigo, $estado, $id_estudiante]);
                }
                $mensaje = "¡Datos del estudiante actualizados!";
                $tipo_mensaje = "success";
            } catch (PDOException $e) {
                $mensaje = "Error al actualizar: " . $e->getMessage();
                $tipo_mensaje = "danger";
            }
        }
    } elseif ($accion === 'eliminar') {
        // ELIMINAR ESTUDIANTE
        try {
            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ? AND rol = 'estudiante'");
            $stmt->execute([$id_estudiante]);
            $mensaje = "Estudiante eliminado correctamente.";
            $tipo_mensaje = "success";
        } catch (PDOException $e) {
            $mensaje = "No se puede eliminar a este estudiante porque tiene fichas entregadas. Se recomienda cambiar su estado a 'Inactivo'.";
            $tipo_mensaje = "warning";
        }
    }
}

// --- 2. OBTENER DATOS AVANZADOS PARA LA TABLA ---
// Esta consulta mágica une al estudiante con sus entregas y calcula su promedio de notas
$sql_estudiantes = "SELECT u.id, u.nombres, u.apellidos, u.codigo_estudiante, u.email, u.estado,
                    COUNT(DISTINCT ei.envio_id) as total_entregas,
                    AVG(ef.calificacion) as promedio
                    FROM usuarios u
                    LEFT JOIN envio_integrantes ei ON u.id = ei.estudiante_id
                    LEFT JOIN envios_fichas ef ON ei.envio_id = ef.id AND ef.estado = 'Revisado'
                    WHERE u.rol = 'estudiante'
                    GROUP BY u.id
                    ORDER BY u.apellidos ASC";
$lista_estudiantes = $pdo->query($sql_estudiantes)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Directorio de Estudiantes | Portafolios UNAP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
    <style>
        body { display: flex; flex-direction: column; min-height: 100vh; background-color: #f4f6f9; }
        main { flex: 1; }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php"><i class="fas fa-balance-scale text-warning"></i> Portafolios UNAP</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="cursos.php"><i class="fas fa-book"></i> Cursos</a></li>
                    <li class="nav-item"><a class="nav-link active" href="estudiantes.php"><i class="fas fa-user-graduate"></i> Estudiantes</a></li>
                    <li class="nav-item"><a class="nav-link" href="usuarios.php"><i class="fas fa-users-cog"></i> Usuarios</a></li>
                </ul>
                <div class="d-flex text-white align-items-center">
                    <span class="me-3"><i class="fas fa-user-tie"></i> <?= htmlspecialchars($_SESSION['nombre_completo'] ?? 'Docente') ?></span>
                    <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="fas fa-sign-out-alt"></i> Salir</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="container py-4">
        
        <?php if ($mensaje): ?>
            <div class="alert alert-<?= $tipo_mensaje ?> alert-dismissible fade show shadow-sm" role="alert">
                <?= htmlspecialchars($mensaje) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 text-secondary"><i class="fas fa-user-graduate"></i> Libreta y Directorio de Estudiantes</h2>
            <button class="btn btn-primary shadow-sm" onclick="abrirModalEstudiante()">
                <i class="fas fa-plus"></i> Matricular Estudiante
            </button>
        </div>

        <div class="card shadow">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaEstudiantes" class="table table-striped table-hover align-middle w-100">
                        <thead class="table-dark">
                            <tr>
                                <th>Código</th>
                                <th>Apellidos y Nombres</th>
                                <th>Email</th>
                                <th class="text-center">Fichas Entregadas</th>
                                <th class="text-center">Promedio</th>
                                <th>Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lista_estudiantes as $est): ?>
                            <tr>
                                <td class="fw-bold text-primary"><?= htmlspecialchars($est['codigo_estudiante'] ?? 'S/C') ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($est['apellidos'] . ', ' . $est['nombres']) ?></td>
                                <td><?= htmlspecialchars($est['email']) ?></td>
                                <td class="text-center">
                                    <span class="badge bg-secondary rounded-pill fs-6"><?= $est['total_entregas'] ?></span>
                                </td>
                                <td class="text-center fw-bold text-success">
                                    <?= $est['promedio'] !== null ? number_format($est['promedio'], 1) : '--' ?>
                                </td>
                                <td>
                                    <?= $est['estado'] ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-danger">Inactivo</span>' ?>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-warning" title="Editar" 
                                            onclick="abrirModalEstudiante(
                                                <?= $est['id'] ?>, 
                                                '<?= htmlspecialchars($est['nombres'], ENT_QUOTES) ?>', 
                                                '<?= htmlspecialchars($est['apellidos'], ENT_QUOTES) ?>',
                                                '<?= htmlspecialchars($est['email'], ENT_QUOTES) ?>',
                                                '<?= htmlspecialchars($est['codigo_estudiante'] ?? '', ENT_QUOTES) ?>',
                                                <?= $est['estado'] ?>
                                            )">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form action="estudiantes.php" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar a este estudiante?');">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id_estudiante" value="<?= $est['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Eliminar"><i class="fas fa-trash-alt"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal fade" id="modalEstudiante" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg">
            <div class="modal-content shadow-lg border-0">
              <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalTitulo"><i class="fas fa-user-graduate"></i> Datos del Estudiante</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <form action="estudiantes.php" method="POST">
                <div class="modal-body bg-light">
                  <input type="hidden" name="accion" value="guardar">
                  <input type="hidden" name="id_estudiante" id="inputIdEstudiante">
                  
                  <div class="row">
                      <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-secondary">Nombres</label>
                        <input type="text" class="form-control" id="inputNombres" name="nombres" required>
                      </div>
                      <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-secondary">Apellidos</label>
                        <input type="text" class="form-control" id="inputApellidos" name="apellidos" required>
                      </div>
                  </div>

                  <div class="row">
                      <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-secondary">Correo Institucional</label>
                        <input type="email" class="form-control" id="inputEmail" name="email" required>
                      </div>
                      <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-secondary">Código Universitario</label>
                        <input type="text" class="form-control" id="inputCodigo" name="codigo_estudiante" required>
                      </div>
                  </div>

                  <div class="row">
                      <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-secondary">Estado</label>
                        <select class="form-select" id="inputEstado" name="estado" required>
                            <option value="1">Activo / Matriculado</option>
                            <option value="0">Inactivo / Retirado</option>
                        </select>
                      </div>
                      <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-secondary">Contraseña</label>
                        <input type="password" class="form-control" name="password" placeholder="Dejar en blanco para no cambiar">
                        <small class="text-muted" id="passHelp">Por defecto será 'estudiante2026'</small>
                      </div>
                  </div>

                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                  <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Alumno</button>
                </div>
              </form>
            </div>
          </div>
        </div>

    </main>

    <footer class="footer mt-auto py-3 bg-dark text-white text-center shadow-lg">
        <div class="container"><span class="text-muted">Desarrollado por Michael Espinoza Coila</span></div>
    </footer>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#tablaEstudiantes').DataTable({
                "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json" },
                "pageLength": 10,
                // Ordenar por Apellidos (columna 1) alfabéticamente
                "order": [[ 1, "asc" ]] 
            });
        });

        function abrirModalEstudiante(id = '', nombres = '', apellidos = '', email = '', codigo = '', estado = 1) {
            document.getElementById('inputIdEstudiante').value = id;
            document.getElementById('inputNombres').value = nombres;
            document.getElementById('inputApellidos').value = apellidos;
            document.getElementById('inputEmail').value = email;
            document.getElementById('inputCodigo').value = codigo;
            document.getElementById('inputEstado').value = estado;
            
            document.getElementById('modalTitulo').innerHTML = id === '' 
                ? '<i class="fas fa-user-plus"></i> Matricular Estudiante' 
                : '<i class="fas fa-user-edit"></i> Editar Estudiante';
                
            document.getElementById('passHelp').style.display = id === '' ? 'block' : 'none';
            
            var modal = new bootstrap.Modal(document.getElementById('modalEstudiante'));
            modal.show();
        }
    </script>
</body>
</html>