<?php
/**
 * SNBundle
 * Created by PhpStorm.
 * File: RemoteBackup.php
 * User: thomas
 * Date: 08.03.17
 * Time: 15:32
 */

namespace SN\BackupBundle\Model;


use SN\ToolboxBundle\Helper\CommandHelper;
use Symfony\Component\Console\Output\OutputInterface;

class RemoteBackup extends Backup
{
    protected $sshConfig;
    protected $id;

    public function __construct($env, array $envConfig, $id)
    {
        $this->setFilename($env);
        $this->sshConfig = $envConfig[$env];
        $this->id        = $id;
    }

    /**
     * @param string $dstFolder
     */
    public function extractTo($dstFolder, OutputInterface $output = null)
    {
        $tmpFolder = sprintf("/tmp/%s", md5(time()));

        // extract local-remote archive if exists
        if ($this->archive_exists()) {
            parent::extractTo($dstFolder);
        }

        // extract archive on remote
        $srcFolder = CommandHelper::executeRemoteCommand(sprintf("php bin/console sn:backup:dump -c"),
            $this->sshConfig);

        // download remote archive to local
        $cmd = sprintf(
            "rsync --delete --info=progress2 -r --rsh='ssh -p %s' %s@%s:%s/* %s/",
            $this->sshConfig["port"],
            $this->sshConfig["user"],
            $this->sshConfig["host"],
            $srcFolder,
            $dstFolder
        );
        CommandHelper::executeCommand($cmd, $output);

        // Clean up tmp on remote
        CommandHelper::executeRemoteCommand(sprintf("php bin/console sn:backup:dump -c %s", $srcFolder),
            $this->sshConfig);

        // save remote archive in local archive
        $this->insertFrom($dstFolder);
    }

}