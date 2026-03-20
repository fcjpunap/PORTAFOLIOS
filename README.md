# PORTAFOLIOS - FCJP UNAP

Sistema de Gestión de Portafolios Académicos para la Facultad de Ciencias Jurídicas y Políticas de la Universidad Nacional del Altiplano.

## Descripción

Este sistema permite la gestión integral de portafolios académicos. Incluye funcionalidades para:
- Gestión de Usuarios (Admins, Docentes y Estudiantes).
- Registro y Matriculación en Cursos.
- Creación de Actividades y Fichas de Casos.
- Envío y Calificación de trabajos.
- Reportes Estadísticos y BI.

## Requisitos del Sistema

- **PHP**: 8.1 o superior (recomendado PHP 8.2).
- **Base de Datos**: MySQL 5.7+ o MariaDB 10.4+.
- **Servidor Web**: Apache (con `mod_rewrite`) o Nginx.
- **Extensiones PHP**: `pdo_mysql`, `mbstring`, `openssl`.

## Instalación y Configuración

1. **Clonar el repositorio**:
   ```bash
   git clone https://github.com/fcjpunap/PORTAFOLIOS.git
   ```

2. **Importar la Base de Datos**:
   Crea una base de datos e importa el archivo `fcjp_portafolios.sql`.

3. **Configurar Conexión**:
   Edita el archivo `config/conexion.php` con tus credenciales:
   ```php
   $host     = 'localhost';
   $dbname   = 'tu_nombre_db';
   $user     = 'tu_usuario';
   $password = 'tu_contrasena';
   ```

4. **Zona Horaria**:
   El sistema está configurado por defecto para la zona horaria de Puno, Perú (`America/Lima`).

## Licencia

Este proyecto está distribuido bajo la licencia **GNU GPL v3**. Consulta el archivo `LICENSE` para más detalles.

---
© 2026 Facultad de Ciencias Jurídicas y Políticas - Universidad Nacional del Altiplano.
