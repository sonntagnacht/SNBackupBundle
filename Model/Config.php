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

class Config
{

    const BACKUP_FOLDER = "backup_folder";
    const DATABASE      = "database";
    const GAUFRETTE     = "is_gaufrette";

    protected static $config = [];

    public static function setConfig(array $config)
    {
        self::$config = $config;

        return time();
    }

    public static function isGaufrette(ContainerInterface $container)
    {
        try {
            /**
             * @var $fs \Gaufrette\Filesystem
             */
            $container->get('knp_gaufrette.filesystem_map')
                ->get(self::$config["backup_folder"]);

            self::$config["is_gaufrette"] = true;
        } catch (\InvalidArgumentException $exception) {
            self::$config["is_gaufrette"] = false;
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