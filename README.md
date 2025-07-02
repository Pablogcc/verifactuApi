# Comandos de la terminal 

## Al bajártelo del repositorio de github, hay que hacer dos comandos

## Instalación del composer para la carpeta vendor

```sh
composer install
```

## Copiar el .env.example en el .env

```sh
cp .env.example .env
```

## Crear todas las tablas de la base de datos vacias

```sh
php artisan migrate
```

## Eliminar los campos de las tablas de la base de datos

```sh
php artisan migrate:fresh --seed
```

## Insertar valores a una tabla en específica

```sh
php artisan db:seed --class=EjemploSeeder
```

## Crea un command para una nueva función

```sh
php artisan make:command nombreEjemplo
```

## Comando automático para firmar todos los XML

```sh
php artisan schedule:work
```

## Comando para procesar los XML desbloqueados

```sh
php artisan facturas-procesar-inserts
```

## Comando para procesar los XML bloqueados

```sh
php artisan facturas:procesar:facturas-bloqueadas
```

## Comando para crear una factoria para el sider

```sh
php artisan make:factory FacturasFactory
```

## Iniciar el proyecto de laravel para las peticiones

```sh
php artisan serve
```