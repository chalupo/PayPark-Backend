# Actividad 3.1 — Bitácora de inicialización de PayPark Backend

Fecha: 30 de octubre de 2026.
Materia: Proyecto VIII, Licenciatura en Desarrollo de Sistemas Web.

## Preparación y alcance

El repositorio original contenía únicamente `README.md`, con el título `# PayPark-Backend`, y estaba limpio. Se conservó ese contenido y se añadieron instrucciones de instalación y verificación. No había instrucciones locales AGENTS.md antes de la inicialización.

Se instaló la plantilla oficial `laravel/laravel` v13.11.0, con Laravel Framework 13.39.0 resuelto en `composer.lock`. Se mantuvieron la estructura, las migraciones y las dos pruebas estándar. No se implementaron vehículos, accesos, pagos, autenticación adicional ni reglas de negocio. La comunicación REST/JSON y el frontend futuro Vue.js permanecen como contexto del proyecto; no se desarrollaron en esta actividad.

Se omitieron los archivos de mantenimiento de la distribución de Laravel (`.github/`, CHANGELOG.md, .styleci.yml e instrucciones genéricas AGENTS.md/CLAUDE.md). No se añadió Boost ni infraestructura adicional al proyecto académico.

## Herramientas y dificultades del entorno

El entorno Debian 13 no tenía PHP ni Composer (`command not found`). No había sudo y la instalación de paquetes del sistema no estaba permitida. Se utilizó Docker como herramienta temporal de ejecución, sin añadir Docker como requisito del proyecto.

Imagen utilizada: `composer:2`, digest `sha256:4ed71c38c6d788eb4e4e3747b5332f5b40d5b5a6fcadd5062a1a70986a118e0a`.

- PHP CLI: 8.5.12, con PDO SQLite y sqlite3 disponibles.
- Composer: 2.11.1.
- Laravel Framework: 13.39.0.
- El lock generado requiere PHP 8.4.1 o superior por las dependencias resueltas; se verificó con 8.5.12.

La descarga por defecto de Composer falló con HTTP 403 porque `api.github.com` no está habilitado en la política de red. El intento `--prefer-source` descargaba el historial completo y se interrumpió para usar una clonación superficial de la etiqueta oficial. Las distribuciones de dependencias se obtuvieron de `codeload.github.com`, permitido por el entorno, conservando repositorios y referencias exactas. Solo durante la instalación se cambiaron las URLs de distribución del lock y después se restauró el lock original de Composer. No se deshabilitó TLS ni se almacenaron credenciales o certificados del entorno en el repositorio.

Git dentro del contenedor mostró una advertencia de propiedad del directorio montado. Para la instalación se autorizó únicamente `/app/PayPark-Backend` como safe.directory dentro del contenedor temporal. El Git del repositorio se ejecutó desde el entorno anfitrión.

## Elección de SQLite y cambio de planeación

Originalmente el proyecto podía utilizar una base de datos como MySQL. Para esta implementación académica se decidió utilizar SQLite para reducir configuración y dependencias externas y facilitar una demostración del MVP en el tiempo disponible.

Este cambio no modifica las reglas de negocio ni la arquitectura lógica del Backend: se mantiene Laravel, la futura API REST/JSON y la separación del frontend Vue.js. La metodología prevista continúa siendo Scrum.

Se configuró `APP_NAME=PayPark` y `DB_CONNECTION=sqlite` en `.env.example`. `DB_DATABASE` queda sin definir para que Laravel utilice `database_path('database.sqlite')`, sin rutas absolutas del entorno. Se creó localmente `database/database.sqlite` con `touch`. `.env` contiene una clave generada localmente y no se versiona; `.env.example` mantiene `APP_KEY` vacía.

## Comandos utilizados

Inspección inicial:

```bash
pwd
rg --files
git status --short
php -v
composer --version
```

Los comandos PHP y Composer posteriores se ejecutaron en un contenedor temporal con el repositorio montado en `/app/PayPark-Backend`. Para descargas se montó el certificado CA del entorno en modo lectura y se usaron `COMPOSER_CAFILE` y `GIT_SSL_CAINFO`, manteniendo el proxy configurado.

Preparación de la plantilla y dependencias:

```bash
# Intentos iniciales, afectados por las restricciones descritas:
composer create-project laravel/laravel work/laravel --no-interaction
composer create-project laravel/laravel work/laravel --prefer-source --no-interaction

# Plantilla finalmente utilizada, fuera del repositorio:
git clone --depth 1 --branch v13.11.0 https://github.com/laravel/laravel.git work/laravel
# Se retiró el .git de la copia temporal y se integró la plantilla,
# restaurando README.md desde el commit original.
composer update --no-install --no-scripts --no-interaction
```

Adaptación temporal para instalar en esta red:

```python
# Sobre una copia local de composer.lock, respaldando primero el original:
# Para cada paquete de packages y packages-dev se sustituyó únicamente:
# https://api.github.com/repos/OWNER/REPO/zipball/REFERENCE
# por https://codeload.github.com/OWNER/REPO/zip/REFERENCE
# Las versiones y referencias no cambiaron.
```

```bash
git config --global --add safe.directory /app/PayPark-Backend
composer install --no-interaction --prefer-dist
# Se restauró composer.lock original antes de las comprobaciones finales.
```

Configuración y validación, desde la raíz del proyecto:

```bash
cp .env.example .env
touch database/database.sqlite
php artisan key:generate --no-interaction
php artisan migrate --no-interaction
php artisan test
php artisan about
php artisan migrate:status
composer check-platform-reqs
composer validate --no-check-publish
git check-ignore .env database/database.sqlite vendor/autoload.php
git status --short
git diff --check
git add .
git diff --cached --name-only
git commit -m "chore: initialize Laravel backend with SQLite"
git status --short
```

## Resultado de migraciones

`php artisan migrate --no-interaction` terminó correctamente y creó la tabla de control de migraciones. Se aplicaron las tres migraciones estándar:

| Migración | Lote | Estado |
| --- | --- | --- |
| 0001_01_01_000000_create_users_table | 1 | Ran |
| 0001_01_01_000001_create_cache_table | 1 | Ran |
| 0001_01_01_000002_create_jobs_table | 1 | Ran |

`php artisan migrate:status` confirmó estos tres estados sin migraciones pendientes. No se añadieron migraciones propias.

## Resultado de pruebas y diagnóstico

`php artisan test`: **2 pruebas aprobadas, 2 aserciones**, duración 0.16 s:

- Tests\Unit\ExampleTest: that true is true.
- Tests\Feature\ExampleTest: the application returns a successful response.

`php artisan about` finalizó correctamente: aplicación PayPark, Laravel 13.39.0, PHP 8.5.12, Composer 2.11.1, entorno local y controlador de base de datos sqlite. Cache, sesiones y colas utilizan las tablas estándar de la base local. El modo debug está habilitado para desarrollo local.

`composer check-platform-reqs` aprobó todos los requisitos y `composer validate --no-check-publish` confirmó que composer.json es válido.

## Revisión de Git

`.gitignore` excluye `.env`, variantes locales de configuración (exceptuando `.env.example`), bases SQLite, vendor, node_modules, logs, claves de storage, cachés y artefactos de compilación. Los directorios de almacenamiento conservan los .gitignore estándar de Laravel.

Se revisó el índice (58 archivos) antes del commit para confirmar que solo contiene código, configuración de ejemplo, lock de dependencias y documentación; sin `.env`, bases locales, credenciales ni dependencias instaladas. Se retiraron tres espacios finales de líneas vacías de la vista estándar detectados por git diff --cached --check. El commit de esta etapa utiliza el mensaje `chore: initialize Laravel backend with SQLite`.


## Etapa 2. Diseño e implementación del modelo de datos

Fecha: 30 de octubre de 2026.

### Modelos y migraciones

Se crearon los modelos Eloquent `Vehicle`, `Access` y `Payment`, con asignación masiva limitada a sus campos y timestamps estándar. Se mantuvo el modelo `User` de Laravel y se agregó únicamente `vehicles()`; no se añadió role porque esta etapa no requiere permisos.

Se añadieron estas migraciones, en orden de dependencia:

- `2026_10_30_000001_create_vehicles_table`: id, user_id, plate única, brand, model, color y timestamps.
- `2026_10_30_000002_create_accesses_table`: id, vehicle_id, entry_at, exit_at nullable, status y timestamps.
- `2026_10_30_000003_create_payments_table`: id, access_id único, amount, paid_at, status y timestamps.

Todas incluyen clave primaria y clave foránea hacia su registro padre. Se conserva el comportamiento restrictivo predeterminado al borrar un padre con registros asociados, sin borrados en cascada. Las migraciones incluyen down() para eliminar sus tablas en una reversión.

### Relaciones establecidas

| Relación | Método en el padre | Método inverso |
| --- | --- | --- |
| Usuario tiene varios vehículos | User::vehicles() — HasMany | Vehicle::user() — BelongsTo |
| Vehículo tiene varios accesos | Vehicle::accesses() — HasMany | Access::vehicle() — BelongsTo |
| Acceso tiene un pago | Access::payment() — HasOne | Payment::access() — BelongsTo |

### Decisiones

- La placa es obligatoria y única en la base de datos; no se implementó normalización de placas.
- Los accesos permiten únicamente `active` y `closed`, con `active` como valor predeterminado; la migración enum genera la restricción correspondiente en SQLite.
- exit_at puede permanecer null. entry_at, exit_at y paid_at se convierten a datetime en Eloquent.
- access_id es único en payments para asegurar como máximo un pago por acceso, incluso al insertar directamente en la base.
- amount utiliza decimal(10, 2) y cast decimal:2 en Eloquent. No se añadieron cálculos de tarifas ni reglas de cobro. SQLite conserva su tipado numérico habitual; la declaración decimal no añade por sí sola precisión fija al motor.
- Payment::status es una cadena obligatoria; las pruebas usan `paid`. No se añadieron un flujo de estados de pago ni pasarelas reales.
- No se añadieron controladores ni endpoints API.

### Ejecución de migraciones y pruebas

Se confirmó que la base local no contenía usuarios antes de reconstruirla. Se utilizaron las dependencias ya instaladas y el contenedor temporal composer:2 de la etapa anterior para ejecutar PHP, sin modificar las dependencias del proyecto.

```bash
php artisan migrate:fresh --no-interaction
php artisan migrate:status
php artisan test
```

Resultado de migrate:fresh: **6 migraciones aplicadas correctamente**, las tres estándar y las tres nuevas. migrate:status confirmó las seis como **[1] Ran**, sin pendientes.

Se creó `tests/Feature/PayParkDataModelTest.php` con RefreshDatabase y SQLite en memoria. Sus cinco pruebas verifican:

1. Creación de usuario, asociación de dos vehículos, dos accesos y un pago; persistencia y relaciones Eloquent en ambos sentidos; fechas, estado predeterminado y salida nullable.
2. Rechazo de una placa duplicada, incluso para otro usuario.
3. Rechazo de un segundo pago para el mismo acceso.
4. Rechazo de un vehículo con usuario inexistente por clave foránea.
5. Rechazo de un estado de acceso fuera de active/closed.

La suite completa terminó con **7 pruebas aprobadas y 24 aserciones**, incluyendo las dos pruebas originales. Duración registrada: 0.28 segundos.

### Dificultades reales

No se encontraron errores de migraciones ni de pruebas durante esta etapa. Se reutilizó Docker por la ausencia de PHP nativo ya documentada en la etapa 1; no fue necesario descargar nuevas dependencias ni resolver nuevos problemas de red.

### Revisión y commit

Se revisaron git status, los cambios y las exclusiones de archivos sensibles. `.env`, la base SQLite local, vendor y logs permanecen fuera del índice. Se conserva `.env.example` sin secretos.

Comandos de cierre:

```bash
git status --short
git diff --check
git check-ignore .env database/database.sqlite vendor/autoload.php
git add app/Models database/migrations tests/Feature/PayParkDataModelTest.php docs/ACTIVIDAD_3_1_BITACORA.md
git diff --cached --check
git diff --cached --name-only
git commit -m "feat: crear modelos y relaciones principales de PayPark"
git status --short
```

## Etapa 3. Desarrollo de la API de vehículos

Fecha: 30 de octubre de 2026.

### Controlador y rutas

Se creó `app/Http/Controllers/VehicleController.php` con los métodos index, store, show, update y destroy. Se reutiliza el modelo Vehicle y el enlace implícito de modelos de Laravel para encontrar vehículos por id y devolver 404 cuando no existen.

Se creó `routes/api.php` con `Route::apiResource('vehicles', VehicleController::class)` y se registró ese archivo en bootstrap/app.php. Laravel aplica el prefijo /api y el grupo de middleware API. No fue necesario instalar paquetes adicionales.

| Método | Endpoint | Acción | Respuesta correcta |
| --- | --- | --- | --- |
| GET | /api/vehicles | Listar vehículos ordenados por id | 200 |
| GET | /api/vehicles/{vehicle} | Consultar por id | 200 |
| POST | /api/vehicles | Registrar | 201 |
| PUT o PATCH | /api/vehicles/{vehicle} | Actualizar | 200 |
| DELETE | /api/vehicles/{vehicle} | Eliminar | 200 |

### Validaciones y respuestas JSON

Las validaciones se comparten en un método privado del controlador, sin capas adicionales:

- user_id es requerido, entero y debe existir en users.
- plate es requerida, de tipo string, máximo 255 caracteres y única en vehicles.
- Al actualizar, la regla unique ignora únicamente el vehículo enlazado por Laravel; permite conservar su propia placa y rechaza la de otro vehículo.
- brand, model y color son requeridos, de tipo string y máximo 255 caracteres.
- Tanto PUT como PATCH requieren todos los campos indicados, conforme a las validaciones solicitadas para esta etapa.
- Solo los datos validados se envían al modelo.

Las consultas, la creación y la actualización devuelven `{ "data": ... }`. El listado contiene un arreglo, sin paginación adicional para este MVP escolar. La eliminación devuelve `{ "message": "Vehículo eliminado correctamente." }`.

Laravel devuelve JSON con message y errors para validaciones (422), y message para registros no encontrados (404). Se conserva la configuración existente para responder JSON en /api/* incluso sin cabecera Accept.

La eliminación respeta las claves foráneas ya existentes: si el vehículo tiene accesos asociados, devuelve 409 con un mensaje claro y conserva el registro, evitando una respuesta 500 por la restricción de SQLite. No se implementaron operaciones de accesos, disponibilidad, entradas, salidas ni pagos, ni se añadieron reglas de cobro o autenticación.

### Rutas y pruebas realizadas

Se ejecutaron los comandos dentro del mismo contenedor temporal composer:2 utilizado en las etapas anteriores:

```bash
php artisan route --no-interaction
php artisan route:list --path=api/vehicles
php artisan test
```

`php artisan route` mostró la ayuda y los subcomandos del espacio route (route:cache, route:clear y route:list). Para inspeccionar los endpoints se ejecutó route:list: **5 rutas registradas**, incluyendo GET|HEAD en las consultas y PUT|PATCH en la actualización, todas apuntando a VehicleController.

Se añadió `tests/Feature/VehicleApiTest.php`, con RefreshDatabase y SQLite en memoria. Sus 13 pruebas cubren:

1. Registro válido y persistencia con respuesta 201.
2. Listado de vehículos.
3. Consulta por id.
4. Actualización PUT conservando la placa propia.
5. Actualización PATCH cambiando la placa.
6. Eliminación y comprobación de ausencia en la base.
7. Rechazo de placa duplicada al registrar.
8. Rechazo de campos obligatorios faltantes.
9. Rechazo de user_id inexistente al registrar.
10. Rechazo de la placa de otro vehículo al actualizar.
11. Validaciones de campos obligatorios y usuario existente al actualizar.
12. Respuestas 404 al consultar, actualizar o eliminar un vehículo inexistente; JSON incluso sin Accept en la consulta.
13. Respuesta 409 al intentar eliminar un vehículo con registros asociados.

Resultado de la suite completa: **20 pruebas aprobadas, 80 aserciones**, duración registrada 0.35 segundos. Incluye las siete pruebas de las etapas anteriores. No se modificaron migraciones ni se requirió reconstruir la base local para esta etapa.

### Dificultades reales

No hubo errores de implementación ni de pruebas. La única aclaración del comando solicitado fue que `php artisan route` presenta ayuda del espacio de comandos; se utilizó `php artisan route:list --path=api/vehicles` para verificar el registro efectivo de endpoints. Se continuó usando Docker porque PHP no está instalado de forma nativa en el entorno, como ya se documentó.

### Revisión de Git

Se revisaron git status, los cambios y los archivos preparados para el commit. `.env`, la base SQLite local y vendor permanecen ignorados, sin archivos sensibles versionados.

```bash
git status --short
git diff --check
git check-ignore .env database/database.sqlite vendor/autoload.php
git add app/Http/Controllers/VehicleController.php routes/api.php bootstrap/app.php tests/Feature/VehicleApiTest.php docs/ACTIVIDAD_3_1_BITACORA.md
git diff --cached --check
git diff --cached --name-only
git commit -m "feat: implementar API de vehículos"
git status --short
```
