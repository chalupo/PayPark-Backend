# Resumen de la Actividad 3.1 — PayPark Backend

Proyecto VIII — Licenciatura en Desarrollo de Sistemas Web.

## Introducción

PayPark es un proyecto universitario orientado a la administración de un estacionamiento. La Actividad 3.1 se enfocó en implementar el Backend de un producto mínimo viable (MVP), con una solución sencilla, funcional y demostrable. La planeación considera Scrum y una separación entre Backend y Frontend mediante una API REST con intercambio de información en JSON.

Este repositorio contiene únicamente el Backend. Vue.js corresponde al Frontend planeado y no fue desarrollado como parte de esta actividad. Tampoco se implementaron sensores, cámaras, reconocimiento de placas, barreras automáticas ni pasarelas reales de pago.

## Preparación del Backend

Se partió de un repositorio que contenía un README y se integró la estructura oficial de Laravel, conservando la información inicial y ampliando posteriormente su documentación. Se verificaron PHP 8.5.12 y Composer 2.11.1 en las herramientas de ejecución disponibles. El proyecto utiliza Laravel 13.39.0 y el conjunto de dependencias fijado requiere PHP 8.4.1 o superior.

Se configuró `.env.example` para SQLite, sin credenciales ni clave de aplicación. El archivo `.env` y la base local se mantienen fuera del control de versiones. Se generó la clave local, se creó el archivo SQLite y se ejecutaron las migraciones y pruebas estándar antes de implementar los módulos.

## Diseño de la base de datos

Se mantuvo User de Laravel y se crearon los modelos Vehicle, Access y Payment. Un usuario puede tener varios vehículos; cada vehículo puede tener varios accesos; cada acceso puede tener un pago. Las relaciones inversas permiten recuperar el registro padre desde cada modelo.

Vehicle incluye usuario, placa, marca, modelo y color. Access registra vehículo, fecha de entrada, fecha de salida opcional y estado. Payment registra acceso, importe, fecha y estado del pago. Los modelos cuentan con identificadores y timestamps.

Las migraciones incorporan claves primarias, claves foráneas y restricciones únicas para la placa y el acceso asociado al pago. Los accesos admiten los estados active y closed. Se añadieron conversiones de fechas y del importe en Eloquent. Con la migración estándar de tokens de Sanctum, el proyecto final cuenta con siete migraciones, incluidas las tres iniciales de Laravel.

## Desarrollo de la API de vehículos

Se implementó VehicleController con operaciones de registro, listado, consulta individual, actualización y eliminación. Las rutas siguen la estructura `/api/vehicles` y utilizan Route::apiResource.

Se validan usuario existente, placa única y campos obligatorios de marca, modelo y color. La actualización permite conservar la placa del mismo vehículo y rechaza la de otro. Se devuelve un conflicto si se intenta eliminar un vehículo con accesos asociados, respetando las restricciones del modelo relacional.

## Control de entradas, salidas y disponibilidad

AccessController permite registrar entradas, registrar salidas y consultar el historial de accesos. La capacidad fija es de 50 vehículos. La disponibilidad se calcula como 50 menos la cantidad de accesos activos, sin almacenar un contador manual.

Se rechaza una entrada cuando el estacionamiento está lleno o cuando el vehículo ya tiene un acceso activo. La entrada utiliza la fecha del servidor, estado active y salida null. La salida requiere un acceso activo y lo cambia a closed, registrando la fecha correspondiente. El vehículo puede ingresar otra vez después de cerrar su acceso anterior.

Las operaciones se realizan en transacciones y SQLite se configura con modo IMMEDIATE y espera breve por bloqueo de escritura para comprobar y modificar los datos dentro de la misma transacción. No se realizaron pruebas de carga como parte de esta actividad.

## Desarrollo del módulo de pagos

PaymentController ofrece registro, listado y consulta individual mediante `/api/payments`. Cada pago requiere un acceso existente y un importe numérico mayor que cero. La API admite hasta dos decimales y el rango definido para decimal(10,2). El servidor asigna estado paid y fecha de pago.

Se mantuvo la decisión de un solo pago por acceso, mediante validación y restricción única en la base. No se exige que el acceso esté cerrado para pagar y el pago no modifica la disponibilidad. El módulo registra información; no calcula tarifas ni realiza cobros mediante plataformas externas.

## Implementación de autenticación

Se incorporó Laravel Sanctum 4.3.3 para autenticación con tokens personales Bearer. AuthController implementa registro de usuario, inicio de sesión, consulta del usuario autenticado y cierre de sesión.

El registro valida nombre, correo válido y único y contraseña de al menos ocho caracteres. El cast hashed de User almacena las contraseñas mediante hash y los atributos sensibles permanecen ocultos en JSON. Login comprueba el hash y rechaza credenciales incorrectas. Sanctum almacena el hash del secreto del token; logout revoca únicamente el token usado.

Solo `/api/user` y `/api/logout` requieren autenticación. Las rutas de los otros módulos conservaron su acceso público para mantener el alcance acordado. Se implementó autenticación básica suficiente para demostrar el MVP, sin un sistema avanzado de roles, permisos o autorización por propietario, que excedería el tiempo y alcance de esta actividad.

## Pruebas realizadas

Se desarrollaron pruebas de modelos, relaciones y API, utilizando PHPUnit y RefreshDatabase. Se verificaron las operaciones de vehículos, validaciones, códigos HTTP, capacidad máxima, rechazo de entradas y pagos duplicados y salidas sin acceso activo. También se comprobaron emisión y revocación de tokens y rechazo de peticiones no autenticadas.

La prueba integral crea un usuario, registra un vehículo, consulta disponibilidad, registra entrada y salida y comprueba sus efectos. Verifica el rechazo de una entrada repetida y una salida sin acceso activo; luego registra el pago, rechaza otro pago para el mismo acceso y consulta el pago y los listados históricos. El escenario de estacionamiento lleno prepara 50 accesos activos y verifica que una salida permita admitir otro vehículo.

En la etapa de integración se ejecutó la suite tanto con SQLite en memoria como con un archivo SQLite nuevo migrado, separado de la base local. Tras incorporar autenticación, el resultado final es de **55 pruebas aprobadas y 287 aserciones**. En el cierre se ejecutaron nuevamente migrate y la suite completa: las siete migraciones están aplicadas y no hay pendientes.

La revisión de rutas confirmó **17 rutas API**: cuatro de autenticación, cinco de vehículos, cuatro de accesos, una de disponibilidad y tres de pagos. PUT y PATCH comparten la ruta de actualización de vehículos.

## Dificultades encontradas y soluciones

1. **Herramientas no disponibles directamente.** El entorno inicial no contaba con PHP y Composer. Se utilizaron las herramientas disponibles mediante un contenedor temporal, sin incorporar Docker como requisito o infraestructura del proyecto.
2. **Restricción de red.** La descarga desde api.github.com devolvió un rechazo. Se obtuvo la plantilla oficial mediante Git y se utilizaron distribuciones oficiales desde codeload.github.com con las mismas referencias. Se restauró el lock original después de la adaptación temporal y se mantuvo la verificación TLS.
3. **Consulta de rutas.** php artisan route mostró la ayuda del espacio de comandos. La revisión efectiva se realizó con php artisan route:list.
4. **Precisión de fechas.** Dos pruebas compararon microsegundos del reloj congelado con valores almacenados en segundos. Se ajustó el reloj de prueba a segundos, sin modificar la lógica del sistema.
5. **Respuesta de autenticación.** Una petición sin token y sin Accept intentaba redirigir a un login web inexistente. Se ajustó la redirección para las rutas API y se conservó la respuesta JSON con código 401.

## Cambios respecto a la planeación

Se utilizó SQLite en lugar de una configuración con MySQL por el alcance académico y el tiempo disponible. Esto redujo la configuración y eliminó la necesidad de un servidor de base de datos externo, manteniendo una base relacional real.

Se continuaron utilizando migraciones, claves foráneas, restricciones y relaciones de Eloquent. La elección del motor no modificó las reglas de negocio ni la arquitectura lógica: el Backend permanece en Laravel y expone una API REST/JSON para un Frontend independiente planeado con Vue.js.

## Resultado final

El Backend incluye autenticación básica, administración de vehículos, entradas, salidas, disponibilidad y registro de pagos asociados a accesos. Cuenta con siete migraciones aplicadas, 17 rutas API y 55 pruebas aprobadas con 287 aserciones.

El repositorio incluye instrucciones de instalación y ejecución, configuración de ejemplo sin secretos y bitácora de las siete etapas. `.env`, la base SQLite local y las dependencias instaladas no se versionan. El cierre solo actualizó documentación y no añadió funcionalidades.

## Conclusión

La actividad permitió integrar un Backend funcional y comprobable dentro del alcance del MVP académico. Laravel, Eloquent, SQLite y Sanctum proporcionaron herramientas estándar para relacionar datos, validar operaciones y demostrar el flujo del estacionamiento. Las pruebas y la documentación permiten revisar los resultados y reproducir la ejecución sin requerir el Frontend planeado ni servicios externos de pago.
