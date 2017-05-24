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
            $backupList = BackupList::factory();
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

    protected function importGaufetteFilesystems(Finder $finder)
    {

    }

    /**
     * @param SplFileInfo $folder
     */
    protected function importGaufretteFilesystem(SplFileInfo $folder)
    {
        $finder = new Finder();
        $finder->files()->in($folder->getRealPath());
        $gfs = $this->getContainer()->get('knp_gaufrette.filesystem_map')->get($folder->getRelativePathname());

        if ($this->output->isVerbose()) {
            $this->output->writeln('');
            $subprogress = new ProgressBar($this->output, count($gfs->keys()));
            $subprogress->setFormat('normal');
            $subprogress->start();
            $subprogress->setRedrawFrequency(count($gfs->keys()) / 100);
        }

        foreach (array_reverse($gfs->keys()) as $file) {
            $gfs->delete($file);
            if ($this->output->isVerbose()) {
                $subprogress->advance();
            }
        }

        if ($this->output->isVerbose()) {
            $subprogress->finish();
            $this->output->write("\x0D");
            $this->output->write("\x1B[2K");
        }

        if ($this->output->isVerbose()) {
            $subprogress = new ProgressBar($this->output, count($finder));
            $subprogress->setFormat('normal');
            $subprogress->start();
            $subprogress->setRedrawFrequency(count($finder) / 100);
        }

        foreach ($finder as $file) {
            $pathname = $file->getRelativePathname();
            $content  = $file->getContents();
            $gfs->write($pathname, $content, true);
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

    protected function restoreBackup($id, OutputInterface $output, InputInterface $input)
    {
        $extractFolder = sprintf("%s/../var/sn_backup", $this->getContainer()->get('kernel')->getRootDir());
        $fs            = new Filesystem();


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
        $backup->extractTo($extractFolder, $output);
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

        foreach ($finder->name('database.*')->depth('== 0') as $file) {
            $this->importDatabase($file);
        }

        $finder = new Finder();
        $finder->files()->in(sprintf("%s/_databases", $extractFolder));
        foreach ($finder as $file) {
            $this->importDatabase($file);
        }

        // Gaufrette Filesystem
        $finder = new Finder();
        $finder->in($extractFolder)->exclude(["_databases", "_app"])->depth('== 0');
        $filesystemAmount = $finder->count();

        $progress = new ProgressBar($output, $filesystemAmount);
        $progress->setFormat(' %current%/%max% Filesystems --- %message%');
        $progress->start();

        foreach ($finder as $folder) {
            $progress->advance();

            $progress->setMessage(sprintf("Copy [%s]",
                $folder));
            $progress->display();

            $this->importGaufretteFilesystem($folder);
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
            $schemaManager->dropTable(sprintf("`%s`", $table->getName()));
        }
    }

    /**
     * @param SplFileInfo $file
     * @param bool $oldVersion
     * @throws ConnectionException
     */
    protected function importDatabase(SplFileInfo $file)
    {
        $filename    = explode(".", $file->getFilename());
        $name        = array_shift($filename);
        $dbal_string = sprintf('doctrine.dbal.%s_connection', $name);

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

                        CommandHelper::executeCommand($cmd,
                            $this->output,
                            sprintf("Import database [%s]", $name));

                        $connection->exec('SET foreign_key_checks = 0');

                        return;
                    }

                    break;
            }
        }

        $json_string = file_get_contents($file->getRealPath());
        $database    = json_decode($json_string, true);

        if (!$connection->isConnected() && !$connection->connect()) {
            throw new ConnectionException(sprintf('Unable to connect to database [%s]', $name));
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