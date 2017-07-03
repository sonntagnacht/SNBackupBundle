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
        $backupList = BackupList::factory();
        $this->checkKnownBackups($backupList);

        foreach ($backupTypes as $type) {
            $files = $this->fs->listKeys($type)["keys"];
            foreach ($files as $file) {
                if (false === $backupList->findByFilename($file) instanceof Backup) {
                    $matches = array();
                    preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}_[0-9]{2}-[0-9]{2}/', $file, $matches);
                    $datetime = \DateTime::createFromFormat("Y-m-d_H-i", $matches[0]);
                    $backup = new Backup();
                    $backup->setType($type);
                    $backup->setDateTime($datetime);
                    $backupList->addBackup($backup);
                }
            }
        }

        $backupList->sortByDate();

    }

    protected function checkKnownBackups(BackupList $backupList)
    {
        $updatedBackups = new ArrayCollection();
        $files          = $this->fs->listKeys()["keys"];
        foreach ($backupList->getDumps() as $backup) {
            if (in_array($backup->getAbsolutePath(), $files)) {
                $updatedBackups->add($backup);
            }
        }
        $backupList->setDumps($updatedBackups);
    }

}