# Fase 3 — portal, ofertas y adjudicación distribuida

El portal y el panel comprador usan las mismas acciones que la API. No hay registro público, correo ni links permanentes. No se incorporaron pagos, facturación, logística, selección automática, revisiones de ofertas presentadas ni anulaciones de adjudicaciones.

## Actualización y acceso

Aplicar `docker compose exec app php artisan migrate` y `docker compose exec app php artisan optimize:clear`. Reiniciar app/worker/scheduler. Las cuatro migraciones `2026_11_06_2*` agregan datos, evolucionan restricciones y reconstruyen líneas históricas de adjudicaciones/órdenes. Conservan IDs y snapshots originales; congelan las ofertas históricas presentadas. El rollback se rechaza si existen adjudicaciones nuevas que el modelo anterior no puede representar. No volver a sembrar la demo original ni borrar volúmenes.

Comprador: `/login`, membresía owner/admin/operator para operar; viewer sólo consulta. Proveedor: `/portal/login`, identidad `SupplierUser` separada, perteneciente explícitamente a un proveedor. Para crearla sobre un proveedor existente:

```bash
docker compose exec app php artisan bidding:supplier-user <supplier-ulid> portal@example.test "Responsable de ofertas"
```

La contraseña se solicita en secreto (mínimo 12 caracteres) y se guarda hasheada. El proveedor, organización y vínculo deben estar activos; además necesita una invitación propia no revocada. Cada request vuelve a verificar la autorización. No se desactivan scopes tenant.

Demo opcional y aditiva, exclusivamente local/testing con `BIDDING_DEMO_ENABLED=true`:

```bash
docker compose exec app php artisan bidding:seed-phase3-demo
```

Elegir una contraseña local de al menos 12 caracteres. Crea **sólo cuentas nuevas**: `comprador.fase3@example.test` y `portal1@example.test`, `portal2@example.test`, `portal3@example.test`. No altera cuentas, contraseñas ni licitaciones existentes. Si ya existe esta demo o sus identidades, rechaza repetirla sin cambios. Agrega una organización `DEMO Fase 3` con cuatro licitaciones genéricas: publicada con borradores, cerrada con ofertas presentadas, parcialmente adjudicada y completamente adjudicada. Las órdenes son ficticias; outbox se persiste sin transmitirlo.

## Probar desde el navegador

1. Como comprador, crear/publicar una licitación con ítems y proveedores invitados usando el flujo habitual.
2. En otro navegador o perfil, entrar a `/portal/login` como cada proveedor. En **Mis invitaciones**, abrir la solicitud y marcar los ítems a cotizar. Cantidad y precio se ingresan como decimales; se puede cotizar menos cantidad y omitir ítems. Guardar borrador antes de presentar.
3. Confirmar **Presentar oferta definitivamente**. El snapshot y las líneas quedan inmutables. Una oferta presentada se puede consultar, no reemplazar.
4. Como comprador, abrir la licitación → **Ofertas y adjudicaciones**. La matriz compara cantidades, precios, subtotales, plazos y condiciones; indica ofertas parciales, ítems sin cotización y cantidades pendientes.
5. Confirmar **Cerrar recepción**. Sólo se adjudica desde `closed` o `partially_awarded`; el deadline impide presentar ofertas aunque todavía no se haya cerrado manualmente.
6. Seleccionar líneas y cantidades por proveedor, precio adjudicado, motivo y confirmación. Una operación puede combinar varios ítems y proveedores. Queda un historial y una orden independiente por proveedor.
7. Repetir con los pendientes. `partially_awarded` conserva cantidades pendientes; `awarded` exige cero pendiente en todos los ítems. Cada proveedor ve exclusivamente sus líneas y órdenes, incluso cuando pertenecen a una operación mixta.

Ejemplo de demo: notebooks solicitadas 100; proveedor 1 ofrece 80 × ARS 1000, proveedor 2 ofrece 70 × ARS 950. Monitores solicitados 50; proveedor 1 ofrece 20 × ARS 210 y proveedor 3 ofrece 30 × ARS 200. Primera operación: 60 notebooks del primero, 40 del segundo, 30 monitores del tercero = **ARS 104000.00**, tres órdenes, quedan 20 monitores. Segunda operación: los 20 monitores del primero = **ARS 4200.00**, una cuarta orden, licitación `awarded`.

## Modelo y cantidades

- Solicitada: `TenderItem.quantity`.
- Ofertada: `BidItem.quantity`, positiva y hasta la solicitada; una oferta vigente por proveedor/RFQ conserva la unicidad existente.
- Adjudicada por ítem: suma de `AwardLine.quantity`, incluyendo operaciones previas.
- Pendiente: solicitada menos adjudicada.
- Disponible de una oferta: cantidad ofertada menos todas sus adjudicaciones previas.
- Subtotal: cantidad × precio, redondeo decimal half-up a dos decimales. Total: suma de subtotales ya redondeados. Cantidades a tres decimales. BCMath/NUMERIC, nunca floats.

Moneda única ARS, heredada de Tender. El comprador puede fijar un precio adjudicado distinto al ofertado con justificación; ambos quedan en snapshot. Cada `Award` es una operación confirmada; `AwardLine` referencia ítem solicitado y línea ofertada/proveedor exactos. `WorkOrder` se agrupa por proveedor y operación, con líneas referenciadas a `AwardLine`. No mezcla proveedores. No se modifican encabezados económicos ni líneas confirmadas; las órdenes no ofrecen endpoints de edición.

Las adjudicaciones legacy conservan su `bid_id` y unicidad mediante índices parciales. Las nuevas tienen `legacy=false` y `bid_id=null`; las relaciones nuevas son `Tender::awards()`, `Award::lines()`, `Award::workOrders()` y `WorkOrder::lines()`. Se mantienen las relaciones singulares anteriores para compatibilidad histórica; no usarlas para nuevas operaciones.

## Concurrencia e idempotencia

La transacción bloquea primero Tender y luego líneas/ítems. Los límites son acumulados por solicitado y por ofertado. PostgreSQL también protege la capacidad mediante trigger y las pertenencias mediante FK compuestas. El envío de oferta toma el mismo bloqueo de Tender que el cierre, evitando presentar después del cierre por carrera.

Adjudicar requiere `request_key` o header `Idempotency-Key`. La clave es única por organización/RFQ; se compara un hash canónico de actor, motivo y líneas normalizadas/ordenadas. Mismo contenido devuelve la misma adjudicación/órdenes, sin duplicar auditoría ni outbox. Otra carga con la misma clave retorna 409. Para otra adjudicación usar una clave nueva. No existe idempotencia global de todos los endpoints.

La generación de órdenes ocurre en la misma transacción: un fallo revierte adjudicación, líneas, órdenes, estado, auditoría y outbox. Las ofertas e invitaciones no se duplican por reintento.

## API REST

Contrato completo: [OpenAPI](openapi.yaml). Ejemplos importables: [Postman fase 3](postman/Bidflow-phase-3.postman_collection.json); configurar IDs y credenciales locales. La colección fase 2 sigue disponible para crear/publicar. Para comparar varios proveedores, repetir login/guardado/presentación con cada identidad antes del cierre.

Rutas relativas a `/api/v1`. Proveedor usa su token Sanctum, separado del comprador. Login proveedor recibe `email`/`password`, devuelve `access_token`, `token_type`, `expires_at`; vigencia 8 horas, sólo hash almacenado. Header `Accept: application/json`; escrituras JSON con `Content-Type: application/json`.

| Método | Ruta | Respuesta |
| --- | --- | --- |
| POST | `/portal/auth/login` | 200 token; 422 credenciales; 429 límite |
| POST | `/portal/auth/logout` | 204 revoca token actual |
| GET | `/portal/invitations` | 200 `data` con invitaciones propias y organización |
| GET | `/portal/{organization}/invitations/{invitation}` | 200 `data`: RFQ, ítems, oferta y adjudicaciones/órdenes propias |
| PUT | `/portal/{organization}/invitations/{invitation}/bid` | 201 alta / 200 reemplazo de borrador |
| POST | `/portal/{organization}/invitations/{invitation}/bid/submit` | 200 presentación; sin body |
| GET | `/tenders/{tender}/evaluation`, `/tenders/{tender}/bids` | 200 matriz `data.items` / `data.bids` |
| POST | `/tenders/{tender}/close` | 200; sin body |
| GET / POST | `/tenders/{tender}/awards` | 200 historial / 201 crea, 200 reintento |
| GET | `/tenders/{tender}/work-orders` | 200 colección de órdenes |
| GET | `/work-orders/{workOrder}` | 200 detalle de orden |

Comprador conserva `X-Organization-ID` autorizado y capacidades rfq:read/rfq:write. Proveedor selecciona organización en la ruta, verificada mediante su propio vínculo, sin confiar en body o headers como autorización. Error 401 sin identidad, 403 cuenta/rol sin permiso, 404 recurso ajeno/vínculo no disponible, 409 estado incompatible/clave alterada, 422 datos o capacidades de cantidades inválidos. Las colecciones nuevas no están paginadas en este MVP.

Guardar borrador parcial (IDs reales de la invitación):

```json
{"lines":[{"tender_item_id":"<item-ulid>","quantity":"80.000","unit_price":"1000.00","delivery_days":3,"conditions":"Entrega coordinada"}],"conditions":"Precio final ARS","warranty":"12 meses"}
```

Los strings decimales son obligatorios. `lines` reemplaza todo el borrador; máximo 1000 líneas distintas. Presentación congela `submitted_snapshot` con las condiciones y líneas vigentes.

Adjudicar (ID de línea **ofertada**, no de ítem solicitado):

```json
{"request_key":"compra-125-operacion-1","reason":"Distribución por capacidad de entrega","lines":[{"bid_item_id":"<linea-proveedor-1>","quantity":"60.000","unit_price":"1000.00"},{"bid_item_id":"<linea-proveedor-2>","quantity":"40.000","unit_price":"950.00"}]}
```

Respuesta `data`: ID de Award, estado `confirmed`, total, actor/fecha/motivo, snapshot, `lines` y `orders`. Historial y economía completos son exclusivos del comprador; portal devuelve únicamente filas del proveedor autenticado.

## Verificación

**Resultado:** 128 tests y 1074 assertions; Pint, cache de rutas/vistas y contratos válidos. Chromium verificó el flujo con sesiones/CSRF reales y pantallas de escritorio/móvil sin desbordamiento global.

Las pruebas incluyen regresiones, flujo web/API, identidad separada, revocación, aislamiento, snapshots, cantidades, integridad, rounding, idempotencia, rollback y compatibilidad de migración/demo. Las carreras usan procesos y conexiones PostgreSQL independientes, verificando que el segundo proceso efectivamente espere un bloqueo de fila (`pg_stat_activity`); cubren sobre-adjudicación y doble clic concurrente. Usan esquemas temporales propios dentro de una base terminada en `_testing`, sin modificar Docker ni datos de negocio.

La validación de esta sesión utiliza PHP 8.4 y PostgreSQL 14 local aislado. PostgreSQL 17 de Compose debe verificarse en el entorno Docker del usuario con la suite completa. No se aplicaron migraciones a esa base.
