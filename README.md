# Registro de Atención a Pacientes (RAP) - HCEI Colombia

Autor: Juanfer2K [el] (elcerritovalle.org)
Versión Actual: 3.5

Aplicación web diseñada para el personal de Ambulancias AVIS que registra digitalmente la atención prehospitalaria, con soporte de firmas, adjuntos, consulta y generación de PDFs. La v3 incorpora mejoras de codificación, permisos, búsqueda, CIE‑10, tema oscuro y firmas.

## Novedades recientes (v3)
- Codificación UTF‑8 unificada (cabeceras y pantallas)
- Permisos: Tripulación puede ver consultas y detalles; PDF/Furtran solo Administrativo/Master/Secretaría
- Búsqueda corregida en consultas (placeholders `?` en filtros LIKE)
- CIE‑10: endpoint robusto y compatibilidad de nombres en Linux (`diagnosticosCie10`, `categoriasCie10`)
- Modo oscuro con gradientes sutiles por sección
- Firmas: color por firma (`data-pen-color`/`--signature-color`) y modal con la misma relación de aspecto que el lienzo en el formulario

## Puesta en marcha
1. PHP 8.1+ con MySQLi (GD/Imagick opcionales)
2. Configurar base de datos en `conn.php`
3. Importar tablas CIE‑10 (`diagnosticosCie10`, `categoriasCie10`)
4. Servir la carpeta (Apache recomendado). `bootstrap.php` define `BASE_URL` automáticamente

## Búsqueda CIE‑10
- Endpoint: `buscar_cie10.php`
- Parámetro: `q` (mínimo 3 caracteres)
- Devuelve JSON Select2 `{ id, text }`; en error responde 500 con detalles y log `[CIE10]`

## Roles
- Tripulación: ver `consulta_atenciones.php` y `obtener_detalle_atencion.php`
- Administrativo/Master/Secretaría: además generar PDF y abrir Furtran
- `admin/contrato.php`: solo Master

## Tema Oscuro
- Activable desde el switch en el header; estilos en `style-dark.css`

## Firmas
- Cada `<canvas class="signature-pad">` declara su color con `data-pen-color`
- JS resuelve el color dinámicamente y mantiene trazos en redimensionado
- Modal respeta relación 500×220 (ajustable en `index.php`)

## Nota de desarrollo
- Guardar archivos en UTF‑8
- Revisar `consulta_atenciones.php` para WHERE dinámico y paginación
- Navegación/roles centralizados en `header.php`

