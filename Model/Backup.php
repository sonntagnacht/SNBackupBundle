<?php
namespace SN\BackupBundle\Model;

use Gaufrette\File;
use SN\ToolboxBundle\Helper\CommandHelper;
use Symfony\Component\Filesystem\Filesystem;

/**
 * SNBundle
 * Created by PhpStorm.
 * File: Backup.php
 * User: thomas
 * Date: 08.03.17
 * Time: 09:42
 */
class Backup implements \JsonSerializable
{
    protected $filename = null;
    protected $version;
    protected $timestamp;
    protected $commit;

    public function remove()
    {
        /**
         * @var $fs \Gaufrette\Filesystem
         */
        $fs = Config::get(Config::FILESYSTE);
        $fs->delete($this->filename);
    }

    public function getFilename()
    {
        if ($this->filename == null) {
            return sprintf("%s.tar.gz", date("Y-m-d_H-i-s", $this->getTimestamp()));
        }

        return sprintf("%s.tar.gz", $this->filename);
    }

    public function archive_exists()
    {

        /**
         * @var $fs \Gaufrette\Filesystem
         */
        $fs = Config::get(Config::FILESYSTE);

        return $fs->has($this->getFilename());
    }

    /**
     * @param $fielname
     * @return $this
     */
    public function setFilename($fielname)
    {
        $this->filename = $fielname;

        return $this;
    }

    /**
     * @return File|boolean
     */
    public function getFile()
    {
        /**
         * @var $fs \Gaufrette\Filesystem
         */
        $fs   = Config::get(Config::FILESYSTE);
        $file = $fs->get($this->getFilename());

        if ($file->exists() === false) {
            return false;
        }

        return $file;
    }

    /**
     * @param \SplFileInfo $file
     */
    public function setFile(\SplFileInfo $file)
    {
        /**
         * @var $fs \Gaufrette\Filesystem
         */
        $fs = Config::get(Config::FILESYSTE);
        $fs->write($this->getFilename(), file_get_contents($file->getRealPath()), true);
    }

    /**
     * @return mixed
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @param string $dstFolder
     */
    public function extractTo($dstFolder)
    {
        $tmpFile = sprintf("/tmp/%s.tar.gz", md5(time()));

        /**
         * @var $gfs \Gaufrette\Filesystem
         */
        $gfs = Config::get(Config::FILESYSTE);
        $fs  = new Filesystem();
        $fs->dumpFile($tmpFile, $gfs->read($this->getFilename()));

        $cmd = sprintf("tar xfz %s -C %s; rm -rf %s",
            $tmpFile,
            $dstFolder,
            $tmpFile
        );
        CommandHelper::executeCommand($cmd);

        $fs->remove($tmpFile);
    }

    public function insertFrom($srcFolder)
    {
        $tmpFile = sprintf("/tmp/%s.tar.gz", md5(time()));

        $cmd = sprintf("cd %s; tar -czf %s *", $srcFolder, $tmpFile);
        CommandHelper::executeCommand($cmd);

        /**
         * @var $gfs \Gaufrette\Filesystem
         */
        $gfs = Config::get(Config::FILESYSTE);

        $gfs->write(file_get_contents($tmpFile), $this->getFilename(), true);
    }

    /**
     * @param mixed $version
     */
    public function setVersion($version)
    {
        $this->version = $version;
    }

    /**
     * @return mixed
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * @param mixed $timestamp
     */
    public function setTimestamp($timestamp)
    {
        $this->timestamp = $timestamp;
    }

    /**
     * @return mixed
     */
    public function getCommit()
    {
        return $this->commit;
    }

    /**
     * @param mixed $commit
     */
    public function setCommit($commit)
    {
        $this->commit = $commit;
    }

    function jsonSerialize()
    {
        return [
            "timestamp" => $this->getTimestamp(),
            "version"   => $this->getVersion(),
            "commit"    => $this->getCommit()
        ];
    }

}