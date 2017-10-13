<?php

namespace SN\BackupBundle\Services;

use SN\BackupBundle\Model\Backup;

/**
 * SNBundles
 * Created by PhpStorm.
 * File: BackupManager.php
 * User: y4roc
 * Date: 13.10.17
 * Time: 23:27
 */
class BackupManager
{
    protected $databases;
    protected $filesystems;

    public function __construct(array $databases = array(), array $filesystems = array())
    {
        $this->databases   = $databases;
        $this->filesystems = $filesystems;
    }

    /**
     * @param string $target
     * @param string $type
     */
    public function createBackup($target, $type = Backup::TYPE_DAILY)
    {
        $backup = new Backup();
        $backup->getFilesFrom($this->filesystems);
    }
}