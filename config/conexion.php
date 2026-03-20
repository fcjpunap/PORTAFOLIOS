<?php
// config/conexion.php - Conexión principal a la Base de Datos

// 1. Establecer la zona horaria a la hora oficial de Perú
// Esto garantiza que si una tarea vence a las 23:59, sea exactamente a esa hora en Puno.
date_default_timezone_set('America/Lima');

// 2. Credenciales de la Base de Datos en Producción
// ¡ATENCIÓN! Debes cambiar estos datos por los que creaste en el cPanel de tu servidor de la UNAP
$host     = 'localhost';                  // Generalmente es 'localhost' en cPanel
$dbname   = 'tu_base_de_datos'; // Ej: derecho_mespinoza_penal
$user     = 'tu_usuario';           // Ej: derecho_user1
$password = 'tu_contrasena';  // La contraseña asignada a ese usuario

try {
    // 3. Crear la conexión utilizando PDO (PHP Data Objects)
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    
    $opciones = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Activa el reporte de errores de SQL
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Devuelve los resultados como arrays asociativos
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Mayor seguridad contra Inyección SQL
    ];

    $pdo = new PDO($dsn, $user, $password, $opciones);

} catch (PDOException $e) {
    // 4. Qué hacer si falla la conexión
    // En producción es mejor no mostrar el error completo al usuario, pero para pruebas lo dejamos así:
    die("<div style='font-family: Arial; padding: 20px; color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px;'>
            <strong>Error Crítico de Sistema:</strong> No se pudo conectar a la base de datos.<br><br>
            Detalle técnico: " . htmlspecialchars($e->getMessage()) . "
         </div>");
}
?>