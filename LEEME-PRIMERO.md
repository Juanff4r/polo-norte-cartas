# Cómo usar este paquete

Esta carpeta NO es un proyecto Laravel completo: contiene solo los archivos que tú aportas (capas, rutas, middleware, configuración). Se copia sobre un Laravel nuevo.

```bash
composer create-project laravel/laravel polo-norte-cartas
cd polo-norte-cartas

# 1. Copiar los archivos de este paquete encima (acepta sobrescribir bootstrap/app.php y AppServiceProvider.php)
cp -r /ruta/a/este/paquete/{app,bootstrap,config,routes,mock-soap,tests,postman,docs,README.md} .

# 2. Variables de entorno: añadir el bloque del proyecto
cat /ruta/a/este/paquete/.env.polonorte.example >> .env.example
cp .env.example .env
php artisan key:generate

# 3. Levantar
PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8081 mock-soap/server.php &
php artisan serve
```

Probado: la lógica de dominio (`php tests/run.php`, 38 pruebas) y la sintaxis de todos los archivos.
**No probado en vivo:** las piezas que dependen de Laravel (rutas, middleware, `bootstrap/app.php`, controlador). Se escribieron para Laravel 11 o superior. Ejecuta la colección de Postman antes de entregar y arregla lo que falle.
