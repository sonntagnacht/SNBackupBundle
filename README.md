# SNBackupBundle

## Configuration

config.yml

```yaml
sn_backup:
    database:
        host: "%database_host%"
        dbname: "%database_name%"
        user: "%database_user%"
        password: "%database_password%"
```

### Local folder

```yaml
sn_backup:
    backup_folder: "/var/backup"
```

### Gaufrette Filesystem

```yaml
knp_gaufrette:
    ...
    filesystems:
        backup_fs:
            adapter: ...

sn_backup:
    backup_folder: backup_fs
```

## Usage

Take a snapshot of your current webapplication

    php bin/console sn:backup:dump

Get a list of all snapshots

    php bin/console sn:backup:restore

Restore a saved snapshot

    php bin/console sn:backup:restore [id]