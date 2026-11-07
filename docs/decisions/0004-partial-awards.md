# 0004 — Fase 3: ofertas parciales y adjudicación distribuida

Fecha: 2026-11-06. Estado: implementado y validado localmente (entregas A–F).

## Hallazgos y evolución (A)

Los nombres reales son TenderInvitation, BidItem y WorkOrder, no Invitation/BidLine/Order. Award exige bid_id y UNIQUE(tender_id)/UNIQUE(bid_id); WorkOrder tiene UNIQUE(award_id). Estas garantías son incompatibles con las cantidades distribuidas solicitadas. No se crearán motores alternativos.

Award será un encabezado de operación confirmado con actor, motivo, moneda, total, snapshot, request_key y request_hash. Sus AwardLine identificarán organización/RFQ/ítem/oferta/línea de oferta/proveedor, cantidad, precio adjudicado, subtotal y snapshot. Una operación puede distribuirse entre proveedores; cada proveedor recibe una WorkOrder con WorkOrderLine exclusivamente propias. Las FK compuestas verificarán todas las pertenencias. El precio adjudicado puede diferir del ofertado por decisión expresa del comprador y queda congelado en el snapshot; no se modifica la oferta.

Los Award anteriores se marcan legacy, conservan bid_id, relaciones y restricciones de unicidad aplicadas mediante índices parciales. Se reconstruyen sus líneas y las de sus órdenes desde los BidItem existentes. Nuevas operaciones exigen request_key, permiten múltiples Award por RFQ y no usan bid_id de encabezado. Las factories históricas indican explícitamente legacy para conservar la prueba de compatibilidad del contrato anterior; las nuevas factories prueban el contrato distribuido. No se eliminan datos ni se reinicializan bases.

El estado parcialmente adjudicado será partially_awarded; closed indica evaluación sin asignaciones; awarded sólo cuando todos los ítems tienen pendiente cero. Se cierra recepción explícitamente mediante acción desde published; el vencimiento impide ofertas aun sin scheduler. Completar una adjudicación no se confunde con completar la ejecución de órdenes.

## Cantidades, dinero y concurrencia (C/E)

Solicitada = TenderItem.quantity. Ofertada = BidItem.quantity, positiva y hasta la solicitada, pudiendo omitir ítems. Adjudicada de ítem = SUM(AwardLine.quantity); pendiente = solicitada - adjudicada. Consumida de oferta = SUM(AwardLine.quantity por bid_item_id); disponible de oferta = ofertada - consumida. Total de oferta = suma de subtotales redondeados por línea; total adjudicado = suma de subtotales de AwardLine. Cantidades a escala 3, precios/importes a escala 2; BCMath y redondeo decimal half-up, nunca float. ARS debe coincidir en toda la cadena.

Transacciones bloquean primero Tender, luego ítems y líneas ofertadas en orden estable. Un trigger PostgreSQL valida las sumas contra solicitado y ofertado bajo el mismo bloqueo padre: no se depende sólo de validaciones anteriores a escribir. Las adjudicaciones confirmadas y sus líneas no se editan mediante ninguna ruta. request_key único por organización/RFQ, hash canónico del payload y respuesta con el mismo Award; payload distinto con la misma clave => 409. Reintentos no crean nuevas órdenes ni eventos. Las órdenes usan número derivado de Award ULID/proveedor, con unicidad por organización y adjudicación/proveedor, sin contador susceptible de carreras.

## Identidad y portal (B)

SupplierUser será una identidad autenticable separada, vinculada a un Supplier; guard de sesión propio y tokens API Sanctum propios. No hay registro público ni enlaces permanentes. Se provisiona de forma administrativa con contraseña hasheada, sin credenciales predeterminadas. Se verifica cuenta, proveedor, organización, vínculo e invitación vigentes por solicitud. Una sesión o token comprador no concede identidad de proveedor ni viceversa.

El proveedor descubre organizaciones mediante una relación anclada a su Supplier, sin desactivar scopes tenant. Cada ruta operativa valida ese vínculo antes de abrir TenantContext. Todas las consultas de invitaciones, ofertas, adjudicaciones y órdenes se restringen además por supplier_id, nunca sólo por organización. No se exponen ofertas de terceros ni adjudicaciones ajenas de una operación mixta.

## Oferta, evaluación y UX (C/D)

Una oferta por RFQ/proveedor conserva UNIQUE existente. Borradores reemplazan sus líneas de forma transaccional; envío definitivo congela snapshot y no permite edición. Se permite cotización parcial en ítems y cantidad, nunca después del deadline, sin invitación o con red/cuenta inactiva. Repetir envío retorna la misma oferta sin duplicar historial/auditoría. El comprador sólo compara ofertas presentadas; viewer consulta, owner/admin/operator cierran y adjudican con motivo explícito.

Blade y API llaman las mismas acciones. El comprador ve matriz por ítem/proveedor, cantidades ofertadas/adjudicadas/pendientes y órdenes; el proveedor ve únicamente su información. No hay selección automática, pagos, ejecución logística ni envíos externos. Se amplía la demo con un comando separado, aditivo, limitado a local/testing; la demo original y sus pruebas permanecen.

## Verificación y límites

Se ejecutaron regresiones, pruebas de integridad referencial/cantidades, reintentos/rollback, upgrade histórico y carreras reales con procesos/conexiones PostgreSQL independientes: 128 tests / 1074 assertions. Las carreras verifican espera real de bloqueo mediante pg_stat_activity. Chromium validó formularios y responsive con sesiones/CSRF reales. Docker no se supone accesible: se informa el backend realmente usado. El rollback de la migración se rechazará si contiene operaciones nuevas que no puedan representarse con el modelo antiguo; nunca se descartarán silenciosamente.

Migraciones aplicables: 200000_enable_partial_awards, 210000_create_supplier_users, 220000_protect_submitted_bids y 230000_protect_confirmed_awards (2026_11_06). Las funciones PostgreSQL usan CREATE OR REPLACE para tolerar la recreación de tablas de RefreshDatabase; los triggers permanecen ligados a sus tablas. No se utiliza migrate:fresh en bases de aplicación.

## Entregas y archivos principales

| Entrega | Implementación | Verificación |
| --- | --- | --- |
| A | Migración `200000_enable_partial_awards`, AwardLine, WorkOrderLine; relaciones plurales sin eliminar las legacy | FK, capacidad, factories, upgrade histórico y rollback de esquema sin datos nuevos |
| B | Migración `210000_create_supplier_users`, SupplierUser, guards, SupplierOrganization, PortalAccess, AuthController y vistas portal | Login web/API, logout, ausencia de identidad, revocación, aislamiento comprador/proveedor/organización |
| C | SaveSupplierBid, SaveBidRequest, BidPolicy, BidResource, migración `220000_protect_submitted_bids` | Borradores parciales, deadline, decimal, snapshot, inmutabilidad y reintentos; navegador real |
| D | EvaluateTender, EvaluationController API/web y `tenders/evaluation.blade.php` | Comparativa, ítems sin cotización, cantidades y ofertas propias/ajenas |
| E | AllocateAward, PartialAwardRequest, AwardResource, CloseTender, migración `230000_protect_confirmed_awards` | Adjudicación distribuida/parcial/completa, pending, límites, idempotencia, rollback y dos carreras PostgreSQL reales |
| F | WorkOrderResource, órdenes generadas en AllocateAward, PartialAwardsDemoSeeder, comandos, OpenAPI/Postman/README/docs.mc | Órdenes segregadas, FK/economía inmutable, demo aditiva, contratos, suite 128/1074 y Pint |

Pruebas nuevas en `tests/Feature/Phase3/PartialLifecycleTest.php` y `PostgresConcurrencyTest.php`. No se ejecutaron migraciones sobre el Docker del usuario. Guía de actualización y prueba funcional por entrega en [fase 3](../phase-3.md): acceso para B, borrador/presentación para C, matriz para D, cierre/adjudicación para E y órdenes/demo para F. La entrega A se aplica mediante las migraciones, no mediante una operación de pantalla.
