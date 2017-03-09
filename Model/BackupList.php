<?php
/**
 * SNBundle
 * Created by PhpStorm.
 * File: BackupList.php
 * User: thomas
 * Date: 08.03.17
 * Time: 10:07
 */

namespace SN\BackupBundle\Model;


use Symfony\Component\Filesystem\Filesystem;

class BackupList implements \JsonSerializable
{
    /**
     * @var BackupList
     */
    private static $instance = null;

    /**
     * @var array
     */
    protected $list = ["dumps" => array()];

    public function __construct()
    {
        if (self::$instance instanceof self) {
            return self::$instance;
        }

        if ($this->getFile() == null) {
            return $this;
        }

        $json_data = file_get_contents($this->getFile()->getRealPath());
        $json_data = json_decode($json_data, true);
        foreach ($json_data["dumps"] as $k => $v) {
            $backup = new Backup();
            $backup->setTimestamp($v["timestamp"]);
            $backup->setVersion($v["version"]);
            $backup->setCommit($v["commit"]);
            $json_data["dumps"][$k] = $backup;
        }
        $this->list = $json_data;
    }

    public static function factory()
    {
        if (false === self::$instance instanceof BackupList) {
            self::$instance = new BackupList();
        }

        return self::$instance;
    }

    public function getFilename()
    {
        return "backup.json";
    }

    /**
     * @return \SplFileInfo|boolean
     */
    protected function getFile()
    {
        $file = new \SplFileInfo($this->getAbsolutepath());

        if ($file->isFile() === false) {
            return false;
        }

        return $file;
    }

    /**
     * @return string
     */
    protected function getAbsolutepath()
    {
        return sprintf("%s/%s", $this->getFilepath(), $this->getFilename());
    }

    /**
     * @return string
     */
    protected function getFilepath()
    {
        if (Config::get(Config::GAUFRETTE)) {
            return sprintf("gaufrette://%s", Config::get(Config::BACKUP_FOLDER));
        }

        return Config::get(Config::BACKUP_FOLDER);
    }

    protected function save()
    {
        $fs               = new Filesystem();
        $absoluteFilename = sprintf("%s/%s", $this->getFilepath(), $this->getFilename());
        $fs->dumpFile($absoluteFilename, $this);
    }

    public function addBackup(Backup $backup)
    {
        array_unshift($this->list["dumps"], $backup);
        $this->save();
    }

    public function hasBackups()
    {
        return (count($this->list) > 0);
    }

    /**
     * @return array|Backup[]
     */
    public function getDumps()
    {
        return $this->list["dumps"];
    }

    public function jsonSerialize()
    {
        return $this->list;
    }

    public function __toString()
    {
        return json_encode($this);
    }
}