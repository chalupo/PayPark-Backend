# PayPark-Backend

Backend académico de PayPark para Proyecto VIII de la Licenciatura en Desarrollo de Sistemas Web. Laravel con SQLite; el futuro frontend Vue.js consumirá una API REST/JSON.

## Instalación local

Requisitos: PHP 8.4.1 o superior (verificado con PHP 8.5.12), Composer 2 y extensiones de Laravel, incluyendo PDO SQLite, mbstring y XML. No se requiere MySQL ni Node.js para verificar esta etapa.

```bash
composer install
cp .env.example .env
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate
php artisan test
php artisan about
php artisan migrate:status
php artisan serve
```

La página inicial estándar está disponible en http://localhost:8000. `.env`, `vendor/` y la base SQLite local no se versionan. `.env.example` no contiene claves ni credenciales y utiliza la ruta SQLite predeterminada de Laravel.

Esta etapa incluye únicamente la inicialización técnica y las migraciones y pruebas estándar. La bitácora está en [docs/ACTIVIDAD_3_1_BITACORA.md](docs/ACTIVIDAD_3_1_BITACORA.md).
