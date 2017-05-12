# SNBackupBundle

This BackupBundle can create backups from type daily, weekly, monthly or yearly. It'll save your default database and GaufretteFilesystems to tar.gz-archive in a GaufretteFilesystem.

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
    backup_folder: backup_fs
    databases: 
        - mydb                  # name of your doctrine database connection
    gaufrette_fs:
        - image_fs              # names of gaufrette filesystems wich should backuped
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

### Required SNDeployBundle

Get a list of all remote snapshots

    php bin/console sn:backup:restore --remote=[prod|test|dev]
    
Download current snapshot of your remote system

    php bin/console sn:backup:restore --remote=[prod|test|dev] c
    
## WebGUI

![webgui](Resources/doc/web_gui.jpg)

```yaml
SNBackupBundle:
    resource: "@SNBackupBundle/Controller/"
    type: annotation
    prefix: /backup
```
