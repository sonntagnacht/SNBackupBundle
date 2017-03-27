# SNBackupBundle

This BackupBundle can create backups from type daily, weekly, monthly or yearly. It'll save your default database and GaufretteFilesystems to tar.gz-archive in a GaufretteFilesystem.

## Configuration

config.yml

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

    php bin/console sn:backup:dump [daily|weekly|monthly|yearly]

Get a list of all snapshots

    php bin/console sn:backup:restore

Restore a saved snapshot

    php bin/console sn:backup:restore [id]
    
Delete backups whiche are older than seven days.

    php bin/console sn:backup:cleanup [daily|weekly|monthly|yearly] 7d
    
## WebGUI

```yaml
SNBackupBundle:
    resource: "@SNBackupBundle/Controller/"
    type: annotation
    prefix: /backup
```