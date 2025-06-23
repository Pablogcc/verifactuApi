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

## Comando automático para firmar todos los XML (FUNCIONA)

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

## Comando para firmar un solo XML (FUNCIONA)

```sh
php artisan firma:xml {numSerie}
```

## Generar una factura Xml por su id (FUNCIONA)

```sh
hp artisan factura:xml {id}(ejemplo: ID12345)
```

## Generar todas las facturas xml (FUNCIONA)

```sh
php artisan factura:xml:all
```

## Firmar los xml generados (FUNCIONA)

```sh
php artisan firma:xml
```