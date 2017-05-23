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


use Knp\Bundle\GaufretteBundle\FilesystemMap;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;

class Config
{

    const TARGET_FS  = "target_fs";
    const DATABASES  = "databases";
    const FILESYSTEM = "filesystem";
    const INCLUDE_FS = "include_fs";

    protected static $config = [];

    /**
     * @var FilesystemMap
     */
    protected static $filesystemMap;

    /**
     * @param array $config
     * @param FilesystemMap $filesystemMap
     */
    public static function setConfig(array $config, FilesystemMap $filesystemMap)
    {
        self::$config        = $config;
        self::$filesystemMap = $filesystemMap;
    }

    public static function get($key)
    {
        if (array_key_exists($key, self::$config)) {
            return self::$config[$key];
        }
    }

    /**
     * @return \Gaufrette\Filesystem
     */
    public static function getTargetFs()
    {
        return self::$filesystemMap->get(self::$config[self::TARGET_FS]);
    }

    public static function getFilesystem()
    {
        return self::get(self::FILESYSTEM);
    }

    public static function getDatabase()
    {
        return self::get(self::DATABASES);
    }

    public static function getGaufretteFs()
    {
        return self::get(self::INCLUDE_FS);
    }


}