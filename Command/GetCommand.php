<?php
/**
 * BugNerd
 * Created by PhpStorm.
 * File: GetCommand.php
 * User: thomas
 * Date: 09.02.17
 * Time: 14:31
 */

namespace SN\BackupBundle\Command;


use Gaufrette\Filesystem;
use SN\BackupBundle\Model\Backup;
use SN\BackupBundle\Model\BackupList;
use SN\ToolboxBundle\Helper\CommandHelper;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GetCommand extends ContainerAwareCommand
{
    protected static $configs;
    /**
     * @var OutputInterface
     */
    protected $output;

    protected function configure()
    {
        $this->setName('sn:backup:get')
            ->addArgument('id', InputArgument::OPTIONAL)
            ->addOption('clean', 'c', InputOption::VALUE_NONE);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        self::$configs = $this->getContainer()->getParameter('sn_backup');
        $this->output  = $output;
        $id            = $input->getArgument('id');

        if ($id != null) {

            if ($input->getOption('clean') == null) {
                $this->getBackup($id, $output);
            } else {
                $this->cleanUp($id);
            }
        } else {
            $output->writeln($this->getConfig());
        }
    }

    protected function cleanUp($tempExtract)
    {
        $folder = explode("/", $tempExtract);

        if ($folder[1] == "tmp") {
            $cmd = sprintf("rm -rf %s", $tempExtract);
            CommandHelper::executeCommand($cmd);
        }
    }

    protected function getBackup($id, OutputInterface $output)
    {
        if ($id == "c") {
            $path = CommandHelper::executeCommand(sprintf("php %s/../bin/console sn:backup:dump --current",
                $this->getContainer()->get('kernel')->getRootDir()));
            $output->writeln($path);

            return;
        }

        $backups = BackupList::factory();
        /**
         * @var $backup Backup
         */
        $backup = $backups->getDumps()->get($id);

        if (null == $backup) {
            $output->writeln(CommandHelper::writeError("Backup not found!"));
            return;
        }

        $toTmp = sprintf("/tmp/%s", md5(time()));
        $fs = new \Symfony\Component\Filesystem\Filesystem();
        $fs->mkdir($toTmp);
        $backup->extractTo($toTmp);

        $output->writeln($toTmp);
    }

    protected function getConfig()
    {
        return (string)BackupList::factory();
    }
}