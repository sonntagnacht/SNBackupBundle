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


use SN\DeployBundle\Services\Version;
use SN\ToolboxBundle\Helper\CommandHelper;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

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

            if ($id == "c") {

                try {
                    /**
                     * @var $sn_deploy Version
                     */
                    $sn_deploy  = $this->getContainer()->get('sn_deploy.twig');
                    $commit     = $sn_deploy->getCommit();
                    $commitLong = $sn_deploy->getCommit(false);
                    $version    = $sn_deploy->getVersion();
                } catch (ServiceNotFoundException $exception) {
                    $commit     = null;
                    $commitLong = null;
                    $version    = null;
                }

                $dump = [
                    "timestamp"   => time(),
                    "commit"      => $commit,
                    "commit_long" => $commitLong,
                    "version"     => $version
                ];

                $output->writeln(json_encode($dump));

                return;
            }

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
        $configs      = self::$configs;
        $backupFolder = $configs["backup_folder"];
        $backupConfig = json_decode($this->getConfig(), true);

        if (count($backupConfig["dumps"]) == 0) {
            $output->writeln(CommandHelper::writeError("Backup not found!"));
        }

        $dump = $backupConfig["dumps"][$id];

        $archiveName   = sprintf("%s.tar.gz", date("Y-m-d_H-i-s", $dump["timestamp"]));
        $backupArchive = sprintf("%s/%s", $backupFolder, $archiveName);
        $temp          = md5(time());
        $tempArchive   = sprintf("/tmp/%s.tar.gz", $temp);
        $tempExtract   = sprintf("/tmp/%s", $temp);

        try {
            /**
             * @var $gfs \Gaufrette\Filesystem
             */
            $gfs  = $this->getContainer()
                ->get('knp_gaufrette.filesystem_map')
                ->get(self::$configs["backup_folder"]);
            $data = $gfs->read($archiveName);

            /**
             * @var $fs Filesystem
             */
            $fs = new \Symfony\Component\Filesystem\Filesystem();
            $fs->dumpFile($tempArchive, $data);
        } catch (\InvalidArgumentException $exception) {
            CommandHelper::executeCommand(sprintf("cp %s %s", $backupArchive, $tempArchive));
        }

        $cmd = sprintf("mkdir %s; tar xfz %s -C %s; rm %s",
            $tempExtract,
            $tempArchive,
            $tempExtract,
            $tempArchive
        );

        $output->writeln(CommandHelper::executeCommand($cmd));

        $output->writeln($tempExtract);
    }

    protected function getConfig()
    {
        try {
            /**
             * @var $fs \Gaufrette\Filesystem
             */
            $fs = $this->getContainer()
                ->get('knp_gaufrette.filesystem_map')
                ->get(self::$configs["backup_folder"]);
            try {
                return $fs->read('backup.json');
            } catch (FileNotFound $exception) {
                return "{dumps:[]}";
            }
        } catch (\InvalidArgumentException $exception) {
            $backupFile = sprintf("%s/backup.json", self::$configs["backup_folder"]);
            if (file_exists($backupFile)) {
                return file_get_contents($backupFile);
            } else {
                return "{dumps:[]}";
            }
        }
    }
}