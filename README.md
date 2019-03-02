# Migrations

Doctrine migrations form Nette Framework.

## Install

````sh
composer require chomenko/migrations
````

## Configuration

```neon
migrations:
    table: doctrine_migrations
    column: version
    directory: %appDir%/../Migrations
    dataDirs: []
    namespace: Migrations
    dataNamespace: MigrationsData
```

## Commands

Commands list. ``php www/index.php list``

![.docs/commands.PNG](.docs/commands.PNG)


