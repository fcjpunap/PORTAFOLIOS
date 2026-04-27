<?php
// usuarios.php - Gestión General de Usuarios
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

    $id_usuario = $_POST['id_usuario'] ?? '';
    $nombres = $_POST['nombres'] ?? '';
    $apellidos = $_POST['apellidos'] ?? '';
    $email = $_POST['email'] ?? '';
    $rol = $_POST['rol'] ?? 'estudiante';
    $codigo = $_POST['codigo_estudiante'] ?? null;
    $estado = $_POST['estado'] ?? 1;
    $password = $_POST['password'] ?? '';

    if ($accion === 'guardar') {
        if (empty($id_usuario)) {
            // CREAR NUEVO USUARIO
            try {
                $hash = password_hash(empty($password) ? 'unap2026' : $password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO usuarios (nombres, apellidos, email, rol, codigo_estudiante, estado, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$nombres, $apellidos, $email, $rol, $codigo, $estado, $hash]);
                $mensaje = "¡Usuario creado exitosamente! (Contraseña por defecto: unap2026)";
                $tipo_mensaje = "success";
            } catch (PDOException $e) {
                $mensaje = "Error al crear (¿El correo ya existe?): " . $e->getMessage();
                $tipo_mensaje = "danger";
            }
        } else {
            // EDITAR USUARIO EXISTENTE
            try {
                if (!empty($password)) {
                    // Si escribió una contraseña nueva, la actualizamos
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE usuarios SET nombres=?, apellidos=?, email=?, rol=?, codigo_estudiante=?, estado=?, password=? WHERE id=?");
                    $stmt->execute([$nombres, $apellidos, $email, $rol, $codigo, $estado, $hash, $id_usuario]);
                } else {
                    // Si la dejó en blanco, conservamos la contraseña anterior
                    $stmt = $pdo->prepare("UPDATE usuarios SET nombres=?, apellidos=?, email=?, rol=?, codigo_estudiante=?, estado=? WHERE id=?");
                    $stmt->execute([$nombres, $apellidos, $email, $rol, $codigo, $estado, $id_usuario]);
                }
                $mensaje = "¡Usuario actualizado correctamente!";
                $tipo_mensaje = "success";
            } catch (PDOException $e) {
                $mensaje = "Error al actualizar: " . $e->getMessage();
                $tipo_mensaje = "danger";
            }
        }
    } elseif ($accion === 'eliminar') {
        // ELIMINAR USUARIO
        try {
            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->execute([$id_usuario]);
            $mensaje = "Usuario eliminado correctamente.";
            $tipo_mensaje = "success";
        } catch (PDOException $e) {
            $mensaje = "No se puede eliminar este usuario porque tiene envíos o cursos asociados. Intenta cambiar su estado a 'Inactivo'.";
            $tipo_mensaje = "warning";
        }
    }
}

// --- 2. OBTENER DATOS PARA LA TABLA ---
$sql_usuarios = "SELECT * FROM usuarios ORDER BY id DESC";
$lista_usuarios = $pdo->query($sql_usuarios)->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios | Portafolios UNAP</title>
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
                    <li class="nav-item"><a class="nav-link" href="estudiantes.php"><i class="fas fa-user-graduate"></i> Estudiantes</a></li>
                    <li class="nav-item"><a class="nav-link active" href="usuarios.php"><i class="fas fa-users-cog"></i> Usuarios</a></li>
                </ul>
                <div class="d-flex text-white align-items-center">
                    <span class="me-3"><i class="fas fa-user-tie"></i> <?= htmlspecialchars($_SESSION['nombre_completo'] ?? 'Admin') ?></span>
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
            <h2 class="h4 text-secondary"><i class="fas fa-users-cog"></i> Gestión de Usuarios</h2>
            <button class="btn btn-success shadow-sm" onclick="abrirModalUsuario()">
                <i class="fas fa-user-plus"></i> Nuevo Usuario
            </button>
        </div>

        <div class="card shadow">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaUsuarios" class="table table-striped table-hover align-middle w-100">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Nombre Completo</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($lista_usuarios as $user): ?>
                            <tr>
                                <td><?= $user['id'] ?></td>
                                <td class="fw-bold">
                                    <?= htmlspecialchars($user['nombres'] . ' ' . $user['apellidos']) ?>
                                    <?php if($user['codigo_estudiante']): ?>
                                        <br><small class="text-muted">Cód: <?= htmlspecialchars($user['codigo_estudiante']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <?php if($user['rol'] == 'docente'): ?>
                                        <span class="badge bg-primary">Docente</span>
                                    <?php elseif($user['rol'] == 'admin'): ?>
                                        <span class="badge bg-dark">Admin</span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-dark">Estudiante</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $user['estado'] ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-danger">Inactivo</span>' ?>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-warning" title="Editar" 
                                            onclick="abrirModalUsuario(
                                                <?= $user['id'] ?>, 
                                                '<?= htmlspecialchars($user['nombres'], ENT_QUOTES) ?>', 
                                                '<?= htmlspecialchars($user['apellidos'], ENT_QUOTES) ?>',
                                                '<?= htmlspecialchars($user['email'], ENT_QUOTES) ?>',
                                                '<?= $user['rol'] ?>',
                                                '<?= htmlspecialchars($user['codigo_estudiante'] ?? '', ENT_QUOTES) ?>',
                                                <?= $user['estado'] ?>
                                            )">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form action="usuarios.php" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar este usuario definitivamente?');">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id_usuario" value="<?= $user['id'] ?>">
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

        <div class="modal fade" id="modalUsuario" tabindex="-1" aria-hidden="true">
          <div class="modal-dialog modal-lg">
            <div class="modal-content shadow-lg border-0">
              <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="modalTitulo"><i class="fas fa-user"></i> Nuevo Usuario</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <form action="usuarios.php" method="POST">
                <div class="modal-body bg-light">
                  <input type="hidden" name="accion" value="guardar">
                  <input type="hidden" name="id_usuario" id="inputIdUsuario">
                  
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
                        <label class="form-label fw-bold text-secondary">Correo Electrónico (Email)</label>
                        <input type="email" class="form-control" id="inputEmail" name="email" required>
                      </div>
                      <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-secondary">Código (Solo para estudiantes)</label>
                        <input type="text" class="form-control" id="inputCodigo" name="codigo_estudiante">
                      </div>
                  </div>

                  <div class="row">
                      <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold text-secondary">Rol</label>
                        <select class="form-select" id="inputRol" name="rol" required>
                            <option value="estudiante">Estudiante</option>
                            <option value="docente">Docente</option>
                            <option value="admin">Administrador</option>
                        </select>
                      </div>
                      <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold text-secondary">Estado</label>
                        <select class="form-select" id="inputEstado" name="estado" required>
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                      </div>
                      <div class="col-md-4 mb-3">
                        <label class="form-label fw-bold text-secondary">Contraseña</label>
                        <input type="password" class="form-control" name="password" placeholder="Dejar en blanco para no cambiar">
                        <small class="text-muted" id="passHelp">Si es nuevo y se deja en blanco será 'unap2026'</small>
                      </div>
                  </div>

                </div>
                <div class="modal-footer">
                  <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                  <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Guardar Usuario</button>
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
            $('#tablaUsuarios').DataTable({
                "language": { "url": "https://cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json" },
                "pageLength": 10,
                "order": [[ 0, "desc" ]]
            });
        });

        function abrirModalUsuario(id = '', nombres = '', apellidos = '', email = '', rol = 'estudiante', codigo = '', estado = 1) {
            document.getElementById('inputIdUsuario').value = id;
            document.getElementById('inputNombres').value = nombres;
            document.getElementById('inputApellidos').value = apellidos;
            document.getElementById('inputEmail').value = email;
            document.getElementById('inputRol').value = rol;
            document.getElementById('inputCodigo').value = codigo;
            document.getElementById('inputEstado').value = estado;
            
            document.getElementById('modalTitulo').innerHTML = id === '' 
                ? '<i class="fas fa-user-plus"></i> Nuevo Usuario' 
                : '<i class="fas fa-user-edit"></i> Editar Usuario';
                
            document.getElementById('passHelp').style.display = id === '' ? 'block' : 'none';
            
            var modal = new bootstrap.Modal(document.getElementById('modalUsuario'));
            modal.show();
        }
    </script>
</body>
</html>