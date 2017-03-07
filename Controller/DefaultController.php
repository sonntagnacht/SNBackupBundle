<?php

namespace SN\BackupBundle\Controller;

use Gaufrette\Exception\FileNotFound;
use SN\ToolboxBundle\Helper\CommandHelper;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="backup_list")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction()
    {
        $config  = $this->getParameter('sn_backup');
        $backups = json_decode($this->getLocalConfig($config["backup_folder"]), true)["dumps"];

        return $this->render('SNBackupBundle:Default:index.html.twig',
            array(
                "backups" => $backups
            ));
    }

    /**
     * @Route("/{id}/information", name="backup_information" )
     */
    public function restoreAction(Request $request)
    {

        $config      = $this->getParameter('sn_backup');
        $backups     = json_decode($this->getLocalConfig($config["backup_folder"]), true)["dumps"];
        $backup      = $backups[$request->get('id')];
        $archiveName = sprintf("%s.tar.gz", date("Y-m-d_H-i-s", $backup["timestamp"]));
        $tmp         = sprintf("/tmp/%s", $archiveName);

        $this->copyFromBackup($config["backup_folder"], $archiveName, $tmp);

        $filelist = CommandHelper::executeCommand(sprintf("tar -tf %s",
            $tmp
        ));

        $fs = new Filesystem();
        $fs->remove($tmp);

        $backup["files"] = explode("\n", $filelist);
        $backup["name"]  = $archiveName;


        return $this->render('SNBackupBundle:Default:info.html.twig',
            array(
                "backup" => $backup
            ));
    }


    /**
     * @param $backupFolder
     * @param $source
     * @param $target
     */
    protected function copyFromBackup($backupFolder, $source, $target)
    {
        $backupArchive = sprintf("%s/%s", $backupFolder, $source);

        try {
            /**
             * @var $gfs \Gaufrette\Filesystem
             */
            $gfs  = $this
                ->get('knp_gaufrette.filesystem_map')
                ->get($backupFolder);
            $data = $gfs->read($source);

            /**
             * @var $fs Filesystem
             */
            $fs = new Filesystem();
            $fs->dumpFile($target, $data);
        } catch (\InvalidArgumentException $exception) {
            CommandHelper::executeCommand(sprintf("cp %s %s", $backupArchive, $target));
        }
    }

    /**
     * @return string
     */
    protected function getLocalConfig($backupFolder)
    {
        try {
            /**
             * @var $fs \Gaufrette\Filesystem
             */
            $fs = $this->get('knp_gaufrette.filesystem_map')
                ->get($backupFolder);
            try {
                return $fs->read('backup.json');
            } catch (FileNotFound $exception) {
                return "{dumps:[]}";
            }
        } catch (\InvalidArgumentException $exception) {
            $backupFile = sprintf("%s/backup.json", $backupFolder);
            if (file_exists($backupFile)) {
                return file_get_contents($backupFile);
            } else {
                return "{dumps:[]}";
            }
        }
    }
}
