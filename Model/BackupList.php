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

        $json_data = $this->getFile()->getContent();
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
     * @return \Gaufrette\File|boolean
     */
    public function getFile()
    {
        /**
         * @var $fs \Gaufrette\Filesystem
         */
        $fs = Config::get(Config::FILESYSTE);

        if ($fs->has($this->getFilename()) === false) {
            return false;
        }

        return $fs->get($this->getFilename());
    }

    protected function save()
    {
        /**
         * @var $fs \Gaufrette\Filesystem
         */
        $fs = Config::get(Config::FILESYSTE);
        $fs->write($this->getFilename(), (string)$this, true);
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