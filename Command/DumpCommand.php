<?php
/**
 * BugNerd
 * Created by PhpStorm.
 * File: DumpCommand.php
 * User: thomas
 * Date: 04.02.17
 * Time: 17:41
 */

namespace SN\BackupBundle\Command;


use Gaufrette\Exception\FileNotFound;
use SN\BackupBundle\Model\Backup;
use SN\BackupBundle\Model\BackupList;
use SN\BackupBundle\Model\Config;
use SN\DeployBundle\Services\Version;
use SN\ToolboxBundle\Helper\CommandHelper;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\Filesystem\Filesystem;

class DumpCommand extends ContainerAwareCommand
{

    protected static $buInformations;
    protected static $dump;
    protected static $configs;
    /**
     * @var $input InputInterface
     */
    protected $input;
    /**
     * @var $output OutputInterface
     */
    protected $output;

    protected function configure()
    {
        $this->setName("sn:backup:dump")
            ->setDescription("Take a snapshot of your current application.")
            ->addOption('remote', 'r', InputOption::VALUE_OPTIONAL, 'Take a snapshot from remote Server.')
            ->addOption('full', 'f', InputOption::VALUE_NONE, 'Take a backup with webfolder.')
            ->addOption('current', 'c', InputOption::VALUE_NONE, 'Without saving');
    }

    protected function copyToBackup($archive, $name)
    {

        try {
            /**
             * @var $fs \Gaufrette\Filesystem
             */
            $fs = $this->getContainer()
                ->get('knp_gaufrette.filesystem_map')
                ->get(self::$configs["backup_folder"]);
            $fs->write(
                $name,
                file_get_contents($archive)
            );
            $this->executeCommand(sprintf("rm -rf %s", $archive));
        } catch (\InvalidArgumentException $exception) {
            $this->executeCommand(sprintf("mv %s %s", $archive, self::$configs["backup_folder"]));
        }
    }

    /**
     * @return mixed
     */
    protected function loadDumpInformations()
    {
        try {
            /**
             * @var $fs \Gaufrette\Filesystem
             */
            $fs = $this->getContainer()
                ->get('knp_gaufrette.filesystem_map')
                ->get(self::$configs["backup_folder"]);
            try {
                $content = $fs->read('backup.json');
            } catch (FileNotFound $exception) {
                $content = "";
            }
        } catch (\InvalidArgumentException $exception) {
            $backupFile = sprintf("%s/backup.json", self::$configs["backup_folder"]);
            if (file_exists($backupFile)) {
                $content = file_get_contents($backupFile);
            } else {
                $content = "";
            }
        }

        self::$buInformations = json_decode($content, true);

        if (!is_array(self::$buInformations["dumps"])) {
            self::$buInformations["dumps"] = array();
        }

        return self::$buInformations;
    }

    /**
     * @param $timestamp
     */
    protected function addDumpInformations($timestamp)
    {
        $commit     = null;
        $commitLong = null;
        $version    = null;

        try {
            /**
             * @var $sn_deploy Version
             */
            $sn_deploy  = $this->getContainer()->get('sn_deploy.twig');
            $commit     = $sn_deploy->getCommit();
            $commitLong = $sn_deploy->getCommit(false);
            $version    = $sn_deploy->getVersion();
        } catch (ServiceNotFoundException $exception) {
        }

        self::$dump = [
            "timestamp"   => $timestamp,
            "commit"      => $commit,
            "commit_long" => $commitLong,
            "version"     => $version
        ];

        array_unshift(self::$buInformations["dumps"], self::$dump);
    }

    protected function saveDumpInformations()
    {
        $content = json_encode(self::$buInformations);

        try {
            // Gaufrette filesystem

            /**
             * @var $fs \Gaufrette\Filesystem
             */
            $fs      = $this->getContainer()
                ->get('knp_gaufrette.filesystem_map')
                ->get(self::$configs["backup_folder"]);
            $content = $fs->write("backup.json", $content, true);
        } catch (\InvalidArgumentException $exception) {
            // Local filesystem

            /**
             * @var $fs Filesystem
             */
            $fs         = new Filesystem();
            $backupFile = sprintf("%s/backup.json", self::$configs["backup_folder"]);
            $fs->dumpFile($backupFile, $content);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input  = $input;
        $this->output = $output;

        if ($input->getOption('remote') != null) {
            $env           = $input->getOption('remote');
            $remoteConfigs = $this->getContainer()->getParameter('sn_deploy.environments');
            $config        = $remoteConfigs[$env];
            CommandHelper::executeRemoteCommand('php bin/console sn:backup:dump', $config);

            return;
        }

        $fs         = new Filesystem();
        $tempFolder = sprintf("/tmp/sn_backup");
        $database   = Config::get(Config::DATABASE);

        // prepare backup folder
        $fs->remove($tempFolder);
        $fs->mkdir($tempFolder);

        // Get configs
        $databaseUser     = $database["user"];
        $databaseHost     = $database["host"];
        $databasePort     = $database["port"];
        $databasePassword = $database["password"];
        $databaseName     = $database["dbname"];
        $backupFolder     = Config::get(Config::BACKUP_FOLDER);

        $this->loadDumpInformations();


        if ($databasePort == null) {
            $databasePort = 3306;
        }

        $cmd = sprintf("mysqldump -h %s -u %s -P %s --password='%s' --compress %s > %s/database.sql",
            $databaseHost,
            $databaseUser,
            $databasePort,
            $databasePassword,
            $databaseName,
            $tempFolder
        );

        $this->executeCommand($cmd);

        try {
            $gaufrette = $this->getContainer()->get('knp_gaufrette.filesystem_map');


            foreach ($gaufrette as $folder => $gfs) {
                if ($folder == $backupFolder) {
                    continue;
                }

                $fs->mkdir(sprintf("%s/%s",
                    $tempFolder,
                    $folder));
                /**
                 * @var $gfs \Gaufrette\Filesystem
                 */
                $files = $gfs->keys();

                foreach ($files as $file) {
                    if ($gfs->isDirectory($file)) {
                        $fs->mkdir(sprintf("%s/%s/%s",
                            $tempFolder,
                            $folder,
                            $file));
                    } else {
                        $data = $gfs->read($file);
                        $fs->dumpFile(
                            sprintf("%s/%s/%s",
                                $tempFolder,
                                $folder,
                                $file),
                            $data);
                    }
                }
            }

        } catch (ServiceNotFoundException $exception) {
        }

        if ($input->getOption('full')) {
            $root_dir = $this->getContainer()->get('kernel')->getRootDir() . '/../';

            $cmd = sprintf("mkdir %s/_app; cp -r %s %s/_app",
                $tempFolder,
                $root_dir,
                $tempFolder);

            $this->executeCommand($cmd);
        }

        $timestamp = time();
        if ($input->getOption('current')) {

            $currentFolder = sprintf("/tmp/%s", md5($timestamp));
            $cmd           = sprintf("cp -r %s %s", $tempFolder, $currentFolder);

            $this->executeCommand($cmd);
            $fs->remove($tempFolder);

            $this->writeln($currentFolder, true);

            return;
        }

        $backup = new Backup();
        $backup->setTimestamp($timestamp);
        $backup->insertFrom($tempFolder);
        $fs->remove($tempFolder);

        try {
            /**
             * @var $sn_deploy Version
             */
            $sn_deploy = $this->getContainer()->get('sn_deploy.twig');
            $backup->setCommit($sn_deploy->getCommit(false));
            $backup->setVersion($sn_deploy->getVersion());
        } catch (ServiceNotFoundException $exception) {
            $backup->setCommit(null);
            $backup->setVersion(null);
        }

        BackupList::factory()->addBackup($backup);
    }

    protected function createSQLDump($dest)
    {
        $con           = $this->getContainer()->get('doctrine.dbal.default_connection');
        $schemaManager = $con->getSchemaManager();
        $mngTables        = $schemaManager->listTables();
        $tables = array();

        foreach ($mngTables as $table){
            $tables[] = $table->getName();
            foreach ($table->getColumns() as $column){

            }
        }
    }

    protected function executeCommand($cmd, $silence = false)
    {
        if ($this->input->getOption('current') || $silence) {
            return CommandHelper::executeCommand($cmd);
        }

        return CommandHelper::executeCommand($cmd, $this->output);
    }

    protected function writeln($message, $force = false)
    {
        if (!$this->input->getOption('current') || $force) {
            $this->output->writeln($message);
        }
    }
}
