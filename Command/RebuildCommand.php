<?php
/**
 * SNBundles
 * Created by PhpStorm.
 * File: RebuildCommand.php
 * User: thomas
 * Date: 03.07.17
 * Time: 09:51
 */

namespace SN\BackupBundle\Command;


use Doctrine\Common\Collections\ArrayCollection;
use SN\BackupBundle\Model\Backup;
use SN\BackupBundle\Model\BackupList;
use SN\BackupBundle\Model\Config;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RebuildCommand extends ContainerAwareCommand
{

    /**
     * @var $fs \Gaufrette\Filesystem
     */

    protected $fs;

    protected function configure()
    {
        $this->setName('sn:backup:rebuild')
            ->setDescription("Rebuild the json document");
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $backupTypes = array(
            Backup::TYPE_DAILY,
            Backup::TYPE_WEEKLY,
            Backup::TYPE_MONTHLY,
            Backup::TYPE_YEARLY
        );

        /**
         * @var $fs \Gaufrette\Filesystem
         */
        $this->fs   = Config::getTargetFs();
        $backupList = new BackupList(null, true);
        $this->checkKnownBackups($backupList);
    }

    protected function checkKnownBackups(BackupList $backupList)
    {
        $updatedBackups = new ArrayCollection();

        $types = array(
            Backup::TYPE_DAILY,
            Backup::TYPE_MONTHLY,
            Backup::TYPE_WEEKLY,
            Backup::TYPE_YEARLY
        );

        foreach ($types as $type) {
            $files = $this->fs->listKeys($type);
            foreach ($files['keys'] as $file) {
                list($year, $month, $day, $hour, $minute) = sscanf($file, $type . '/%d-%d-%d_%d-%d.tar.gz');

                $backup = new Backup();
                $backup->setType($type);
                $timestamp = mktime($hour, $minute, 0, $month, $day, $year);
                $backup->setTimestamp($timestamp);
                $backupList->addBackup($backup);
            }
        }

    }

}