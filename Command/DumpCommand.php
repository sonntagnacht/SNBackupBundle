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


use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use SN\BackupBundle\Model\Backup;
use SN\BackupBundle\Model\BackupList;
use SN\BackupBundle\Model\Config;
use SN\DeployBundle\Services\Version;
use SN\ToolboxBundle\Helper\CommandHelper;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
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
        $backupTypeDescription = sprintf('The type of the backup [%s]',
            join(',', array(Backup::TYPE_DAILY, Backup::TYPE_MONTHLY, Backup::TYPE_WEEKLY, Backup::TYPE_YEARLY)));

        $this->setName("sn:backup:dump")
            ->setDescription("Take a snapshot of your current application.")
            ->addArgument('type',
                InputArgument::OPTIONAL,
                $backupTypeDescription,
                Backup::TYPE_DAILY)
            ->addOption('remote', 'r', InputOption::VALUE_OPTIONAL, 'Take a snapshot from remote Server.')
            ->addOption('full', 'f', InputOption::VALUE_NONE, 'Take a backup with webfolder.')
            ->addOption('current', 'c', InputOption::VALUE_NONE, 'Without saving');
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

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->input  = $input;
        $this->output = $output;

        if (!in_array($this->input->getArgument('type'),
            array(
                Backup::TYPE_DAILY,
                Backup::TYPE_WEEKLY,
                Backup::TYPE_MONTHLY,
                Backup::TYPE_YEARLY
            ))
        ) {
            throw new \InvalidArgumentException(sprintf('The type [%s] is unknown.',
                $this->input->getArgument('type')));
        }


        $backup = new Backup();
        $backup->setType($input->getArgument('type'));

        if ($input->getOption('remote') != null) {
            $env           = $input->getOption('remote');
            $remoteConfigs = $this->getContainer()->getParameter('sn_deploy.environments');
            $config        = $remoteConfigs[$env];
            CommandHelper::executeRemoteCommand('php bin/console sn:backup:dump', $config);

            return;
        }

        $fs         = new Filesystem();
        $tempFolder = sprintf("/tmp/sn_backup");

        // prepare backup folder
        $fs->remove($tempFolder);
        $fs->mkdir($tempFolder);

        // Get configs
        $backupFolder     = Config::get(Config::BACKUP_FOLDER);

        $this->dumpDatabase(sprintf("%s/database.json", $tempFolder));

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

        if ($input->getOption('full')) {
            $root_dir = $this->getContainer()->get('kernel')->getRootDir() . '/../';

            $cmd = sprintf("mkdir %s/_app; cp -r %s %s/_app",
                $tempFolder,
                $root_dir,
                $tempFolder);

            $this->executeCommand($cmd);
        }

        if ($input->getOption('current')) {

            $currentFolder = sprintf("/tmp/%s", md5($backup->getTimestamp()));
            $cmd           = sprintf("cp -r %s %s", $tempFolder, $currentFolder);

            $this->executeCommand($cmd);
            $fs->remove($tempFolder);

            $this->writeln($currentFolder, true);

            return;
        }
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

    protected function dumpDatabase($dest)
    {

        $dbal_string = sprintf('doctrine.dbal.%s_connection', Config::get(Config::DATABASES));
        /**
         * @var $con Connection
         */
        $con           = $this->getContainer()->get($dbal_string);
        if(!$con->isConnected() && !$con->connect()){
            throw new ConnectionException('Database is not connected!');
        }

        $schemaManager = $con->getSchemaManager();
        $mngTables     = $schemaManager->listTables();
        $tables        = array();

        foreach ($mngTables as $table) {
            $cols   = array();
            $query    = sprintf("SELECT * FROM %s", $table->getName());
            $statement = $con->executeQuery($query);
            while ($result =  $statement->fetchAll() ) {
                $cols[] = $result;
            }
            $tables[$table->getName()] = $cols;
        }

        $fs = new Filesystem();
        $fs->dumpFile($dest, json_encode($tables));

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
