<?php
/**
 * BugNerd
 * Created by PhpStorm.
 * File: RestoreCommand.php
 * User: thomas
 * Date: 05.02.17
 * Time: 00:24
 */

namespace SN\BackupBundle\Command;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use SN\BackupBundle\Model\Backup;
use SN\BackupBundle\Model\BackupList;
use SN\BackupBundle\Model\RemoteBackup;
use SN\BackupBundle\Model\RemoteBackupList;
use SN\ToolboxBundle\Helper\CommandHelper;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class RestoreCommand extends ContainerAwareCommand
{
    protected static $configs;
    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var InputInterface
     */
    protected $input;

    protected function configure()
    {
        $this->setName("sn:backup:restore")
            ->setDescription("Restore a backup")
            ->addArgument('id', InputArgument::OPTIONAL, 'Id of backup wich will be restore')
            ->addOption('remote', 'r', InputOption::VALUE_OPTIONAL, 'To load a remote backup.')
            ->addOption('filter', 'f', InputOption::VALUE_REQUIRED, 'Filter by type');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        self::$configs = $this->getContainer()->getParameter('sn_backup');
        $this->output  = $output;
        $this->input   = $input;


        if ($input->getArgument('id') != null) {
            $this->restoreBackup($input->getArgument('id'), $output, $input);
        } else {
            $backupList = ($input->getOption('remote') == null) ? BackupList::factory() :
                new RemoteBackupList($input->getOption('remote'), $this->getContainer()
                    ->getParameter('sn_deploy.environments'));
            $this->renderList(
                $output,
                $backupList
            );

            $helper   = $this->getHelper('question');
            $question = new Question(
                'Please select the backup you will restore: ',
                null
            );

            $id = $helper->ask($input, $output, $question);
            $this->restoreBackup($id, $output, $input);
        }
    }

    protected function restoreRemoteBackup($env, $id)
    {
    }


    protected function getRemoteCurrentBackup($env, $extractFolder)
    {
        $remoteConfigs = $this->getContainer()->getParameter('sn_deploy.environments');
        $config        = $remoteConfigs[$env];

        new RemoteBackup($env, $remoteConfigs[$env], "c");
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
                file_get_contents($archive),
                true
            );
            CommandHelper::executeCommand(sprintf("rm -rf %s", $archive));
        } catch (\InvalidArgumentException $exception) {
            CommandHelper::executeCommand(sprintf("mv %s %s", $archive, self::$configs["backup_folder"]));
        }
    }

    protected function copyFromBackup($archiveName, $extractFolder)
    {
        $backupArchive = sprintf("%s/%s", self::$configs["backup_folder"], $archiveName);
        $tempArchive   = sprintf("%s/%s", "/tmp", $archiveName);

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
            $fs = new Filesystem();
            $fs->dumpFile($tempArchive, $data);
        } catch (\InvalidArgumentException $exception) {
            CommandHelper::executeCommand(sprintf("cp %s %s", $backupArchive, $tempArchive));
        }

        $cmd = sprintf("tar xfz %s -C %s",
            $tempArchive,
            $extractFolder
        );
        CommandHelper::executeCommand($cmd);
    }

    protected function getRemoteBackup($env, $id)
    {

        $extractFolder = sprintf("%s/../var/sn_backup", $this->getContainer()->get('kernel')->getRootDir());
        $remoteConfigs = $this->getContainer()->getParameter('sn_deploy.environments');

        return new RemoteBackup($env, $remoteConfigs[$env], $id);
    }

    protected function getRemoteCurrentConfig($env)
    {
        $remoteConfigs = $this->getContainer()->getParameter('sn_deploy.environments');
        $config        = $remoteConfigs[$env];

        return json_decode(
            CommandHelper::executeRemoteCommand("php bin/console sn:backup:get c", $config),
            true);
    }

    protected function restoreBackup($id, OutputInterface $output, InputInterface $input)
    {
        $extractFolder = sprintf("%s/../var/sn_backup", $this->getContainer()->get('kernel')->getRootDir());
        $fs            = new Filesystem();
        try {
            $fs->remove($extractFolder);
        } catch (\Exception $e) {
        }
        $fs->mkdir($extractFolder);

        if ($input->getOption('remote') == null) {
            $backupList = BackupList::factory();
            /**
             * @var $backup Backup
             */
            $backup = $backupList->getDumps()->get($id);
            if (!$backup) {
                $formatter      = $this->getHelper('formatter');
                $errorMessages  = array('', 'Backup not found!', '');
                $formattedBlock = $formatter->formatBlock($errorMessages, 'error');
                $output->writeln(array('', $formattedBlock));

                return;
            }
            $backup->extractTo($extractFolder);
        } else {
            $backup = $this->getRemoteBackup($input->getOption('remote'), $id, $extractFolder);
            $backup->extractTo($extractFolder, $this->output);
        }

        $app_folder = sprintf("%s/_app", $extractFolder);

        if ($fs->exists($app_folder)) {
            $root_folder = $this->getContainer()->get('kernel')->getRootDir() . "/../";
            $helper      = $this->getHelper('question');
            $cmd         = sprintf("cp -r %s %s",
                $app_folder,
                $root_folder);
            $question    = new ConfirmationQuestion(
                sprintf(
                    'Do you want restore your webfolder [y/N]? ',
                    $cmd)
                , false,
                '/^(y)/i');

            if ($helper->ask($input, $output, $question)) {
                CommandHelper::executeCommand($cmd);
            }
        }

        $cmd = "git rev-parse --is-inside-work-tree";

        // git reset
        if ($backup->getCommit() != null && CommandHelper::executeCommand($cmd)) {
            $helper   = $this->getHelper('question');
            $cmd      = sprintf("git reset --hard %s", $backup->getCommit());
            $question = new ConfirmationQuestion(
                sprintf(
                    'Do you want to execute \'%s\' [y/N]? ',
                    $cmd)
                , false,
                '/^(y)/i');

            if ($helper->ask($input, $output, $question)) {
                CommandHelper::executeCommand($cmd);
            }
        }

        // Database import
        $finder = new Finder();
        $finder->files()->in($extractFolder);

        foreach ($finder->name('database.*') as $file) {
            $this->importDatabase($file);
        }

        $finder = new Finder();
        $finder->files()->in(sprintf("%s/databases", $extractFolder));
        dump($finder);
        foreach ($finder as $file) {
            $this->importDatabase($file);
        }

        try {
            $gaufrette = $this->getContainer()->get('knp_gaufrette.filesystem_map');
            $finder    = new Finder();

            // Delete all Gaufrette files
            foreach ($gaufrette as $folder => $gfs) {
                if ($folder == self::$configs["backup_folder"]) {
                    continue;
                }
                $files = array_reverse($gfs->keys());
                foreach ($files as $file) {
                    $gfs->delete($file);
                }
            }

            // Load import Gaufrette files
            $finder->directories()->in("$extractFolder")->depth("== 0");
            foreach ($finder as $dir) {
                if ($dir->getRelativePathname() == "_app") {
                    continue;
                }
                $gfs     = $gaufrette->get($dir->getRelativePathname());
                $dFinder = Finder::create();
                $dFinder->files()->in($dir->getRealPath());
                foreach ($dFinder as $file) {
                    $pathname = $file->getRelativePathname();
                    $content  = $file->getContents();
                    $gfs->write($pathname, $content, true);
                }
            }

        } catch (ServiceNotFoundException $exception) {
        }

        $fs->remove($extractFolder);
    }

    /**
     * @return string
     */
    protected function getLocalConfig()
    {
        return BackupList::factory();
    }

    protected function getRemoteConfig($env)
    {
        $remoteConfigs = $this->getContainer()->getParameter('sn_deploy.environments');
        $config        = $remoteConfigs[$env];

        return CommandHelper::executeRemoteCommand("php bin/console sn:backup:get", $config);
    }

    /**
     * @param OutputInterface $output
     * @param BackupList|RemoteBackupList $config
     */
    protected function renderList(OutputInterface $output, $config)
    {
        $backup = new Table($output);
        $backup->setHeaders(array("ID", "Timestamp", "Type", "Version", "Commit"));
        if ($config->hasBackups()) {
            foreach ($config->getDumps() as $id => $dump) {
                if ($this->input->getOption('filter') != null && $this->input->getOption('filter') != $dump->getType()) {
                    continue;
                }
                $backup->addRow(array(
                    $id,
                    date("Y-m-d H-i", $dump->getTimestamp()),
                    $dump->getType(),
                    $dump->getVersion(),
                    $dump->getCommit(true)
                ));
            }
        }
        $backup->render();
    }

    protected function dropDatabase(Connection $connection)
    {
        $connection->exec('SET foreign_key_checks = 0');
        $schemaManager = $connection->getSchemaManager();
        $mngTables     = $schemaManager->listTables();

        foreach ($mngTables as $table) {
            $schemaManager->dropTable($table->getName());
        }
    }

    /**
     * @param SplFileInfo $file
     * @param bool $oldVersion
     * @throws ConnectionException
     */
    protected function importDatabase(SplFileInfo $file, $oldVersion = false)
    {
        $name = array_shift(explode(".", $file->getFilename()));
        if (true === $oldVersion) {
            $dbal_string = sprintf('doctrine.dbal.%s_connection', Config::get(Config::DATABASES));
        } else {
            $dbal_string = sprintf('doctrine.dbal.%s_connection', $name);
        }
        /**
         * @var $connection Connection
         */
        $connection = $this->getContainer()->get($dbal_string);
        $driver     = get_class($connection->getDriver());

        $this->dropDatabase($connection);

        if ($file->getExtension() == "sql") {
            switch ($driver) {
                case 'Doctrine\DBAL\Driver\PDOMySql\Driver':
                    if (CommandHelper::executeCommand("which mysql")) {
                        $cmd = sprintf("mysql -h %s -u %s -P %s --password='%s' %s < %s",
                            $connection->getHost(),
                            $connection->getUsername(),
                            $connection->getPort() ? $connection->getPort() : 3306,
                            $connection->getPassword(),
                            $connection->getDatabase(),
                            $file->getRealPath());
                        CommandHelper::executeCommand($cmd, $this->output);
                        $connection->exec('SET foreign_key_checks = 0');

                        return;
                    }

                    break;
            }
        }

        $json_string = file_get_contents($file->getRealPath());
        $database    = json_decode($json_string, true);

        if (!$connection->isConnected() && !$connection->connect()) {
            throw new ConnectionException('Database is not connected!');
        }

        $connection->exec('SET foreign_key_checks = 0');
        $schemaManager = $connection->getSchemaManager();
        $mngTables     = $schemaManager->listTables();

        foreach ($mngTables as $table) {
            $schemaManager->dropTable($table->getName());
        }

        $cmd = sprintf("php %s/../bin/console doctrine:schema:create",
            $this->getContainer()->get('kernel')->getRootDir());
        CommandHelper::executeCommand($cmd);

        $cmd = sprintf("php %s/../bin/console doctrine:migrations:status",
            $this->getContainer()->get('kernel')->getRootDir());
        CommandHelper::executeCommand($cmd);

        $tables = count($database);

        $progress = new ProgressBar($this->output, $tables);
        $progress->setFormat(' %current%/%max% Tables --- %message%');
        $progress->start();

        foreach ($database as $tablename => $table) {
            $progress->setMessage("Import data records [$tablename]");
            foreach ($table as $cols) {
                foreach ($cols as $col) {
                    $values = array();
                    foreach ($col as $key => $value) {
                        if ($value == "") {
                            $values[] = sprintf("%s = null", $key);
                        } else {
                            $values[] = sprintf("%s = \"%s\"", $key, addslashes($value));
                        }
                    }
                    $connection->exec(sprintf("INSERT INTO %s SET %s;", $tablename, join(',', $values)));
                }
            }
            $progress->advance();
        }
        $progress->finish();

        $connection->exec('SET foreign_key_checks = 1');
    }
}