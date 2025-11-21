# 🚀 Guía de Despliegue - Servidor Kadri

Sigue estos pasos para actualizar tu aplicación en el servidor en vivo.

## 1. Actualizar Código (Git)
Como ya clonaste el repositorio, solo necesitas traer los últimos cambios.

1.  Conéctate por SSH (o usa la Terminal de cPanel).
2.  Navega a la carpeta de tu aplicación:
    ```bash
    cd /ruta/a/tu/carpeta/public_html
    ```
3.  Descarga los cambios:
    ```bash
    git pull origin main
    ```

## 2. Actualizar Base de Datos
> ⚠️ **IMPORTANTE**: Haz un backup de tu base de datos actual antes de importar.

1.  Entra a **phpMyAdmin** desde tu cPanel.
2.  Selecciona tu base de datos.
3.  Ve a la pestaña **Importar**.
4.  Sube el archivo `schema_full.sql` que está en la carpeta de tu proyecto (o súbelo desde tu PC).
5.  Ejecuta la importación.

## 3. Configuración (Solo si es la primera vez)
1.  Busca el archivo `conn_production.example.php`.
2.  Renómbralo a `conn.php`.
3.  Edítalo y pon tus datos reales de cPanel:
    ```php
    $DB_USER = 'tu_usuario_cpanel';
    $DB_PASS = 'tu_contraseña';
    $DB_NAME = 'tu_base_datos';
    ```

¡Listo! Tu aplicación debería estar actualizada y funcionando.
