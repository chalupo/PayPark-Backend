# PayPark Backend

PayPark es un sistema web para administrar un estacionamiento. Este repositorio contiene exclusivamente el Backend del MVP académico de la **Actividad 3.1**, desarrollado para **Proyecto VIII de la Licenciatura en Desarrollo de Sistemas Web**, con una planeación basada en Scrum.

El Backend permite registrar usuarios y vehículos, controlar entradas y salidas, calcular espacios disponibles y registrar pagos asociados a los accesos mediante una API REST con respuestas JSON.

**Vue.js corresponde al Frontend planeado y no forma parte de este repositorio.** Los archivos de recursos y la página de bienvenida son los de la plantilla de Laravel; no constituyen el Frontend de PayPark. El alcance implementado no incluye sensores, cámaras, reconocimiento automático de placas, barreras automáticas ni pasarelas reales de pago.

## Tecnologías y requisitos

| Tecnología | Uso / versión verificada |
| --- | --- |
| PHP | 8.5.12 en la verificación; el lock actual requiere PHP 8.4.1 o superior |
| Laravel | 13.39.0, estructura del Backend, validación, Eloquent y migraciones |
| Composer | 2.11.1 verificado; instalar con Composer 2 |
| SQLite | Base de datos relacional local |
| Laravel Sanctum | 4.3.3, autenticación API mediante tokens Bearer |
| API REST / JSON | Comunicación con clientes HTTP |
| PHPUnit | Pruebas automatizadas mediante `php artisan test` |

Se requieren las extensiones PHP habituales de Laravel y PHPUnit, incluyendo PDO SQLite, mbstring, XML/DOM, ctype, curl, fileinfo, OpenSSL y tokenizer. `composer check-platform-reqs` permite comprobar los requisitos instalados. No es necesario instalar MySQL, Node.js ni compilar recursos para ejecutar y probar la API.

Durante el desarrollo se utilizó un contenedor temporal disponible en el entorno porque PHP y Composer no estaban instalados directamente. Docker no es un requisito de instalación del proyecto ni se incluye infraestructura Docker en este repositorio.

## Instalación y configuración

Desde la raíz del repositorio, con PHP y Composer disponibles:

```bash
composer install
cp .env.example .env
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate
```

En PowerShell se puede sustituir `cp .env.example .env` por `Copy-Item .env.example .env`.

Comprueba estos valores de `.env` para desarrollo local:

```dotenv
APP_NAME=PayPark
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
DB_CONNECTION=sqlite
```

`APP_KEY` se genera con Artisan; no debe copiarse a `.env.example`. Deja `DB_DATABASE` sin definir: Laravel utiliza automáticamente `database/database.sqlite`. Si se necesita otra ubicación, configura `DB_DATABASE` con la ruta absoluta de un archivo SQLite existente. No hacen falta usuario, contraseña ni un servidor de base de datos. El archivo debe poder escribirse, al igual que `storage/` y `bootstrap/cache/`.

`.env.example` es la plantilla versionada sin credenciales. `.env`, la base SQLite local, dependencias y archivos de ejecución están excluidos mediante `.gitignore`.

Para revisar y aplicar migraciones posteriores:

```bash
php artisan migrate
php artisan migrate:status
```

Se incluyen siete migraciones: tres estándar de Laravel, vehículos, accesos, pagos y tokens de Sanctum. No es necesario ejecutar seeders para iniciar; el registro API permite crear usuarios.

## Iniciar el servidor

```bash
php artisan serve
```

La API estará disponible bajo `http://localhost:8000/api`. La raíz `http://localhost:8000` conserva la bienvenida estándar de Laravel. Las peticiones con cuerpo deben enviar `Content-Type: application/json`; se recomienda `Accept: application/json`.

## Funcionalidades y endpoints

| Módulo | Método | Endpoint | Finalidad |
| --- | --- | --- | --- |
| Autenticación | POST | /api/register | Registrar usuario y emitir token |
| Autenticación | POST | /api/login | Iniciar sesión y emitir token |
| Autenticación | GET | /api/user | Consultar usuario autenticado |
| Autenticación | POST | /api/logout | Revocar el token usado |
| Vehículos | GET | /api/vehicles | Listar |
| Vehículos | POST | /api/vehicles | Registrar |
| Vehículos | GET | /api/vehicles/{vehicle} | Consultar por id |
| Vehículos | PUT o PATCH | /api/vehicles/{vehicle} | Actualizar |
| Vehículos | DELETE | /api/vehicles/{vehicle} | Eliminar |
| Accesos | GET | /api/accesses | Listar historial |
| Accesos | GET | /api/accesses/{access} | Consultar por id |
| Accesos | POST | /api/accesses/entry | Registrar entrada |
| Accesos | POST | /api/accesses/{vehicle}/exit | Registrar salida del vehículo |
| Disponibilidad | GET | /api/availability | Consultar espacios |
| Pagos | GET | /api/payments | Listar |
| Pagos | GET | /api/payments/{payment} | Consultar por id |
| Pagos | POST | /api/payments | Registrar pago de un acceso |

Son **17 rutas API**; PUT y PATCH comparten una ruta de actualización. Los parámetros entre llaves corresponden a identificadores de los modelos.

### Autenticación

Registro recibe `name`, `email` y `password`. El nombre es obligatorio; el correo debe ser válido y único; la contraseña requiere al menos ocho caracteres. Login recibe `email` y `password` y comprueba las credenciales. Las contraseñas se almacenan mediante hash y no se incluyen en las respuestas.

Registro (201) y login (200) devuelven `data` con el usuario, `token` y `token_type: Bearer`. Envía el token recibido en la cabecera:

```http
Authorization: Bearer <token>
```

Solo `/api/user` y `/api/logout` exigen `auth:sanctum`. Las demás rutas conservan acceso público en este alcance académico; no se implementaron roles avanzados, permisos ni autorización por propietario. Logout elimina únicamente el token actual y devuelve 200; ese token deja de permitir acceso al endpoint protegido.

### Datos de las operaciones

| Operación | Cuerpo JSON |
| --- | --- |
| Registrar usuario | `{"name":"Ana","email":"ana@example.com","password":"password123"}` |
| Iniciar sesión | `{"email":"ana@example.com","password":"password123"}` |
| Crear o actualizar vehículo | `{"user_id":1,"plate":"ABC-123","brand":"Nissan","model":"Versa","color":"Azul"}` |
| Registrar entrada | `{"vehicle_id":1}` |
| Registrar salida | Sin cuerpo; el id del vehículo está en la ruta |
| Registrar pago | `{"access_id":1,"amount":"25.00"}` |

Estos valores son ejemplos: usa los identificadores que devuelva tu instalación. Tanto PUT como PATCH requieren todos los campos del vehículo. Las respuestas de recursos utilizan `data`; los errores incluyen `message` y, para validaciones, `errors`.

Códigos utilizados: 200 para consultas, actualización, eliminación, login y logout; 201 para creación; 401 si falta autenticación válida; 404 si no existe el recurso de una ruta; 409 para conflictos de acceso o eliminación; 422 para datos inválidos, credenciales incorrectas y pagos duplicados.

## Reglas principales de negocio

- Cada vehículo pertenece a un usuario existente y su placa debe ser única. No se puede eliminar un vehículo con accesos asociados.
- La capacidad es de **50 vehículos**. `available = 50 - occupied`, donde occupied cuenta accesos con `status=active`; no se guarda un contador manual.
- Se rechaza la entrada si el estacionamiento está lleno o si el vehículo ya tiene un acceso activo.
- La entrada guarda la fecha del servidor, `status=active` y `exit_at=null`.
- La salida requiere un acceso activo, asigna la fecha del servidor y cambia su estado a `closed`. Una nueva entrada es posible después de cerrar el acceso anterior.
- Cada acceso puede tener **un solo pago**. El importe debe ser numérico y positivo, con hasta dos decimales y máximo 99999999.99, conforme al campo decimal(10,2).
- Los pagos reciben `status=paid` y `paid_at` del servidor. Registrar un pago no modifica la disponibilidad ni exige que el acceso esté cerrado. No se calculan tarifas ni se procesa dinero mediante servicios externos.

Ejemplo de disponibilidad con tres accesos activos:

```json
{"capacity":50,"occupied":3,"available":47}
```

## Pruebas y diagnóstico

```bash
php artisan test
php artisan migrate:status
php artisan route:list --path=api
php artisan about
```

Resultado del cierre: **55 pruebas aprobadas y 287 aserciones**, siete migraciones aplicadas y 17 rutas API. La suite cubre modelos, validaciones, autenticación, CRUD de vehículos, capacidad, entradas/salidas, pagos y el flujo integral. Por defecto utiliza SQLite en memoria y RefreshDatabase para aislar los datos de prueba.

`php artisan route` muestra la ayuda del espacio de comandos; para listar rutas se utiliza `php artisan route:list`.

## Estructura general

| Ruta | Contenido |
| --- | --- |
| app/Http/Controllers/ | AuthController, VehicleController, AccessController y PaymentController |
| app/Models/ | User, Vehicle, Access y Payment, con relaciones Eloquent |
| routes/api.php | Las 17 rutas API |
| bootstrap/app.php | Registro de rutas y respuestas JSON de errores de autenticación |
| config/ | Configuración de Laravel y SQLite |
| database/migrations/ | Esquema relacional y tokens de Sanctum |
| database/factories/ | Factory estándar de usuarios utilizada en pruebas |
| tests/Feature/ | Pruebas de API, datos, autenticación e integración |
| tests/Unit/ | Prueba unitaria inicial de Laravel |
| docs/ | Bitácora y resumen académico de la Actividad 3.1 |

Relaciones principales: User tiene varios Vehicle; Vehicle tiene varios Access; Access tiene un Payment. Se conservan las claves foráneas y las restricciones únicas de placa y access_id del pago.

## Planeación y documentación

Se eligió **SQLite en lugar de una configuración con MySQL** por el alcance académico y el tiempo disponible. SQLite redujo la configuración necesaria y mantuvo una base relacional real con migraciones y relaciones de Laravel. El cambio no modificó las reglas de negocio ni la arquitectura lógica del Backend.

- [Bitácora de las etapas 1 a 7](docs/ACTIVIDAD_3_1_BITACORA.md).
- [Resumen académico para el reporte](docs/RESUMEN_ACTIVIDAD_3_1.md).
