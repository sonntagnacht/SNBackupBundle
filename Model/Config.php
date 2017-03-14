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
    const DATABASE      = "database";
    const FILESYSTE     = "filesystem";

    protected static $config = [];

    public static function setConfig(array $config)
    {
        self::$config = $config;

        return time();
    }

    public static function isGaufrette(ContainerInterface $container)
    {
        try {
            self::$config["filesystem"] = $container
                ->get('knp_gaufrette.filesystem_map')
                ->get(self::$config["backup_folder"]);;
        } catch (\InvalidArgumentException $exception) {
            self::$config["filesystem"] = new Filesystem();
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
}