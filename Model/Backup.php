<?php
namespace SN\BackupBundle\Model;

use SN\ToolboxBundle\Helper\CommandHelper;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

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
    protected $version;
    protected $timestamp;
    protected $commit;

    public function getFilename()
    {
        return sprintf("%s.tar.gz", date("Y-m-d_H-i-s", $this->timestamp));
    }

    /**
     * @return \SplFileInfo
     */
    protected function getFile()
    {
        $finder = new Finder();
        $files  = $finder->name($this->getFilename())->in($this->getFilepath());

        return $files[0];
    }

    /**
     * @param \SplFileInfo $file
     */
    protected function setFile(\SplFileInfo $file)
    {
        $fs               = new Filesystem();
        $absoluteFilename = sprintf("%s/%s", $this->getFilepath(), $this->getFilename());
        $fs->dumpFile($absoluteFilename, file_get_contents($file->getRealPath()));
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
        $tmpFile          = sprintf("/tmp/%s.tar.gz", md5(time()));
        $absoluteFilename = sprintf("%s/%s", $this->getFilepath(), $this->getFilename());

        $fs = new Filesystem();
        $fs->copy($absoluteFilename, $tmpFile);

        $cmd = sprintf("tar xfz %s -C %s; rm -rf %s",
            $tmpFile,
            $dstFolder,
            $tmpFile
        );
        CommandHelper::executeCommand($cmd);
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