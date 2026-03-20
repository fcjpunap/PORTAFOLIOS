<?php
// login.php - Acceso al sistema
session_start();

// 1. SOLUCIÓN AL BUCLE DE REDIRECCIÓN: Ahora se evalúan los 3 roles correctamente
if (isset($_SESSION['usuario_id'])) {
    if ($_SESSION['rol'] === 'admin') {
        header('Location: dashboard_admin.php');
    } elseif ($_SESSION['rol'] === 'docente') {
        header('Location: dashboard_docente.php');
    } else {
        header('Location: dashboard_estudiante.php');
    }
    exit;
}

require_once 'config/conexion.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (!empty($email) && !empty($password)) {
        // Buscar al usuario por email o código
        $stmt = $pdo->prepare("SELECT id, rol, nombres, apellidos, password FROM usuarios WHERE email = ? OR codigo_estudiante = ?");
        $stmt->execute([$email, $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verificar la contraseña cifrada
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['usuario_id'] = $user['id'];
            $_SESSION['rol'] = $user['rol'];
            $_SESSION['nombre_completo'] = $user['nombres'] . ' ' . $user['apellidos'];

            // Redirigir según el rol exacto
            if ($user['rol'] === 'admin') {
                header('Location: dashboard_admin.php');
            } elseif ($user['rol'] === 'docente') {
                header('Location: dashboard_docente.php');
            } else {
                header('Location: dashboard_estudiante.php');
            }
            exit;
        } else {
            $error = 'Credenciales incorrectas. Verifica tu correo/código y contraseña.';
        }
    } else {
        $error = 'Por favor, completa todos los campos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión | Portafolios FCJP UNAP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style> 
        body { background-color: #f4f6f9; } 
        .bg-unap { background-color: #0b2e59; } 
        .text-unap { color: #0b2e59; }
    </style>
</head>
<body class="d-flex align-items-center min-vh-100">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">
                <div class="card shadow border-0 rounded-3">
                    <div class="card-header bg-unap text-white text-center py-4 rounded-top">
                        <i class="fas fa-university fa-3x mb-2"></i>
                        <h4 class="mb-0 fw-bold">Portafolios FCJP</h4>
                        <small>Universidad Nacional del Altiplano</small>
                    </div>
                    <div class="card-body p-4 p-md-5">
                        <?php if ($error): ?>
                            <div class="alert alert-danger small"><i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <form action="login.php" method="POST">
                            <div class="mb-3">
                                <label class="form-label fw-bold text-secondary small">Correo o Código</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fas fa-user text-muted"></i></span>
                                    <input type="text" class="form-control" name="email" placeholder="Ingresa tu correo o código" required autofocus>
                                </div>
                            </div>
                            <div class="mb-4">
                                <label class="form-label fw-bold text-secondary small">Contraseña</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fas fa-lock text-muted"></i></span>
                                    <input type="password" class="form-control" name="password" placeholder="••••••••" required>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm" style="background-color: #0b2e59; border-color: #0b2e59;">
                                <i class="fas fa-sign-in-alt me-2"></i> Ingresar
                            </button>
                        </form>

                        <div class="text-center mt-4">
                            <a href="recuperar_password.php" class="text-decoration-none small text-primary fw-bold">
                                <i class="fas fa-question-circle"></i> ¿Olvidaste tu contraseña?
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>