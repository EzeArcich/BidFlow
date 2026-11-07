# Bidflow

- Usar `docs.mc` como especificación. Motor B2B genérico para productos, servicios y obras; independiente de cualquier sistema origen. No agregar tablas ni reglas sectoriales.
- Alcance actual: fases 0, 1, 2 y 3: API, panel Blade, portal proveedor, ofertas y adjudicación parcial. No implementar flujos de fases posteriores sin indicación.
- PHP 8.4, Laravel 13, PostgreSQL. Ejecutar comandos PHP con `php8.4` en este host o mediante Compose.
- Antes de abrir contexto, descubrir organizaciones mediante User::organizations(), anclado al usuario autenticado, sin desactivar scopes.
- Toda consulta de entidades tenant requiere `TenantContext::run()` con organización validada por autenticación y membresía. Nunca usar una organización del request sin autorizarla.
- No desactivar scopes de organización en código de aplicación. Query Builder y SQL directo quedan limitados a migraciones, seeders y pruebas de integridad.
- Proveedores globales: usar `visibleToCurrentOrganization()` y policies; no exponer listados globales ni editar contactos compartidos desde un cliente.
- Web: sesiones/CSRF separadas de Sanctum, organización revalidada por request. Reutilizar acciones y queries compartidas; Bootstrap local, sin npm.
- Dinero decimal, restricciones en PostgreSQL y datos demo explícitos. No agregar APIs externas ni datos comerciales reales.
- Verificar `php8.4 artisan test` y `php8.4 vendor/bin/pint --test`. Las pruebas requieren una base dedicada terminada en `_testing`.

- Fase 3: SupplierUser y guards separados; no exponer ofertas/economía de otros proveedores. Award nuevo usa líneas y múltiples órdenes, no las relaciones singulares legacy. Adjudicar mediante AllocateAward con clave idempotente; no editar snapshots confirmados.
