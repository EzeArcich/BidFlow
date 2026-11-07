# 0003 — Administración web Blade

Fecha: 2026-11-06. Estado: implementado.

## Alcance

Interfaz administrativa para las operaciones RFQ existentes, independiente del futuro portal de proveedores. Sin ofertas, adjudicaciones, webhooks ni emails nuevos. Sin migraciones ni cambios de dependencias Composer/npm.

## Autenticación y tenancy

Login/logout con guard `web`, sesiones Laravel y protección CSRF del grupo web. Login regenera ID de sesión; logout invalida sesión y regenera token CSRF, sin revocar tokens Sanctum. Comparte el rate limiter de login existente. Sanctum sigue aceptando exclusivamente Bearer: una sesión web no autentica la API.

El selector consulta organizaciones activas desde el usuario autenticado y valida cada elección antes de guardar `active_organization_id` en sesión. Cada solicitud vuelve a comprobar membresía/estado antes de abrir TenantContext y antes del binding de Tender. Una selección revocada se limpia y obliga a elegir otra. Sin membresías hay una pantalla explicativa, sin datos de terceros.

Se reutilizan policies y roles: viewer sólo consulta. Los botones no autorizados se ocultan, pero los endpoints también autorizan en servidor. Formularios RFQ/ítems rechazan organization_id como autorización. La selección explícita de organización tiene su propio endpoint y verifica la membresía, nunca confía en el ID del formulario.

## Reutilización del dominio

Los controladores web usan SaveTender, ManageTenderItem, InviteSuppliers y PublishTender. Los adaptadores Form Request heredan las reglas API; convierten fecha local de Buenos Aires a UTC y campos de texto a conditions/attributes, conservando otras claves existentes. No se implementan mutaciones mediante HTTP contra la propia API ni se guardan tokens API en el navegador.

ListTenders y AvailableSuppliers concentran consultas compartidas por web y API; se mantiene el contrato REST. Dashboard obtiene conteos agrupados y solicitudes recientes mediante TenantModel. No hay números precargados, métricas monetarias estimadas ni gráficos ficticios.

Publicación requiere confirmación aceptada en servidor además de las precondiciones del dominio. GET sólo muestra revisión; POST ejecuta la acción transaccional. Las validaciones vuelven al formulario con datos anteriores; conflictos 409 de mutaciones web se presentan como errores de formulario. API conserva sus status JSON originales.

ActivityContext scoped identifica auditoría web/API y restaura su valor al salir, al igual que TenantContext. Los motivos de historial son neutrales al canal. La auditoría se escribe desde las mismas acciones.

## Interfaz y assets

Bootstrap 5.3.8 compilado, almacenado en public/vendor/bootstrap con licencia MIT, más CSS de identidad Bidflow y JavaScript vanilla. No npm, SPA, fuentes remotas ni solicitudes CDN en runtime. Fuente de Bootstrap: https://getbootstrap.com/docs/5.3/getting-started/download/.

Navegación lateral con offcanvas móvil, header con organización/rol, foco accesible y estados con texto. Tablas con scroll horizontal en pantallas pequeñas, paginación Bootstrap y filtros GET. Fechas en Buenos Aires y cantidades decimales como strings.

Invitaciones: selección por página y contacto opcional; JavaScript habilita el contacto sólo al seleccionar el proveedor. Sin JavaScript se pueden seleccionar proveedores sin contacto. No se persiste selección entre páginas; las invitaciones ya guardadas se marcan como tales.

Se conserva la clasificación de solicitudes legacy al editar; no se fuerza su conversión. Conditions y attributes existentes permanecen; la interfaz administra `notes` y `specifications` como texto.

## Operación y pendientes

Se usan los usuarios existentes, sin nuevas contraseñas predeterminadas ni cambios automáticos. Las cuentas demo tienen contraseñas aleatorias hasta que el administrador asigne una contraseña local. El README documenta el flujo de navegador.

Pendiente: administración de cuentas/proveedores, perfiles/zonas horarias por usuario, cierre/cancelación/expiración, adjuntos, ofertas/adjudicación y comunicaciones. Esta UI no agrega esas operaciones ni muestra botones que aparenten ofrecerlas.
