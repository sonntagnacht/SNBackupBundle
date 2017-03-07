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


use Gaufrette\Exception\FileNotFound;
use SN\ToolboxBundle\Helper\CommandHelper;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class RestoreCommand extends ContainerAwareCommand
{
    protected static $configs;
    protected $output;

    protected function configure()
    {
        $this->setName("sn:backup:restore")
            ->setDescription("Restore a backup")
            ->addArgument('id', InputArgument::OPTIONAL, 'Id of backup wich will be restore')
            ->addOption('remote', 'r', InputOption::VALUE_OPTIONAL, 'To load a remote backup.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        self::$configs = $this->getContainer()->getParameter('sn_backup');
        $this->output  = $output;


        if ($input->getArgument('id') != null) {
            $this->restoreBackup($input->getArgument('id'), $output, $input);
        } else {
            $this->renderList($output,
                ($input->getOption('remote') == null) ? $this->getLocalConfig() : $this->getRemoteConfig($input->getOption('remote')));
        }
    }


    protected function getRemoteCurrentBackup($env, $extractFolder)
    {
        $remoteConfigs = $this->getContainer()->getParameter('sn_deploy.environments');
        $config        = $remoteConfigs[$env];

        $srcFolder = CommandHelper::executeRemoteCommand(sprintf("php bin/console sn:backup:dump -c"), $config);

        $localArchive = sprintf("%s/%s.tar.gz", self::$configs['backup_folder'], $env);

        if (file_exists($localArchive)) {
            $cmd = sprintf("rm -Rf %s; mkdir %s; tar xfz %s -C %s",
                $extractFolder,
                $extractFolder,
                $localArchive,
                $extractFolder);

            CommandHelper::executeCommand($cmd);
        }

        $cmd = sprintf(
            "rsync --delete --info=progress2 -r --rsh='ssh -p %s' %s@%s:%s/* %s/",
            $config["port"],
            $config["user"],
            $config["host"],
            $srcFolder,
            $extractFolder
        );

        CommandHelper::executeCommand($cmd, $this->output);

        CommandHelper::executeRemoteCommand(
            sprintf("php bin/console sn:backup:get -c %s", $srcFolder),
            $config);
    }

    protected function getRemoteBackup($env, $id, $extractFolder)
    {
        $remoteConfigs = $this->getContainer()->getParameter('sn_deploy.environments');
        $config        = $remoteConfigs[$env];

        $srcFolder = CommandHelper::executeRemoteCommand(sprintf("php bin/console sn:backup:get %s", $id), $config);

        $localArchive = sprintf("%s/%s.tar.gz", self::$configs['backup_folder'], $env);

        if (file_exists($localArchive)) {
            $cmd = sprintf("rm -Rf %s; mkdir %s; tar xfz %s -C %s",
                $extractFolder,
                $extractFolder,
                $localArchive,
                $extractFolder);

            CommandHelper::executeCommand($cmd);
        }

        $cmd = sprintf(
            "rsync --delete --info=progress2 -r --rsh='ssh -p %s' %s@%s:%s/* %s/",
            $config["port"],
            $config["user"],
            $config["host"],
            $srcFolder,
            $extractFolder
        );
        CommandHelper::executeCommand($cmd, $this->output);

        $cmd = sprintf("cd %s; tar -czf %s *",
            $extractFolder,
            $localArchive);

        CommandHelper::executeCommand($cmd);

        $this->output->writeln(CommandHelper::executeRemoteCommand(sprintf("php bin/console sn:backup:get -c %s",
            $srcFolder),
            $config));
    }

    protected function getLocalBackup($timestamp, $backupFolder, $extractFolder)
    {
        $archiveName   = sprintf("%s.tar.gz", date("Y-m-d_H-i-s", $timestamp));
        $backupArchive = sprintf("%s/%s", $backupFolder, $archiveName);
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

    protected function restoreBackup($id, OutputInterface $output, InputInterface $input)
    {
        $extractFolder    = sprintf("%s/../var/sn_backup", $this->getContainer()->get('kernel')->getRootDir());
        $configs          = self::$configs;
        $databaseUser     = $configs["database"]["user"];
        $databaseHost     = $configs["database"]["host"];
        $databasePort     = $configs["database"]["port"];
        $databasePassword = $configs["database"]["password"];
        $databaseName     = $configs["database"]["dbname"];
        $backupFolder     = $configs["backup_folder"];

        if ($databasePort == null) {
            $databasePort = 3306;
        }

        if ($input->getOption('remote') == null) {
            $backupConfig = json_decode($this->getLocalConfig(), true);

            if (count($backupConfig["dumps"]) == 0) {
                $output->writeln(CommandHelper::writeError("Backup not found!"));

                return;
            }

            $dump = $backupConfig["dumps"][$id];
        }

        $fs = new Filesystem();
        $fs->remove($extractFolder);
        $fs->mkdir($extractFolder);

        if ($input->getOption('remote') == null) {
            $this->getLocalBackup($dump["timestamp"], $backupFolder, $extractFolder);
        } else {
            if ($input->getArgument('id') == "c") {
                $this->getRemoteCurrentBackup($input->getOption('remote'), $extractFolder);
            } else {
                $this->getRemoteBackup($input->getOption('remote'), $id, $extractFolder);
            }
        }

        $app_folder  = sprintf("%s/_app", $extractFolder);
        $root_folder = $this->getContainer()->get('kernel')->getRootDir() . "/../";

        if ($fs->exists($app_folder)) {
            $helper   = $this->getHelper('question');
            $cmd      = sprintf("cp -r %s %s",
                $app_folder,
                $root_folder);
            $question = new ConfirmationQuestion(
                sprintf(
                    'Do you want restore your webfolder [y/N]? ',
                    $cmd)
                , false,
                '/^(y)/i');

            if ($helper->ask($input, $output, $question)) {
                CommandHelper::executeCommand($cmd);
            }
        }

        // Database import
        $cmd = sprintf("mysql -h %s -u %s -P %s --password='%s' %s < %s/database.sql",
            $databaseHost,
            $databaseUser,
            $databasePort,
            $databasePassword,
            $databaseName,
            $extractFolder
        );
        CommandHelper::executeCommand($cmd);

        $cmd = "git rev-parse --is-inside-work-tree";

        // git reset
        if ($dump["commit_long"] != null && CommandHelper::executeCommand($cmd)) {
            $helper   = $this->getHelper('question');
            $cmd      = sprintf("git reset --hard %s", $dump["commit_long"]);
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

    protected function getRemoteConfig($env)
    {
        $remoteConfigs = $this->getContainer()->getParameter('sn_deploy.environments');
        $config        = $remoteConfigs[$env];

        return CommandHelper::executeRemoteCommand("php bin/console sn:backup:get", $config);
    }

    protected function renderList(OutputInterface $output, $configs)
    {
        $configs = json_decode($configs, true);

        $backup = new Table($output);
        $backup->setHeaders(array("ID", "Timestamp", "Version", "Commit"));
        if (count($configs["dumps"]) > 0) {
            foreach ($configs["dumps"] as $id => $dump) {
                $backup->addRow(array($id, date("Y-m-d H-i-s", $dump["timestamp"]), $dump["version"], $dump["commit"]));
            }
        }
        $backup->render();
    }
}