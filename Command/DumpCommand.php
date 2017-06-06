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
use Gaufrette\FilesystemMap;
use Psr\Log\LoggerInterface;
use SN\BackupBundle\Model\Backup;
use SN\BackupBundle\Model\BackupList;
use SN\BackupBundle\Model\Config;
use SN\DeployBundle\Services\Version;
use SN\ToolboxBundle\Gaufrette\GaufretteHelper;
use SN\ToolboxBundle\Helper\CommandHelper;
use SN\ToolboxBundle\Helper\CommandLoader;
use SN\ToolboxBundle\Helper\DataValueHelper;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\ProgressBar;
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

    protected $tempFolder;

    /**
     * @var $logger LoggerInterface
     */
    protected $logger;

    /**
     * @var $fs Filesystem
     */
    protected $fs;

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
            ->addOption('full', 'f', InputOption::VALUE_NONE, 'Take a backup with webfolder.')
            ->addOption('current', 'c', InputOption::VALUE_NONE, 'Without saving')
            ->addOption('check-target-fs',
                null,
                InputOption::VALUE_NONE,
                'If given, the target filesystem will be checked if it is available');
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

    /**
     * @param $path
     * @param bool $remove
     * @return mixed
     */
    protected function createFolder($path, $remove = false)
    {
        $fs = $this->fs;

        if (true === $remove) {
            $fs->remove($path);
        }

        if (false === $fs->exists($path)) {
            $fs->mkdir($path);
        }

        return $path;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $this->input  = $input;
        $this->output = $output;
        $this->logger = $this->getContainer()->get('logger');

        $this->logger->notice(sprintf("Create backup of type [%s]",
            $this->input->getArgument('type')));

        if (!in_array($this->input->getArgument('type'),
            array(
                Backup::TYPE_DAILY,
                Backup::TYPE_WEEKLY,
                Backup::TYPE_MONTHLY,
                Backup::TYPE_YEARLY
            ))
        ) {
            $msg = sprintf('The type [%s] is unknown.',
                $this->input->getArgument('type'));
            $this->logger->error($msg);
            throw new \InvalidArgumentException($msg);
        }

        /**
         * @var $gaufrette FilesystemMap
         */
        $gaufrette   = $this->getContainer()->get('knp_gaufrette.filesystem_map');
        $gaufretteFs = Config::getGaufretteFs();
        $saveFs      = array();

        if ($input->getOption('check-target-fs')) {
            // test if backup fs exists
            Config::getTargetFs();
        }

        foreach ($gaufretteFs as $fsName) {
            // if given fsName doesnt exist, an InvalidArgumentException will be thrown
            try {
                $saveFs[$fsName] = $gaufrette->get($fsName);
            } catch (\InvalidArgumentException $exception) {
                $this->logger->error($exception->getMessage());

                return;
            }
        }


        $backup = new Backup();
        $backup->setType($input->getArgument('type'));

        // Get configs
        $this->fs = new Filesystem();
        $fs       = $this->fs;

        $tmpFolder = sprintf("/tmp/%s_%s", $backup->getType(), md5(time()));

        $this->tempFolder = $this->createFolder($tmpFolder, true);


        $connections = Config::get(Config::DATABASES);
        $loader      = new CommandLoader($this->output);
        $loader->setMessage("Export databases")->run();
        foreach ($connections as $connection_name) {
            $msg = sprintf("Export database [%s]", $connection_name);
            $loader->setMessage($msg);
            $this->logger->notice($msg);
            $this->dumpDatabase($connection_name);
        }
        $loader->stop("Export databases finished!");

        $this->copyGaufretteFilesystem($saveFs);

        if ($input->getOption('full')) {
            $root_dir = $this->getContainer()->get('kernel')->getRootDir() . '/../';

            $cmd = sprintf("mkdir %s/_app; cp -r %s %s/_app",
                $this->tempFolder,
                $root_dir,
                $this->tempFolder);

            CommandHelper::executeCommand($cmd);
        }

        $this->logger->notice("Uploading Backup");
        $backup->insertFrom($this->tempFolder, $output);
        $fs->remove($this->tempFolder);

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

        $this->logger->notice('Backup complete.');
    }

    /**
     * @param $gaufretteFs \Gaufrette\Filesystem[]
     */
    protected function copyGaufretteFilesystem($gaufretteFs)
    {
        $fs       = $this->fs;
        $progress = new ProgressBar($this->output, count($gaufretteFs));
        $progress->setFormat(' %current%/%max% Filesystems --- %message%');
        $progress->start();

        foreach ($gaufretteFs as $folder => $gfs) {
            $progress->advance();

            $progress->setMessage(sprintf("Calculate [%s]",
                $folder));
            $progress->display();

            $msg = sprintf("Copy [%s] (%s)",
                $folder,
                DataValueHelper::convertFilesize(GaufretteHelper::getSize($gfs)));

            $this->logger->notice($msg);
            $progress->setMessage($msg);
            $progress->display();

            $fs->mkdir(sprintf("%s/%s",
                $this->tempFolder,
                $folder));
            /**
             * @var $gfs \Gaufrette\Filesystem
             */
            $files = $gfs->keys();
            if ($this->output->isVerbose()) {
                $this->output->writeln('');
                $subprogress = new ProgressBar($this->output, count($files));
                $subprogress->setFormat('normal');
                $subprogress->start();
                $subprogress->setRedrawFrequency(count($files) / 100);
            }

            foreach ($files as $counter => $file) {
                if ($gfs->isDirectory($file)) {
                    $fs->mkdir(sprintf("%s/%s/%s",
                        $this->tempFolder,
                        $folder,
                        $file));
                } else {
                    $data = $gfs->read($file);
                    $fs->dumpFile(
                        sprintf("%s/%s/%s",
                            $this->tempFolder,
                            $folder,
                            $file),
                        $data);
                }
                if ($this->output->isVerbose()) {
                    $subprogress->advance();
                }
            }
            if ($this->output->isVerbose()) {
                $subprogress->finish();
                $this->output->write("\x0D");
                $this->output->write("\x1B[2K");
            }

        }
        $progress->finish();
        $this->output->writeln(" - Complete!");
    }

    protected function dumpDatabase($connection_name)
    {
        $destination = sprintf("%s/_databases", $this->tempFolder);
        $this->createFolder($destination);
        $dbal_string = sprintf('doctrine.dbal.%s_connection', $connection_name);

        /**
         * @var $con Connection
         */
        $con    = $this->getContainer()->get($dbal_string);
        $driver = get_class($con->getDriver());

        switch ($driver) {
            case 'Doctrine\DBAL\Driver\PDOMySql\Driver':
                if (CommandHelper::executeCommand("which mysqldump")) {
                    $cmd = sprintf("mysqldump --single-transaction=TRUE --quick -h %s -u %s -P %s --password='%s' %s > %s/%s.sql",
                        $con->getHost(),
                        $con->getUsername(),
                        $con->getPort() ? $con->getPort() : 3306,
                        $con->getPassword(),
                        $con->getDatabase(),
                        $destination,
                        $connection_name);
                    CommandHelper::executeCommand($cmd);

                    return;
                }
                break;
        }

        // Default Database-Export
        $warning = CommandHelper::writeWarning(sprintf("Databasedump command for [%s] not found. Try JSON export!",
            $driver));
        $this->output->writeln($warning);

        if (!$con->isConnected() && !$con->connect()) {
            throw new ConnectionException('Database is not connected!');
        }

        $schemaManager = $con->getSchemaManager();
        $mngTables     = $schemaManager->listTables();
        $tables        = array();

        foreach ($mngTables as $table) {
            $cols      = array();
            $query     = sprintf("SELECT * FROM %s", $table->getName());
            $statement = $con->executeQuery($query);
            while ($result = $statement->fetchAll()) {
                $cols[] = $result;
            }
            $tables[$table->getName()] = $cols;
        }

        $this->fs->dumpFile(sprintf(
            "%s/%s.json",
            $destination,
            $connection_name),
            json_encode($tables));

    }

    protected function writeln($message, $force = false)
    {
        if (!$this->input->getOption('current') || $force) {
            $this->output->writeln($message);
        }
    }
}
