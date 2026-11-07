Bidflow — Especificación técnica y avance real
Versión: 0.4 · Fecha: 2026-11-06 · Estado: fase 3 implementada y validada localmente

1. Objetivo y alcance vigente
Bidflow es un SaaS B2B genérico de solicitudes de cotización (RFQ) y adjudicación. Una licitación puede representar productos, servicios u obras de cualquier industria. Es independiente de InsureTech Assess y de cualquier sistema origen. No consulta otras bases ni requiere APIs comerciales para arrancar.

Esta versión reemplaza la orientación automotriz de la especificación 0.1. Los datos/tipos especializados existentes se conservan exclusivamente por compatibilidad; no determinan las reglas del motor ni de la nueva API. No agregar tablas ni reglas centrales para vehículos, repuestos o talleres.

MVP: organizaciones, usuarios/membresías, red de proveedores habilitada por organización, RFQ/ítems/invitaciones, ofertas parciales, comparación/adjudicación múltiple y distribuida, órdenes, auditoría, comunicaciones y API. Fuera: pagos, facturación, marketplace público, comisiones, subastas dinámicas, compatibilidades técnicas verificadas e integraciones comerciales obligatorias.

2. Stack y principios
PHP 8.4, Laravel 13, PostgreSQL (17 en Compose), Redis/Predis/Horizon, Scheduler, Blade y Mailpit local. Dependencias fijadas por composer.lock. OpenAPI 3.1 y PHPUnit. Un monolito modular, sin repositorios genéricos, microservicios, event sourcing ni servicios externos injustificados.

ULID en entidades de negocio. Sanctum conserva BIGINT interno de tokens, con propietarios ULID. Dinero NUMERIC(15,2), cantidades NUMERIC(12,3), sin float para importes. Persistencia y conexiones PostgreSQL en UTC; presentación argentina cuando corresponda en America/Argentina/Buenos_Aires.

Aislamiento multiempresa por organization_id, TenantModel, TenantContext y policies; no hay RLS. Toda consulta tenant exige contexto. FK compuestas protegen pertenencia entre entidades. El contexto se restaura incluso ante excepciones. SQL directo se reserva a migraciones, seeders y pruebas.

3. Dominio existente
Identity: Organization, User, OrganizationMembership (owner/admin/operator/viewer), ApiClient.
Supplier Network: Supplier, SupplierContact, SupplierLocation, SupplierCapability y OrganizationSupplier. Datos generales compartidos; habilitación y notas privadas por organización. API de fase 2 sólo lista proveedores y vínculos activos, con contactos/capacidades. No administra la red global.
Tendering: Tender, TenderItem, TenderAttachment, TenderInvitation, TenderStatusHistory.
Offers/fulfillment: Bid, BidItem, Award y WorkOrder.
Integration: InvitationAccessToken, AuditLog, WebhookEndpoint, WebhookDelivery, IdempotencyRecord y OutboxEvent.

Tipos nuevos de RFQ: products, services, works. Tipos nuevos de proveedor: vendor, contractor; Organization admite company. Se conservan parts/repair, parts/workshop/mixed y study/insurer para demo y persistencia legacy. RepairScope existente permanece como dato heredado; la API no lo crea ni expone reglas relacionadas. No se exige correspondencia sectorial entre RFQ y proveedor.

Tender: organization_id, type, status, title, description, currency ARS, deadline_at, conditions JSONB, source_system, external_reference, created_by y timestamps. external_claim_id heredado no se requiere ni se usa en la nueva API.
TenderItem: tender_id, posición única, description, quantity, unit, attributes JSONB como especificaciones genéricas, reference_code y notes opcionales. No contiene precios universales.
TenderInvitation: proveedor de la organización, contacto opcional del mismo proveedor, estado y vencimiento. UNIQUE(tender_id,supplier_id).

4. Autenticación implementada
POST /api/v1/auth/login: email, password, device_name. Emite Bearer Sanctum con capacidades rfq:read/rfq:write y vigencia de 8 horas; sólo se almacena hash. Responde 200 con access_token, token_type, expires_at y data del usuario. Límite 5 intentos/min por IP+email y 20 por IP.
GET /api/v1/auth/me: usuario autenticado y organizaciones/roles/estados propios.
POST /api/v1/auth/logout: revoca sólo el token actual; 204.
Sin sesiones compartidas, registro público, refresh tokens ni SSO. La gestión administrativa de usuarios/membresías queda pendiente. Usuarios demo con contraseñas aleatorias: asignar una contraseña local mediante administración antes de probar login.

5. Organización activa y permisos
X-Organization-ID selecciona una organización activa de la que el usuario autenticado es miembro. No es autorización por sí mismo. Si existe una sola organización activa se infiere; si hay varias, omitir el header retorna 422; sin membresía activa o con un selector ajeno, 403. Un ULID inválido retorna 422.
La relación User::organizations descubre membresías desde el usuario autenticado sin desactivar TenantModel. El middleware autoriza antes de abrir TenantContext y antes del binding de modelos. Nunca tomar organization_id del body/query como autorización.
Owner/admin/operator: crear y administrar borradores/publicar. Viewer: sólo consulta. Se exigen además capacidades de lectura/escritura del token. Recursos ajenos o inexistentes => 404.

6. API operativa fase 2
Base /api/v1. JSON; contrato exhaustivo en docs/openapi.yaml y colección ejecutable en docs/postman/Bidflow-phase-2.postman_collection.json.

GET /health: liveness público, 200 {status:ok,service:bidflow}.
GET /tenders: lista propia paginada. q por título; filtros status/type; page/per_page (20 por defecto, máximo 100).
POST /tenders: crea draft (201). Obligatorios title, type genérico, deadline_at ISO8601 futuro con zona. Opcionales description, currency ARS, conditions, source_system, external_reference. Organización/creador/estado los asigna el servidor.
GET /tenders/{tender}: detalle con ítems e invitaciones (200).
PATCH /tenders/{tender}: actualiza sólo draft (200); deadline sincroniza invitaciones. Cambios de clasificación impedidos por dependencias existentes retornan 409 y conservan datos.
GET /tenders/{tender}/items: lista ordenada por posición (200).
POST /tenders/{tender}/items: agrega ítem en draft (201). description, unit, quantity string decimal positiva; opcionales position, attributes, reference_code, notes. Cantidad: hasta 9 enteros/3 decimales, no notación científica ni números JSON. position omitida se asigna al final.
PATCH /tenders/{tender}/items/{item}: edición en draft (200); debe pertenecer a la RFQ de ruta.
DELETE /tenders/{tender}/items/{item}: elimina en draft sin dependencias (204); con ofertas/adjuntos => 409.
GET /suppliers: proveedores disponibles propios, activos y vinculados, con contactos/capacidades (200 paginado). q por nombre legal/comercial; page/per_page.
GET /tenders/{tender}/invitations: consulta (200).
POST /tenders/{tender}/invitations: lote invitations:[{supplier_id,contact_id?}], máximo 100 distintos, transaccional (200). Sólo draft con deadline futuro; proveedor/vínculo activos y contacto del proveedor. Repetir devuelve existentes sin cambios, sin duplicar auditoría. Duplicados dentro del lote => 422. No envía emails ni genera tokens de portal.
POST /tenders/{tender}/publish: publica draft (200), sin body.

No hay eliminación física de RFQ; se mantiene la policy existente para proteger referencias y auditoría. El cierre está implementado como operación explícita; cancelación permanece pendiente. Los ítems sí admiten eliminación en las condiciones anteriores.

Campos protegidos id, organization_id, created_by, status, is_demo, tender_id y repair_scope se rechazan en payload incluso con null. Ítems/invitaciones se gestionan con sus endpoints separados. Descripciones y condiciones no contienen reglas sectoriales.

Respuestas individuales: data. Colecciones: data; paginadas agregan links/meta. Error: message; validaciones agregan errors por campo. 401 sin autenticación/token inválido/expirado; 403 permisos/membresía/capacidad; 404 inexistente/ajeno; 409 conflicto de estado/dependencias; 422 datos o precondiciones inválidos; 429 login limitado.

7. Publicación, auditoría y consistencia
Las acciones de escritura comparten DB::transaction y lockForUpdate sobre Tender mediante WithDraftTender; verifican organización, permisos y estado al escribir. No se actualiza status libremente desde controladores ni requests.
Publicar exige:
- Tender draft con deadline futuro.
- Al menos un ítem.
- Al menos un proveedor invitado.
- Todas las invitaciones pending y vigentes, con proveedor/vínculo activos.
La publicación guarda published, historial draft->published, auditoría tender.published y OutboxEvent en una misma transacción. Si cualquier persistencia falla, revierte todo. Repetir publicación => 409 sin duplicar eventos. Ningún correo/webhook se envía en fase 2.
Creación, edición, ítems e invitaciones también registran auditoría. Invitaciones usan bloqueo padre más UNIQUE para deduplicar. No hay todavía idempotencia general por Idempotency-Key.

8. Estados y fases
Tender: draft -> published -> closed -> partially_awarded -> awarded. Una adjudicación puede pasar directamente de closed a awarded si cubre todo. completed/cancelled/expired conservan reglas futuras; no hay expiración automática.
TenderInvitation: pending -> sent -> viewed -> responded; alternativas declined/expired/revoked. Fase 2 crea pending; no envía.
Bid: draft -> submitted implementado, presentación inmutable y cobertura parcial permitida, cálculos en servidor. accepted/rejected/withdrawn se conservan del modelo anterior, sin nuevos endpoints de transición.
WorkOrder: issued -> accepted -> in_progress -> completed; cancelled/disputed con reglas futuras explícitas.

Fase 3 implementada: identidades SupplierUser con sesión propia/tokens Sanctum, ofertas parciales, comparación, cierre explícito, adjudicación múltiple con cantidades distribuidas, órdenes por proveedor/operación. Pruebas reales de idempotencia y concurrencia PostgreSQL.
Fase 4: emails en cola, consumidor de outbox, webhooks HTTPS firmados HMAC, timestamp anti-replay, backoff y deduplicación por event_id; credenciales de integración por organización. Ningún origen comercial específico es obligatorio.
Fase 5: dashboard/UI, adjuntos privados autorizados, retención, rate limiting ampliado, observabilidad, backups y E2E.

9. Restricciones conservadas
UNIQUE de invitación por RFQ/proveedor, oferta por RFQ/proveedor, adjudicación legacy por RFQ/oferta; nuevas adjudicaciones por clave/RFQ y órdenes por adjudicación/proveedor; número de orden único por organización. FK compuestas validan mismo tenant, misma RFQ y proveedor/invitación/contacto; moneda de ofertas consistente. CHECK de cantidades/precios/estados; subtotal decimal consistente. Source references no fuerzan unicidad: una referencia externa puede tener varias RFQ.
Documentos privados en disco local; no usar enlaces públicos por defecto. Tokens/credenciales sólo hash, secretos de webhook cifrados; no loguear secretos. Después de confirmar transacciones para envíos; outbox en la misma transacción.

10. Demo, pruebas y validación
Demo heredada explícitamente ficticia, sin OEM/compatibilidades verificadas, correos example.test. Comando bidding:seed-demo --profile=demo|development|stress; sólo local/testing y flag BIDDING_DEMO_ENABLED. Repetición rechaza un demo existente, sin borrar datos.
Perfiles: demo 2 organizaciones/15 proveedores/20 RFQ/48 ofertas; development 60/300/960; stress 250/5000/20000 (siempre 2 organizaciones). Las adjudicaciones demo conservan snapshot y orden.
Pruebas PostgreSQL obligatorias sobre base dedicada terminada en _testing. Se conserva la suite base de 27 tests; la expectativa de /tenders anónimo cambia de 404 a 401 por existir ahora la ruta. Suite final: 93 tests y 715 assertions; Pint correcto. Pruebas nuevas cubren todos los endpoints, roles, identidad/membresías, acceso cruzado, payloads inválidos, edición no permitida, invitaciones duplicadas, publicación/precondiciones, transacciones, persistencia y UTC.
Docker y suite base 27/334 operativos confirmados por el usuario. Esta sesión prueba con PHP 8.4/PostgreSQL 14 aislado por falta de permiso del socket Docker. Compose/CI usan PostgreSQL 17.

11. Decisiones abiertas
- Criterio al vencer RFQ: propuesta cerrar para evaluación si hay ofertas válidas y expirar si no. No hay expiración automática aún.
- Transiciones de ejecución de órdenes; adjudicación ya restringida a owner/admin/operator.
- Impuestos/IVA, retención/límites de adjuntos y revisión de ofertas antes de producción.
- Administración de datos generales compartidos y contactos privados por organización.
- Idempotencia por header en creación de RFQ e integración servicio-a-servicio.
- Evolución/migración definitiva de datos legacy sin romper compatibilidad.

12. Instrucciones de desarrollo
Usar este documento y las decisiones de docs/decisions. El alcance autorizado incluye fases 2 y 3 y sus interfaces Blade; comunicaciones y ejecución de órdenes permanecen pendientes. Mantener lógica de negocio en acciones, Form Requests, Resources, policies y restricciones PostgreSQL. No desactivar scopes para resolver tenancy; no usar proveedores reales ni APIs externas. Ejecutar tests y Pint. No borrar datos para aplicar migraciones.

13. Interfaz administrativa Blade implementada
Login/logout de sesiones Laravel, CSRF y regeneración de sesión; no modifica Sanctum Bearer. Elección de organización activa verificada contra membresía autenticada, revalidada por solicitud, con limpieza de selección revocada. Middleware ejecutado antes del binding y TenantContext restaurado al finalizar.
Layout SaaS B2B responsive: sidebar/offcanvas móvil, header con rol/organización, Bootstrap 5.3.8 servido localmente, CSS propio y JavaScript vanilla. Sin npm, SPA ni CDN en navegador. Indicadores reales por organización: total de licitaciones, borradores, publicadas, cerradas/en evaluación y últimas solicitudes.
Pantallas de listado filtrado/paginado, creación/edición de RFQ, detalle con ítems e invitados, alta/edición/eliminación de ítems, búsqueda y selección de proveedores habilitados e invitaciones. Publicación con confirmación aceptada en servidor y validaciones compartidas con API; después queda en consulta.
Controladores web reutilizan acciones existentes; Form Requests adaptan formularios y heredan validaciones. Queries de listas compartidas con API. Conditions/attributes se conservan al agregar notas/especificaciones. No se envían organization_id de formularios como autorización, salvo selector explícito que valida membresía. Viewer sólo lectura, verificaciones también en servidor.
Rutas y flujo de navegador en README; decisiones en docs/decisions/0003-blade-admin.md. Sin migraciones nuevas ni cambios automáticos de credenciales. Cuentas demo operador1@example.test y operador2@example.test requieren contraseña local asignada por administrador.
La fase 3 extiende el panel con comparación/adjudicación y agrega un portal de proveedores separado. Emails/webhooks y ejecución de órdenes permanecen pendientes.

Validación de la interfaz: 106 tests, 885 assertions; Pint y Composer correctos. Se mantienen las 93 pruebas previas y se agregan 13 escenarios web. Revisión de navegador real con CSRF, sesiones y tamaños de escritorio/móvil sobre datos temporales aislados.


FASE 3 — Evolución vigente (prevalece sobre limitaciones de fases anteriores)
Portal independiente /portal, SupplierUser explícito, sesiones propias y Sanctum proveedor; sin registro público. Acceso por proveedor, organización y vínculo activos e invitación propia. No expone economía de competidores.
BidItem conserva cantidad ofrecida, precio ARS, plazo, condiciones. Borrador reemplazable; presentación definitiva congela snapshot/filas. Cantidades positivas hasta lo solicitado, ítems parciales permitidos, deadlines estrictos.
Award nuevo: operación confirmada con motivo, actor, clave/hash idempotente y snapshot. AwardLine: organización, licitación, ítem, oferta/línea, proveedor, cantidad, precio, subtotal, snapshot. FK compuestas verifican pertenencias. Legacy conserva bid_id e índices parciales; migración materializa líneas y órdenes anteriores sin borrar datos.
WorkOrder plural por adjudicación/proveedor, WorkOrderLine refiere la línea adjudicada exacta. Cantidades y dinero inmutables; no mezcla proveedores. Todo en la misma transacción.
Solicitada=ítem; ofertada=BidItem; adjudicada=SUM AwardLine; pendiente=solicitada-adjudicada; disponible oferta=ofertada-SUM adjudicaciones de BidItem. Total=suma de subtotales half-up a 2 decimales; cantidades escala 3, BCMath sin floats.
Flujo published -> closed (cierre explícito) -> partially_awarded -> awarded sólo con pendientes cero. Recepción vencida no acepta ofertas aunque no exista cierre automático. Adjudicación sólo por owner/admin/operator; viewer consulta.
Bloqueo Tender primero, luego ítems/líneas; trigger valida capacidad acumulada. Idempotency-Key/request_key por RFQ, hash canónico; mismo payload devuelve mismo recurso, distinto=409. Rollback completo ante fallos.
Entregas A–F y decisiones: docs/decisions/0004-partial-awards.md. Guía funcional, credenciales demo elegidas localmente y endpoints: docs/phase-3.md. Contrato docs/openapi.yaml y docs/postman/Bidflow-phase-3.postman_collection.json. Demo separada bidding:seed-phase3-demo, aditiva local/testing, nunca sobrescribe identidades ni datos anteriores.
Pendientes: emails, consumidor outbox, webhooks, pagos/facturación/logística, cancelación/anulación/revisión y administración avanzada.

Validación final de fase 3: 128 tests y 1074 assertions, incluyendo las 106 pruebas anteriores. Pint, cache de vistas/rutas y contratos OpenAPI/Postman verificados. Chromium: flujo proveedor/buyer con sesiones/CSRF reales y revisión responsive de escritorio/móvil. PostgreSQL 14 aislado; pendiente ejecutar suite en PostgreSQL 17 de Docker del usuario.
