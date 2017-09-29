<?php

namespace SN\BackupBundle\Model;

use Gaufrette\File;
use SN\ToolboxBundle\Helper\CommandHelper;
use SN\ToolboxBundle\Helper\CommandLoader;
use SN\ToolboxBundle\Helper\DataValueHelper;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Stopwatch\Stopwatch;

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

    const TYPE_DAILY   = 'daily';
    const TYPE_WEEKLY  = 'weekly';
    const TYPE_MONTHLY = 'monthly';
    const TYPE_YEARLY  = 'yearly';

    protected static $tmpFolder = "/tmp/sn-backup";

    protected $filename = null;
    protected $version;
    protected $type = null;
    /**
     * @var \DateTime
     */
    protected $dateTime;
    protected $commit;

    public function __construct()
    {
        $this->dateTime = new \DateTime();
    }

    public function remove()
    {
        /**
         * @var $fs \Gaufrette\Filesystem
         */
        $fs = Config::getTargetFs();
        if ($fs->has($this->getAbsolutePath())) {
            return $fs->delete($this->getAbsolutePath());
        }

        return false;
    }

    public function getAbsolutePath()
    {
        return sprintf("%s/%s", $this->getType(), $this->getFilename());
    }

    public function getFilename()
    {
        if ($this->filename == null) {
            return sprintf("%s.tar.gz", date("Y-m-d_H-i", $this->getTimestamp()));
        }

        return sprintf("%s.tar.gz", $this->filename);
    }

    public function archive_exists()
    {

        /**
         * @var $fs \Gaufrette\Filesystem
         */
        $fs = Config::getTargetFs();

        return $fs->has($this->getAbsolutePath());
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
     * @return string
     */
    public function getFilesize($decimals = 2)
    {
        $bytes  = $this->getFile()->getSize();
        $size   = array(
            "Bytes",
            "kB",
            "MB",
            "GB",
            "TB",
            "PB"
        );
        $factor = floor((strlen($bytes) - 1) / 3);

        return sprintf("%.{$decimals}f %s", $bytes / pow(1024, $factor), $size[$factor]);

    }

    /**
     * @return File|boolean
     */
    public function getFile()
    {
        /**
         * @var $fs \Gaufrette\Filesystem
         */
        $fs = Config::getTargetFs();
        if ($fs->has($this->getAbsolutePath())) {
            return $fs->get($this->getAbsolutePath());
        }

        return false;
    }

    /**
     * @return \DateTime
     */
    public function getDateTime()
    {
        return $this->dateTime;
    }

    /**
     * @param \DateTime $dateTime
     */
    public function setDateTime(\DateTime $dateTime)
    {
        $this->dateTime = $dateTime;
    }

    /**
     * @param \SplFileInfo $file
     */
    public function setFile(\SplFileInfo $file)
    {
        /**
         * @var $fs \Gaufrette\Filesystem
         */
        $fs = Config::getTargetFs();
        $fs->write($this->getAbsolutePath(), file_get_contents($file->getRealPath()), true);
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
    public function extractTo($dstFolder, OutputInterface $output = null)
    {
        $tmpFile = sprintf("%s/sn-backup-%s-%s.tar.gz", self::$tmpFolder, $this->getType(), md5(time()));

        /**
         * @var $gfs \Gaufrette\Filesystem
         */
        $gfs = Config::getTargetFs();
        $fs  = new Filesystem();
        if ($output instanceof OutputInterface) {
            $output->writeln(sprintf("Downloading Backup (%s) to [%s]", $this->getAbsolutePath(), $dstFolder));
        }
        $fs->dumpFile($tmpFile, $gfs->read($this->getAbsolutePath()));
        try {
            $fs->remove($dstFolder);
        } catch (\Exception $e) {
        }
        $fs->mkdir($dstFolder);

        $cmd = sprintf("tar xfz %s -C %s; rm -rf %s",
            $tmpFile,
            $dstFolder,
            $tmpFile
        );

        if ($output instanceof OutputInterface) {
            CommandHelper::execute($cmd,
                array(
                    "output"       => $output,
                    "idle_timeout" => 1800
                ));
        } else {
            CommandHelper::execute($cmd,
                array(
                    "idle_timeout" => 1800
                ));
        }

        $fs->remove($tmpFile);
    }

    public function insertFrom($srcFolder, OutputInterface $output = null)
    {
        /**
         * @var $gfs \Gaufrette\Filesystem
         */
        $tmpFile = sprintf("%s/sn-backup-%s-%s.tar.gz", self::$tmpFolder, $this->getType(), md5(time()));
        $cmd     = sprintf("cd %s; tar -czf %s *", $srcFolder, $tmpFile);

        if ($output instanceof OutputInterface) {
            $cmdLoader = new CommandLoader($output);
            $cmdLoader->setMessage(sprintf("Compressing Backup to [%s]", $tmpFile));
            $cmdLoader->run();
            $stopwatch = new Stopwatch();
            $stopwatch->start('compress');
            CommandHelper::execute($cmd,
                array(
                    'idle_timeout' => 1800
                ));
            $event = $stopwatch->stop('compress');
            if ($output->isDebug()) {
                $output->writeln(sprintf("Compression takes %d seconds.", round($event->getDuration() / 1000), 0));
            }
            $cmdLoader->setMessage(
                sprintf('Uploading Backup (%s) to [%s]',
                    DataValueHelper::convertFilesize(filesize($tmpFile)),
                    $this->getAbsolutePath()
                ));
            $gfs = Config::getTargetFs();
            $gfs->write($this->getAbsolutePath(), file_get_contents($tmpFile), true);
            $cmdLoader->stop("Done!");
        } else {
            CommandHelper::execute($cmd,
                array(
                    'idle_timeout' => 1800
                ));
            $gfs = Config::getTargetFs();
            $gfs->write($this->getAbsolutePath(), file_get_contents($tmpFile), true);
        }

        $fs = new Filesystem();
        $fs->remove($tmpFile);
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
        return $this->dateTime->getTimestamp();
    }

    /**
     * @param int $timestamp
     */
    public function setTimestamp($timestamp)
    {
        $this->dateTime = \DateTime::createFromFormat('U', $timestamp);
    }

    /**
     * @return mixed
     */
    public function getCommit($short = false)
    {
        if ($short) {
            return substr($this->commit, 0, 7);
        }

        return $this->commit;
    }

    /**
     * @param mixed $commit
     */
    public function setCommit($commit)
    {
        $this->commit = $commit;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return bool
     */
    public function exist()
    {
        /**
         * @var $fs \Gaufrette\Filesystem
         */
        $fs = Config::getTargetFs();

        return ($fs->has($this->getAbsolutePath()));
    }

    public function jsonSerialize()
    {
        return [
            "timestamp" => $this->getTimestamp(),
            "version"   => $this->getVersion(),
            "commit"    => $this->getCommit(),
            "type"      => $this->getType()
        ];
    }

}