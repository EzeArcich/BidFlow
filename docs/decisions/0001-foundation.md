# 0001 — Base técnica y aislamiento

Fecha: 2026-11-06. Alcance histórico: fases 0 y 1. La orientación sectorial y los pendientes de autenticación de este registro fueron reemplazados por [0002](0002-generic-rfq-api.md) en fase 2; las decisiones de tenancy, ULID e integridad se mantienen.

## Decisiones

- Confirmado por el usuario: tenancy en base compartida y ULID.
- Selección técnica: Laravel 13 y PHP 8.4. El host tiene PHP 8.4 además de su PHP 8.2 predeterminado. Versiones de paquetes fijadas por Composer. Referencia: https://github.com/laravel/docs/blob/13.x/releases.md.
- PostgreSQL obligatorio, incluidas pruebas. Compose usa 17; validación local inicial sobre 14. JSONB, CHECK y FK compuestas son parte del contrato de persistencia.
- UTC en aplicación y fechas con zona horaria. Presentación argentina futura: America/Argentina/Buenos_Aires.
- IDs ULID son identificadores, no secretos de autorización. El portal usará tokens independientes de alta entropía almacenados sólo como hash.
- Contexto de organización explícito y scoped al ciclo de ejecución; consultas sin contexto fallan. No se toma automáticamente `organization_id` del cliente. Autenticación y selección autorizada de contexto se implementan en fase 2.
- FK compuestas impiden que ofertas, líneas, invitaciones, adjuntos, adjudicaciones, órdenes y persistencia de integración mezclen organizaciones o licitaciones.
- Proveedores globales y datos generales compartidos. Habilitación y notas por organización. Edición de la red global denegada a clientes hasta definir su administración. Nuevos contactos privados por cliente requieren ampliar el esquema.
- Una oferta por proveedor/RFQ y una adjudicación total por RFQ. Una orden por adjudicación y número único por organización. El generador transaccional de numeración anual pertenece a la fase 3.
- ARS es la única moneda inicial. Demo usa montos ficticios finales para comparación; no constituye decisión fiscal.
- Persistencia de outbox, webhooks e idempotencia preparada; su lógica de ejecución queda para las fases indicadas en el roadmap.
- Sin dependencias de catálogos, proveedores reales ni otra base de datos. No se agrega Laravel Boost ni herramientas de IA al producto: no son necesarias para este alcance.

## Pendiente antes de las fases siguientes

1. Confirmar el criterio al vencer una RFQ: propuesta, cerrar para evaluación si hay ofertas válidas y expirar si no las hay. La especificación original es ambigua; no se implementó expiración automática.
2. Definir permisos de adjudicación y transiciones de órdenes por rol; no se habilitan por inferencia. Las policies iniciales permiten lectura según membresía y edición de borradores para owner/admin/operator. Otras mutaciones permanecen denegadas hasta su implementación.
3. Autenticación: usuarios propios y credenciales por organización como propuesta inicial; no hay SSO ni tokens emitidos aún. Sanctum está instalado como base prevista para autenticación interna, sin rutas ni migraciones de tokens activas.
4. Tratamiento de IVA, adjuntos/retención, administración de datos compartidos del proveedor y revisiones de ofertas antes de producción.

## Límites

No hay RLS de PostgreSQL: el aislamiento de consultas se aplica en Eloquent y policies, con integridad referencial en DB. SQL directo requiere revisión explícita. Los CHECK validan estados permitidos, pero no sustituyen las transiciones de dominio, la cobertura de ítems, el cálculo de totales ni el lock transaccional de adjudicación que se construirán en fases 2 y 3.
