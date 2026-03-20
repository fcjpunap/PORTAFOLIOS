<?php
// cambiar_password.php - Cambio de contraseña para usuarios con sesión iniciada
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

require_once 'config/conexion.php';
$usuario_id = $_SESSION['usuario_id'];
$rol = $_SESSION['rol'];
$mensaje = '';
$tipo_mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password_actual = $_POST['password_actual'];
    $password_nueva = $_POST['password_nueva'];
    $password_confirmar = $_POST['password_confirmar'];

    // 1. Verificar contraseña actual
    $stmt = $pdo->prepare("SELECT password FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (password_verify($password_actual, $user['password'])) {
        if ($password_nueva === $password_confirmar) {
            if (strlen($password_nueva) >= 6) {
                // Actualizar a la nueva contraseña
                $hash_nuevo = password_hash($password_nueva, PASSWORD_DEFAULT);
                $stmt_update = $pdo->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
                $stmt_update->execute([$hash_nuevo, $usuario_id]);
                
                $mensaje = "¡Tu contraseña se ha actualizado correctamente!";
                $tipo_mensaje = "success";
            } else {
                $mensaje = "La nueva contraseña debe tener al menos 6 caracteres.";
                $tipo_mensaje = "warning";
            }
        } else {
            $mensaje = "Las nuevas contraseñas no coinciden.";
            $tipo_mensaje = "danger";
        }
    } else {
        $mensaje = "La contraseña actual es incorrecta.";
        $tipo_mensaje = "danger";
    }
}

// Determinar el enlace de retorno según el rol
$url_retorno = ($rol === 'docente') ? 'dashboard_docente.php' : 'dashboard_estudiante.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cambiar Contraseña | UNAP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> body { background-color: #f8f9fa; } .bg-unap { background-color: #0b2e59; } </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-unap shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="<?= $url_retorno ?>"><i class="fas fa-arrow-left me-2"></i> Volver a mi panel</a>
        </div>
    </nav>

    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                
                <?php if ($mensaje): ?>
                    <div class="alert alert-<?= $tipo_mensaje ?> shadow-sm">
                        <i class="fas <?= $tipo_mensaje === 'success' ? 'fa-check-circle' : 'fa-info-circle' ?> me-2"></i> <?= htmlspecialchars($mensaje) ?>
                    </div>
                <?php endif; ?>

                <div class="card shadow border-0 border-top border-4 border-primary">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex p-3 mb-3">
                                <i class="fas fa-lock fa-2x"></i>
                            </div>
                            <h4 class="fw-bold text-dark">Cambiar Contraseña</h4>
                            <p class="text-muted small">Asegúrate de usar una contraseña fuerte y que no uses en otras cuentas.</p>
                        </div>

                        <form action="cambiar_password.php" method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-bold text-secondary">Contraseña Actual</label>
                                <input type="password" class="form-control" name="password_actual" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold text-secondary">Nueva Contraseña</label>
                                <input type="password" class="form-control" name="password_nueva" required minlength="6">
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold text-secondary">Confirmar Nueva Contraseña</label>
                                <input type="password" class="form-control" name="password_confirmar" required minlength="6">
                            </div>
                            <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm">
                                <i class="fas fa-save me-2"></i> Actualizar Contraseña
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>