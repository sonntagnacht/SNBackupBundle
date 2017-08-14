# SNBackupBundle
[![Latest Stable Version](https://poser.pugx.org/sonntagnacht/backup-bundle/v/stable.png)](https://packagist.org/packages/sonntagnacht/backup-bundle) [![Total Downloads](https://poser.pugx.org/sonntagnacht/backup-bundle/downloads.png)](https://packagist.org/packages/sonntagnacht/backup-bundle) [![License](https://poser.pugx.org/sonntagnacht/backup-bundle/license)](https://packagist.org/packages/sonntagnacht/backup-bundle)

This BackupBundle can create backups from type daily, weekly, monthly or yearly. It'll save your default database and GaufretteFilesystems to tar.gz-archive in a GaufretteFilesystem.

## Installtion

Run `composer require sonntagnacht/backup-bundle` to use SNBackupBundle in your Project.

## Configuration

Add to `AppKernel.php`

```php
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            new Symfony\Bundle\FrameworkBundle\FrameworkBundle(),
            new Symfony\Bundle\SecurityBundle\SecurityBundle(),
            new Symfony\Bundle\TwigBundle\TwigBundle(),
            // ...
            new SN\BackupBundle\SNBackupBundle(),
            // ...
            
        return $bundles;
    }
    // ...
}
```

config.yml

```yaml
knp_gaufrette:
    ...
    filesystems:
        backup_fs:
            adapter: ...

sn_backup:
    target_fs: backup_fs
    databases: 
        - mydb                  # name of your doctrine database connection
    include_fs:
        - image_fs              # names of gaufrette filesystems wich should backuped
```

## Usage

To take a backup of your current webapplication (database and gaufrette filesystems)

    php bin/console sn:backup:dump [daily|weekly|monthly|yearly]

For large Backups, we skipped an initial connection check because the connection might get lost until everything is stored in a `tar`
If you still want a check if the filesystem exists, execute the backup command with `--check-target-fs`

Get a list of all backups

    php bin/console sn:backup:restore

Restore a saved backup

    php bin/console sn:backup:restore [id]
    
Delete backups whiche are older than seven days.

    php bin/console sn:backup:cleanup [daily|weekly|monthly|yearly] 7d

## WebGUI

![webgui](Resources/doc/web_gui.jpg)

To use the webgui add following lines to `routing.yml`
```yaml
SNBackupBundle:
    resource: "@SNBackupBundle/Controller/"
    type: annotation
    prefix: /backup
```
