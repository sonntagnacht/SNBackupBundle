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


use SN\ToolboxBundle\Helper\CommandHelper;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Debug\Exception\ContextErrorException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class RestoreCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName("sn:backup:restore")
            ->setDescription("Restore a backup")
            ->addArgument('id', InputArgument::OPTIONAL, 'Id of backup wich will be restore');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getArgument('id') != null) {
            $this->restoreBackup($input->getArgument('id'), $output, $input);
        } else {
            $this->renderList($output);
        }
    }

    protected function restoreBackup($id, OutputInterface $output, InputInterface $input)
    {
        $backupFile       = sprintf("%s/../backup.json", $this->getContainer()->get('kernel')->getRootDir());
        $extractFolder    = sprintf("%s/../var/sn_backup", $this->getContainer()->get('kernel')->getRootDir());
        $configs          = $this->getContainer()->getParameter('sn_backup');
        $databaseUser     = $configs["database"]["user"];
        $databaseHost     = $configs["database"]["host"];
        $databasePort     = $configs["database"]["port"];
        $databasePassword = $configs["database"]["password"];
        $databaseName     = $configs["database"]["dbname"];
        $backupFolder     = $configs["backup_folder"];

        if ($databasePort == null) {
            $databasePort = 3306;
        }

        if (file_exists($backupFile) === false) {
            $output->writeln(CommandHelper::writeError("Backup not found!"));
        }

        $backupConfig = json_decode(file_get_contents($backupFile), true);

        //try {

        $dump = $backupConfig["dumps"][$id];
        $fs   = new Filesystem();
        $fs->remove($extractFolder);
        $fs->mkdir($extractFolder);

        $cmd = sprintf("tar xfz %s/%s.tar.gz -C %s",
            $backupFolder,
            date("Y-m-d_H-i-s", $dump["timestamp"]),
            $extractFolder
        );

        CommandHelper::executeCommand($cmd, $output, false);

        // Database import
        $cmd = sprintf("mysql -h %s -u %s -P %s --password='%s' %s < %s/database.sql",
            $databaseHost,
            $databaseUser,
            $databasePort,
            $databasePassword,
            $databaseName,
            $extractFolder
        );

        CommandHelper::executeCommand($cmd, $output, false);

        // Git revert
        $helper   = $this->getHelper('question');
        $cmd      = sprintf("git reset --hard %s", $dump["commit_long"]);
        $question = new ConfirmationQuestion(
            sprintf(
                'Do you want to execute \'%s\'? [y|<options=bold>N</>] ',
                $cmd)
            , false,
            '/^(y|j)/i');

        if ($helper->ask($input, $output, $question)) {
            CommandHelper::executeCommand($cmd, $output, false);
        }

        try {
            $gaufrette = $this->getContainer()->get('knp_gaufrette.filesystem_map');
            $finder    = new Finder();

            // Delete all Gaufrette files
            foreach ($gaufrette as $gfs) {
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
            $output->writeln("No Gaufrette-FilesystemMap found!");
        }

        /*} catch (ContextErrorException $exception) {
            $output->writeln(CommandHelper::writeError("Backup not found!"));
        }*/

    }

    protected function renderList(OutputInterface $output)
    {
        $backupFile = sprintf("%s/../backup.json", $this->getContainer()->get('kernel')->getRootDir());

        if (file_exists($backupFile) === true) {
            $backupConfig = json_decode(file_get_contents($backupFile), true);
        } else {
            $backupConfig = ["dumps" => array()];
        }

        $backup = new Table($output);
        $backup->setHeaders(array("ID", "Timestamp", "Version", "Commit"));
        foreach ($backupConfig["dumps"] as $id => $dump) {
            $backup->addRow(array($id, date("Y-m-d H-i-s", $dump["timestamp"]), $dump["version"], $dump["commit"]));
        }
        $backup->render();
    }
}