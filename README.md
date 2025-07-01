# Comandos de la terminal 

## Crear todas las tablas de la base de datos vacias

```sh
php artisan migrate
```

## Eliminar todas las tablas de la base de datos

```sh
php artisan migrate:rollback
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


# OPCIONALES

## Comando para firmar un solo XML

```sh
php artisan firma:xml {numSerie}
```

## Generar una factura Xml por su id

```sh
hp artisan factura:xml {id}(ejemplo: ID12345)
```

## Generar todas las facturas xml

```sh
php artisan factura:xml:all
```

## Firmar los xml generados

```sh
php artisan firma:xml
```

## Iniciar el proyecto de laravel para las peticiones(http://127.0.0.1:8000)

```sh
php artisan serve
```

# Al descargarlo del reporistorio de github, hay que hacer dos comandos

## Instalacion del composer para la carpeta vendor

```sh
composer install
```

## Copiar el archivo .env.exmple en el .env

```sh
cp .env.example .env
```