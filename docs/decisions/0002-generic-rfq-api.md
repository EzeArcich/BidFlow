# 0002 — API genérica de RFQ y organización activa

Fecha: 2026-11-06. Estado: implementado en fase 2.

## Alcance y compatibilidad

Bidflow es un SaaS B2B genérico. Esta decisión reemplaza la orientación automotriz del documento inicial y prevalece sobre ejemplos históricos de 0001. Nuevas RFQ API usan `products`, `services` o `works`. No se agregan tablas de vehículos, catálogos, repuestos ni talleres. `attributes` de TenderItem contiene especificaciones arbitrarias.

Una migración incremental amplía CHECK existentes: tipos genéricos de RFQ; `vendor` y `contractor` en proveedores; `company` en organizaciones. Conserva `parts`, `repair`, `workshop`, `mixed`, `study`, `insurer` y RepairScope para que los datos demo, relaciones y pruebas previas sigan funcionando. La API no crea detalles sectoriales ni exige coincidencias entre tipo de RFQ y tipo de proveedor. El rollback conserva datos: PostgreSQL rechaza restaurar los CHECK antiguos mientras existan registros genéricos; no los transforma ni los elimina.

## Autenticación y selección de organización

- Sanctum ya estaba instalado. Se habilita `HasApiTokens` y una tabla de tokens con `ulidMorphs` para usuarios ULID. La PK interna de tokens es BIGINT convencional de Sanctum, fuera de las entidades de negocio.
- Sólo Bearer, sin compartir sesiones web. Token limitado a 8 horas, hash persistido, capacidades `rfq:read` y `rfq:write`. Logout revoca exclusivamente el token actual. No hay registro público ni refresh tokens.
- Login: límite 5/min por IP+email y 20/min por IP. Usuarios sin membresía pueden consultar su identidad, pero no operar sobre el dominio.
- `/auth/me` descubre organizaciones a través de una relación `User::organizations()` que parte del usuario autenticado. Es una consulta a Organization con pivot de membresía, sin desactivar el scope de OrganizationMembership.
- `X-Organization-ID` selecciona entre membresías activas. Se autoriza antes de abrir TenantContext y antes del binding de Tender. Si hay una sola organización activa se infiere; cero => 403; varias sin selector => 422. Un selector válido sin membresía => 403.
- No se usa organization_id del body, query string o token como autorización. Campos protegidos en payload retornan 422 incluso si son null.
- Owner/admin/operator crean y administran borradores; viewer consulta. Tokens sin capacidad de escritura no pueden mutar aunque el rol sí lo permita.

## Mutaciones y publicación

`WithDraftTender` centraliza transacción, lockForUpdate y verificación de rol/estado. Actualizaciones de RFQ, ítems, invitaciones y publicación bloquean la misma fila padre. Los controladores delegan las mutaciones a acciones; las respuestas se construyen con Resources explícitos.

No hay borrado físico de RFQ: mantiene la policy existente y evita romper auditoría y referencias. El alcance de RFQ es listado/creación/detalle/actualización/publicación. Sí hay eliminación de ítems de borrador sin dependencias. Cancelación y cierre son operaciones de dominio pendientes.

Ítems: descripción/unidad obligatorias, cantidades positivas como strings decimales (9 enteros y 3 decimales como máximo), posiciones únicas; se asigna la siguiente al omitirla. `attributes` reutiliza JSONB existente para especificaciones. No se agregan precios al pedido.

Invitaciones: lote atómico, proveedor y vínculo activos, contacto opcional del mismo proveedor, deadline futuro. Se crean `pending`, sin token ni email. Repeticiones devuelven la invitación existente sin modificarla ni duplicar auditoría (200). Duplicados dentro del lote => 422. UNIQUE(tender_id,supplier_id) es la barrera final. Cambiar el deadline del borrador sincroniza sus invitaciones. Los lotes bloquean proveedores en orden de ID para evitar órdenes de bloqueo inconsistentes entre RFQ. Cambiar una clasificación bloqueada por registros dependientes devuelve 409 y revierte la transacción, sin borrar datos legacy.

Publicación: exige draft, deadline futuro, al menos un ítem y una invitación; todas las invitaciones pendientes y vigentes, con proveedor/vínculo activos. Cambia a published y agrega historial, auditoría y outbox dentro de la misma transacción. Repetir => 409. No hay consumidor de outbox ni envíos en esta fase.

## Fechas y errores

PostgreSQL fija UTC por conexión; evita que timestamps serializados por Eloquent se interpreten según la zona horaria del servidor. La API exige ISO8601 con zona y normaliza el deadline a UTC antes de persistirlo.

200 consulta/actualización/publicación/invitaciones; 201 creación RFQ/ítem; 204 eliminación de ítem/logout; 401 autenticación; 403 permisos/membresía/capacidad; 404 recursos ajenos/inexistentes; 409 estado incompatible; 422 validación/precondiciones; 429 login limitado. Errores JSON de Laravel con message y errors en validaciones.

## Pendientes

Cierre/cancelación/expiración automática, portal y ofertas, adjudicación, CRUD administrativo de proveedores y usuarios, credenciales servicio-a-servicio, idempotencia por header para creación de RFQ, emails/webhooks y consumidor de outbox. La deduplicación natural de invitaciones no equivale a idempotencia general. El criterio de vencimiento de 0001 sigue pendiente; no se agrega scheduler de expiración.
