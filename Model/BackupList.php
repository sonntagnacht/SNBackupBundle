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

    private $type = null;

    /**
     * @var array
     */
    protected $list = array();

    /**
     * @var array
     */
    protected $storage = array();

    public function __construct($type = null, $clean = false)
    {
        $this->storage = new ArrayCollection();
        $this->type    = $type;
        $this->list    = new ArrayCollection();

        if (false === $clean) {
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

                $this->storage->add($backup);
                if (isset($v["type"])) {
                    $backup->setType($v["type"]);
                    if (isset($v["type"]) && $type != null && $type != $v["type"]) {
                        continue;
                    }
                }
                $this->list->add($backup);
            }
        }
    }

    public function removeBackup(Backup $backup)
    {
        $this->list->removeElement($backup);
        $this->storage->removeElement($backup);
        $backup->remove();
        $this->save();
    }

    public static function factory($type = null)
    {
        if (false === self::$instance instanceof BackupList) {
            self::$instance = new BackupList($type);
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
        $fs = Config::getTargetFs();

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
        $fs = Config::getTargetFs();
        $fs->write($this->getFilename(), (string)$this, true);
    }

    public function addBackup(Backup $backup)
    {
        $this->list->add($backup);
        $this->storage->add($backup);
        $this->save();
    }

    public function findByDate($timestamp)
    {
        /**
         * @var $backup Backup
         */
        foreach ($this->list as $backup) {
            if ($backup->getDateTime() == $timestamp) {
                return $backup;
            }
        }

        return false;
    }

    public function sortByDate()
    {
        $iterator = $this->list->getIterator();
        $iterator->uasort(function ($a, $b) {
            /**
             * @var $a Backup
             * @var $b Backup
             */
            return ($a->getTimestamp() < $b->getTimestamp()) ? -1 : 1;
        });

        $this->list = new ArrayCollection(iterator_to_array($iterator));
        $this->save();
    }

    /**
     * @param string $filename
     * @return bool|Backup
     */
    public function findByFilename($filename)
    {
        /**
         * @var $backup Backup
         */
        foreach ($this->list as $backup) {
            if ($backup->getAbsolutePath() == $filename || $backup->getFile() == $filename) {
                return $backup;
            }
        }

        return false;
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
            if ($backup->getDateTime() == $timestamp) {
                dump($backup);

                return $backup;
            }
        }
    }

    /**
     * @return array|Backup[]|ArrayCollection
     */
    public function getDumps()
    {
        return $this->list;
    }

    public function setDumps(ArrayCollection $collection)
    {
        $this->list    = $collection;
        $this->storage = $collection;
        $this->save();
    }

    public
    function jsonSerialize()
    {
        return $this->storage->toArray();
    }

    public
    function __toString()
    {
        return json_encode($this);
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }
}