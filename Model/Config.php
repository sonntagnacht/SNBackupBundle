<?php
/**
 * SNBundle
 * Created by PhpStorm.
 * File: Config.php
 * User: thomas
 * Date: 08.03.17
 * Time: 10:21
 */

namespace SN\BackupBundle\Model;


use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

class Config
{

    const BACKUP_FOLDER = "backup_folder";
    const DATABASES     = "database";
    const FILESYSTEM    = "filesystem";

    protected static $config = [];

    public static function setConfig(array $config)
    {
        self::$config = $config;

        return time();
    }

    public static function isGaufrette(ContainerInterface $container)
    {
        try {
            self::$config[self::FILESYSTEM] = $container
                ->get('knp_gaufrette.filesystem_map')
                ->get(self::$config[self::BACKUP_FOLDER]);
        } catch (\InvalidArgumentException $exception) {
            self::$config[self::FILESYSTEM] = new Filesystem();
        }

        return time();
    }

    public static function get($key)
    {
        if (array_key_exists($key, self::$config)) {
            return self::$config[$key];
        }

        return null;
    }

    public static function getBackupFolder()
    {
        return self::get(self::BACKUP_FOLDER);
    }

    public static function getFilesystem()
    {
        return self::get(self::FILESYSTEM);
    }

    public static function getDatabase()
    {
        return self::get(self::DATABASES);
    }


}