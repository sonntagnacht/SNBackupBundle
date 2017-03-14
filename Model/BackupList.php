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


use Doctrine\Common\Collections\ArrayCollection;
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
    protected $list = array();

    public function __construct()
    {
        $this->list = new ArrayCollection();

        if (self::$instance instanceof self) {
            return self::$instance;
        }

        if ($this->getFile() == false) {
            return $this;
        }

        $json_data = file_get_contents($this->getFile());
        $json_data = json_decode($json_data, true);
        foreach ($json_data as $k => $v) {
            $backup = new Backup();
            $backup->setTimestamp($v["timestamp"]);
            $backup->setVersion($v["version"]);
            $backup->setCommit($v["commit"]);
            $this->list->add($backup);
        }
    }

    public function removeBackup(Backup $backup)
    {
        $this->list->removeElement($backup);
        $this->save();
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
    public function getFile()
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
        $this->list->add($backup);
        $this->save();
    }

    public function hasBackups()
    {
        return ($this->list->count() > 0);
    }

    /**
     * @param $timestamp
     * @return Backup
     */
    public function getBackup($timestamp)
    {
        /**
         * @var $backup Backup
         */
        foreach ($this->list as $backup) {
            if ($backup->getTimestamp() == $timestamp) {
                dump($backup);

                return $backup;
            }
        }
    }

    /**
     * @return array|Backup[]
     */
    public function getDumps()
    {
        return $this->list;
    }

    public function jsonSerialize()
    {
        return $this->list->toArray();
    }

    public function __toString()
    {
        return json_encode($this);
    }
}