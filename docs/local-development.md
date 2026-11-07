# Guía de desarrollo local de Bidflow

SaaS B2B genérico de solicitudes de cotización (RFQ) y adjudicación para productos, servicios y obras de cualquier industria. Independiente de cualquier sistema origen: las referencias externas son datos, sin FK ni consultas a otras bases.

## Estado real

Fases 0 y 1 completas; fase 2 implementada: autenticación Sanctum, organización activa autorizada, creación/listado/detalle/edición de RFQ borrador, gestión de ítems, búsqueda de proveedores vinculados, invitaciones y publicación transaccional auditada. El flujo completo se puede ejecutar mediante API y desde la nueva interfaz administrativa Blade.

Se conservan los datos y tipos automotores de la demo inicial por compatibilidad. Las nuevas RFQ API usan `products`, `services` y `works`, sin detalles ni reglas sectoriales. La fase 3 agrega portal de proveedores, ofertas parciales, comparación, adjudicación distribuida y órdenes independientes. Emails y entregas de webhooks todavía no están implementados. La publicación persiste un evento en outbox, sin enviarlo.

PHP 8.4 · Laravel 13 · PostgreSQL 17 en Compose · Redis/Predis/Horizon · Scheduler · nginx · Mailpit. Dependencias fijadas en `composer.lock`. ULID en entidades de negocio; timestamps y conexión PostgreSQL en UTC. Blade inicial sin compilación de JavaScript.

## Fase 3: proveedores, ofertas y adjudicación parcial

Implementados portal independiente en **http://localhost:8080/portal/login**, identidad `SupplierUser`, borradores y presentación inmutable de ofertas parciales. El comprador abre **Ofertas y adjudicaciones** desde el detalle de una licitación, compara ofertas, cierra recepción y adjudica cantidades a varios proveedores. El estado `partially_awarded` conserva pendientes; `awarded` requiere cero pendiente. Se emiten órdenes separadas por proveedor en la misma transacción.

[Guía completa, rutas, payloads y prueba desde el navegador](phase-3.md) · [OpenAPI](openapi.yaml) · [Postman fase 3](postman/Bidflow-phase-3.postman_collection.json).

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan optimize:clear
# Opcional, sólo local: agrega datos ficticios sin alterar la demo anterior.
docker compose exec app php artisan bidding:seed-phase3-demo
```

El comando solicita una contraseña local y crea `comprador.fase3@example.test`, `portal1@example.test`, `portal2@example.test`, `portal3@example.test`. No hay contraseña predeterminada. Para provisionar un acceso a un proveedor existente: `php artisan bidding:supplier-user <supplier-ulid> <email> "Nombre"`.

**Validación fase 3:** 128 tests / 1074 assertions, Pint, vistas/rutas y contratos OpenAPI/Postman correctos. Chromium validó proveedor → oferta → comparación → adjudicación → órdenes con sesiones/CSRF reales en escritorio y móvil. Pruebas realizadas con PostgreSQL 14 aislado; ejecutar la suite en PostgreSQL 17 de Docker antes de usar las migraciones allí.

La adjudicación exige clave de operación; reintentos no duplican órdenes. Cantidades y dinero usan decimales exactos; FK compuestas y triggers PostgreSQL protegen pertenencias, capacidad e inmutabilidad. Las migraciones conservan y reconstruyen líneas históricas. No borrar volúmenes ni repetir la demo original. Correos, pagos, logística, selección automática y anulaciones siguen pendientes.

## Interfaz administrativa web

Abrir **http://localhost:8080/** o `/login`. Blade, Bootstrap 5.3.8 local y JavaScript vanilla; no requiere npm, compilación ni CDN. La API REST conserva sus 16 operaciones de fase 2 y agrega las de fase 3, con autenticación Bearer. No hay migraciones nuevas para esta interfaz.

1. Iniciar sesión con una cuenta existente. Si hay varias membresías activas, elegir organización; con una se resuelve automáticamente.
2. En **Resumen**, consultar indicadores reales de la organización: total, borradores, publicadas y en evaluación, además de las solicitudes recientes.
3. En **Licitaciones → Nueva licitación**, completar título, categoría genérica y fecha/hora futura (Buenos Aires); descripción, condiciones y referencia son opcionales.
4. En el detalle, **Agregar ítem**: descripción, cantidad, unidad y especificaciones. Se pueden editar o eliminar ítems de borrador sin dependencias.
5. **Invitar**: buscar proveedores habilitados, marcar participantes y, si corresponde, elegir un contacto. Guardar invitaciones antes de cambiar de página. Las ya invitadas se muestran identificadas; repetir no duplica registros.
6. **Revisar y publicar**: revisar cantidades, participantes y fecha límite, marcar la confirmación y publicar. Las validaciones de negocio aparecen en la página sin perder los datos del formulario.
7. La licitación publicada queda en modo de consulta, con sus ítems e invitados. No se envían correos; las ofertas se reciben mediante el portal de fase 3.

Owner/admin/operator pueden operar; viewer ve información sin controles de escritura y los endpoints rechazan intentos directos. Se verifica membresía en cada solicitud, incluso después de seleccionar organización. El cambio de organización vuelve al dashboard para evitar reutilizar rutas de una empresa anterior. Logout web no revoca tokens API.

**Validación de esta interfaz:** 106 tests y 885 assertions, Pint y Composer correctos. Incluye las 93 pruebas anteriores y 13 escenarios web. Flujo completo probado en Chromium con CSRF/sesiones reales y revisión responsive de escritorio y móvil. Las pruebas y la revisión usan bases aisladas; no modifican los datos del entorno Docker.

**Credenciales demo:** `operador1@example.test` y `operador2@example.test`, si se cargó el seeder. No existe una contraseña común: usar la contraseña local que ya asignaste o establecer una mediante Tinker siguiendo la sección Autenticación. No se modificaron contraseñas existentes ni se crearon usuarios en tu base Docker.

Rutas web nuevas (relativas a la raíz, fuera de `/api/v1`):

| Método | Ruta | Pantalla u operación |
| --- | --- | --- |
| GET | `/`, `/login` | Login; si ya hay sesión, redirige al dashboard |
| POST | `/login`, `/logout` | Abrir/cerrar sesión |
| GET / POST | `/organizations` | Elegir organización propia activa |
| GET | `/dashboard` | Resumen de la organización |
| GET / POST | `/tenders` | Lista filtrada / creación |
| GET | `/tenders/create` | Formulario de nueva RFQ |
| GET / PATCH | `/tenders/{tender}` | Detalle / actualización |
| GET | `/tenders/{tender}/edit` | Editar borrador |
| GET / POST | `/tenders/{tender}/items/create`, `/tenders/{tender}/items` | Formulario / alta de ítem |
| GET | `/tenders/{tender}/items/{item}/edit` | Editar ítem |
| PATCH / DELETE | `/tenders/{tender}/items/{item}` | Actualizar / eliminar ítem |
| GET | `/suppliers` | Proveedores habilitados, búsqueda y paginación |
| GET | `/tenders/{tender}/suppliers` | Selección de proveedores para invitar |
| POST | `/tenders/{tender}/invitations` | Guardar invitaciones |
| GET / POST | `/tenders/{tender}/publish` | Confirmación / publicación |

Los formularios usan CSRF; no se permite publicar por GET. Fechas de pantalla en Buenos Aires, persistencia UTC. Los campos de condiciones y especificaciones agregan texto conservando otras propiedades cargadas por API. Las solicitudes demo legacy se pueden consultar/editar sin convertir su clasificación.

## Actualizar un entorno existente

No volver a sembrar ni borrar la base. La nueva migración conserva los datos existentes y agrega los tipos genéricos y la tabla de tokens Sanctum.

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan optimize:clear
docker compose exec app php artisan test
docker compose exec app vendor/bin/pint --test
```

Reiniciar procesos persistentes tras cambiar código/configuración: `docker compose restart app worker scheduler`.

## Inicio local desde cero

Requisitos: Docker Engine, Compose v2, puertos 8080 y 8025 libres. No se requiere PHP/Composer en el host.

```bash
cp .env.example .env
cp .env.testing.example .env.testing
# Linux: ajustar LOCAL_UID/LOCAL_GID en .env si no son 1000.
docker compose build app
docker compose up -d postgres redis mailpit
docker compose run --rm app composer install --no-interaction
docker compose run --rm app php artisan key:generate
docker compose run --rm app php artisan migrate
docker compose run --rm app php artisan bidding:seed-demo --profile=demo
docker compose up -d app nginx worker scheduler
```

Web: http://localhost:8080 · API: http://localhost:8080/api/v1 · Salud: `/api/v1/health` y `/up` · Mailpit: http://localhost:8025. La página inicial muestra el login web; luego se accede al dashboard administrativo. Los servicios de datos no publican puertos. Las credenciales de `.env.example` son locales.

El volumen PostgreSQL conserva datos. `docker/postgres/init-testing.sql` crea `bidflow_testing` al inicializar un volumen nuevo. Si un volumen previo no la tiene: `docker compose exec postgres createdb -U bidflow bidflow_testing`.

## Autenticación y organización activa

Usuarios existentes y Laravel Sanctum con Bearer tokens, vigencia de 8 horas, capacidades `rfq:read`/`rfq:write` y sólo hash persistido. No se comparten sesiones web. Logout revoca sólo el token actual. No hay registro público, refresh tokens ni administración API de usuarios/membresías en esta fase. El login web usa sesiones separadas y nunca requiere copiar un token.

Los usuarios demo `operador1@example.test` y `operador2@example.test` tienen contraseñas aleatorias. Para probar login, un administrador debe asignar una contraseña local elegida por él. Por ejemplo, en `docker compose exec app php artisan tinker`:

```php
$user = App\Models\User::where('email', 'operador1@example.test')->firstOrFail();
$user->password = 'REEMPLAZAR-POR-TU-CONTRASENA-LOCAL';
$user->save(); // El cast hashed aplica el hash.
```

No hay contraseña predeterminada ni se modifica ninguna automáticamente al migrar.

Si Tinker informa `Writing to directory /.config/psysh is not allowed`, el usuario numérico del contenedor no tiene un directorio personal escribible. Compose configura rutas XDG temporales para PsySH. Para usarlo inmediatamente en un contenedor que ya estaba levantado:

```bash
docker compose exec -e XDG_CONFIG_HOME=/tmp/bidflow/config -e XDG_DATA_HOME=/tmp/bidflow/data app php artisan tinker
```

La configuración se aplica también a futuros contenedores al recrearlos con Compose. No requiere reconstruir la imagen. La configuración y el historial de Tinker en `/tmp` no persisten al recrear el contenedor.


```bash
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email":"operador1@example.test","password":"REEMPLAZAR-POR-TU-CONTRASENA-LOCAL","device_name":"local"}'
```

Respuesta: `access_token`, `token_type: Bearer`, `expires_at` y `data` del usuario con sus organizaciones/roles. No versionar tokens ni contraseñas. Login está limitado a 5 intentos/minuto por IP+email y 20/minuto por IP.

Enviar `Authorization: Bearer <token>` y `Accept: application/json`. Consultar `GET /auth/me` para conocer las membresías. En operaciones de negocio, `X-Organization-ID: <ulid>` selecciona una organización cuya membresía se verifica antes de abrir TenantContext y antes de resolver modelos de ruta. Si hay una sola organización activa se infiere; si hay varias se exige el selector. Sin membresía activa => 403.

`organization_id` en el body no autoriza nada: está prohibido, junto con campos de estado/creador/ID. Owner/admin/operator administran borradores; viewer tiene lectura. Una RFQ ajena responde 404. La API exige además las capacidades correspondientes del token.

## Flujo completo de RFQ

Rutas relativas a `/api/v1`. Contrato completo y errores en [OpenAPI](openapi.yaml). Importar [la colección Postman](postman/Bidflow-phase-2.postman_collection.json), configurar `email`/`password` y ejecutar **Flujo completo**. La colección conserva token e IDs como variables locales; si el usuario tiene varias organizaciones, seleccionar `organization_id` después de consultar `/auth/me`.

1. `POST /auth/login`; luego `GET /auth/me`.
2. `POST /tenders` con:

```json
{
  "title": "Compra de insumos de oficina",
  "type": "products",
  "currency": "ARS",
  "deadline_at": "2026-12-01T18:00:00Z",
  "conditions": {"delivery": "En oficina"},
  "source_system": "erp_cliente",
  "external_reference": "OC-2026-125"
}
```

3. `POST /tenders/{tender}/items` con:

```json
{
  "description": "Papel reciclado",
  "unit": "kg",
  "quantity": "1.250",
  "attributes": {"color": "blanco", "certification": "reciclado"}
}
```

`attributes` almacena especificaciones genéricas. Cantidad como string decimal positivo, hasta 9 enteros y 3 decimales; no se aceptan números JSON. `position` opcional se asigna al final. No hay precios en el pedido. `reference_code` y `notes` son opcionales.

4. `GET /suppliers?q=nombre` para seleccionar proveedores activos vinculados a la organización. Incluye contactos y capacidades compartidos de la red. No filtra por industria o tipo de RFQ.
5. `POST /tenders/{tender}/invitations` con:

```json
{"invitations":[{"supplier_id":"<ulid-proveedor>","contact_id":"<ulid-contacto-opcional>"}]}
```

Se puede omitir `contact_id`. El lote completo es transaccional y admite hasta 100 proveedores distintos. Deben estar activos el proveedor y su vínculo; el contacto debe pertenecer al proveedor. Se guardan `pending`, con deadline de la RFQ, sin email ni token. Repetir devuelve las invitaciones existentes sin cambiarlas o duplicar auditoría; duplicados dentro del lote se rechazan con 422.

6. `POST /tenders/{tender}/publish`, sin body. Exige borrador, deadline futuro, ítems y proveedores invitados; todas las invitaciones deben seguir pendientes, vigentes y con proveedor/vínculo activos. Guarda `published`, historial, auditoría y outbox en una sola transacción. Repetir retorna 409, sin duplicar eventos.

| Método | Ruta | Resultado |
| --- | --- | --- |
| POST | `/auth/login` | 200: token y usuario |
| GET | `/auth/me` | 200: usuario y organizaciones |
| POST | `/auth/logout` | 204: revoca el token actual |
| GET / POST | `/tenders` | 200: lista / 201: crea draft |
| GET / PATCH | `/tenders/{tender}` | 200: detalle / actualiza draft |
| GET / POST | `/tenders/{tender}/items` | 200: lista / 201: agrega |
| PATCH / DELETE | `/tenders/{tender}/items/{item}` | 200: actualiza / 204: elimina |
| GET | `/suppliers` | 200: proveedores disponibles |
| GET / POST | `/tenders/{tender}/invitations` | 200: lista / crea o recupera |
| POST | `/tenders/{tender}/publish` | 200: publica |

Listas de RFQ y proveedores paginadas: `q`, `page`, `per_page` (20 por defecto, máximo 100); RFQ además `status` y `type`. Colecciones: `data`; paginadas agregan `links` y `meta`. Detalle de RFQ incluye ítems e invitaciones. Respuesta individual: `data`.

Errores JSON: `message`, y `errors` por campo en 422. 401: autenticación; 403: membresía/rol/capacidad; 404: recurso ajeno/inexistente o ítem de otra RFQ; 409: estado incompatible o referencias que impiden el cambio; 422: datos/precondiciones inválidos; 429: rate limit de login.

Sólo borradores son editables. PATCH de deadline sincroniza sus invitaciones. No hay eliminación física de RFQ; se preservan auditoría y referencias. Ítems sin ofertas ni adjuntos pueden eliminarse en draft. Clasificaciones con registros dependientes no se convierten (409), preservando la demo legacy.

## Pruebas y desarrollo sin Docker

PHP 8.4 con PDO PostgreSQL, BCMath, Intl, DOM/XML, Mbstring, cURL, Fileinfo, Zip y PCNTL; Composer 2 y PostgreSQL accesible. Ajustar `.env`/`.env.testing` a bases exclusivas de Bidflow. No cambiar el PHP global del host.

```bash
php8.4 /usr/local/bin/composer install
php8.4 artisan key:generate
php8.4 artisan migrate
php8.4 artisan serve --host=127.0.0.1 --port=8080
php8.4 artisan test
php8.4 vendor/bin/pint --test
```

En Docker usar `docker compose exec app` y `php` en lugar de `php8.4`. Tests requieren PostgreSQL dedicado cuyo nombre termine en `_testing`; se rechazan otras bases antes de migrar. PHPUnit usa correo/caché en memoria y cola síncrona: no requiere Redis/SMTP. La suite anterior se conserva; la aserción de health para `/tenders` pasa de 404 a 401 al existir ahora la ruta autenticada.

Validación final de fase 2: **93 tests, 715 assertions**; Pint, Composer, configuración Compose, cacheo de rutas/configuración y referencias/payloads de OpenAPI/Postman correctos.

Pruebas web adicionales: login/logout, separación de Sanctum, CSRF, selección/revocación de membresía, aislamiento, roles, dashboard, filtros y flujo completo de creación/ítems/invitaciones/publicación.

Pruebas de API: autenticación real y revocación/expiración, selección de organización, ausencia de membresía, roles y capacidades, acceso cruzado, validación, estados no editables, ítems, proveedores, duplicados, lotes atómicos, precondiciones/publicación repetida, UTC y persistencia. Las pruebas de demo/integridad anteriores siguen ejecutándose.

## Demo heredada

```bash
php8.4 artisan bidding:seed-demo --profile=demo
```

También `development` y `stress`. Requiere `APP_ENV=local|testing` y `BIDDING_DEMO_ENABLED=true`; se rechaza producción. Todos los proveedores/precios son ficticios; referencias `DEMO-`, `is_demo=true`, correos `@example.test`. No genera tokens de portal, jobs ni llamadas externas. Faker usa semilla fija; IDs, fechas y secretos varían. Repetir rechaza el demo existente sin modificarlo. Usar otra base local nueva para otro perfil; nunca hay borrado automático.

| Perfil | Organizaciones | Proveedores | RFQ | Ofertas |
| --- | ---: | ---: | ---: | ---: |
| demo | 2 | 15 | 20 | 48 |
| development | 2 | 60 | 300 | 960 |
| stress | 2 | 250 | 5000 | 20000 |

## Arquitectura y pendientes

[Especificación actual](../docs.mc) · [Decisiones iniciales](decisions/0001-foundation.md) · [Decisiones fase 2](decisions/0002-generic-rfq-api.md) · [Interfaz Blade](decisions/0003-blade-admin.md) · [Adjudicación parcial](decisions/0004-partial-awards.md).

- TenantModel exige TenantContext; las FK compuestas protegen integridad entre organizaciones y licitaciones. No hay RLS. SQL directo queda reservado a migraciones/seeders/tests; no desactivar scopes en aplicación.
- User::organizations descubre membresías desde la identidad autenticada antes de abrir contexto. Recursos JSON enumeran campos explícitos.
- Acciones de escritura bloquean la RFQ dentro de transacciones y no permiten cambios sobre published. Estados y transiciones no se reciben como asignaciones libres del cliente.
- Dinero decimal, nunca float. Secretos de webhook cifrados, tokens sólo hash. Adjuntos privados en disco local. Colas con after_commit.
- Pendientes: cancelación/expiración automática, administración completa de usuarios/proveedores, revisiones/anulaciones, idempotencia general, emails, consumidor de outbox, webhooks y ejecución de órdenes.

Docker operativo y suite base 27/334 confirmados por el usuario. Esta sesión no tiene acceso al socket Docker: las verificaciones de fase 2 se ejecutan con PHP 8.4 y PostgreSQL 14 aislado; Compose/CI conservan PostgreSQL 17. No se modificó el entorno Docker ni se aplicaron migraciones sobre su base desde esta sesión.
