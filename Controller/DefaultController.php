<?php

namespace SN\BackupBundle\Controller;

use Gaufrette\Exception\FileNotFound;
use SN\BackupBundle\Model\BackupList;
use SN\ToolboxBundle\Helper\CommandHelper;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
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
        $backups = BackupList::factory()->getDumps();

        return $this->render('SNBackupBundle:Default:index.html.twig',
            array(
                "backups" => $backups
            ));
    }

    /**
     * @Route("/{id}/download", name="backup_download")
     * @param Request $request
     * @return BinaryFileResponse
     */
    public function downloadAction(Request $request)
    {
        $list   = BackupList::factory();
        $backup = $list->getDumps()[$request->get('id')];

        $response = new BinaryFileResponse($backup->getFile());
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $backup->getFilename()
        );

        return $response;
    }

    /**
     * @Route("/{id}/information", name="backup_information" )
     */
    public function restoreAction(Request $request)
    {

        $config      = $this->getParameter('sn_backup');
        $list = BackupList::factory();
        $archive      = $list->getDumps()[$request->get('id')];
        $tmp         = sprintf("/tmp/%s", $archive->getFilename());

        $fs = new Filesystem();
        $fs->copy($archive->getFile(), $tmp);

        $filelist = CommandHelper::executeCommand(sprintf("tar -tf %s",
            $tmp
        ));

        $fs->remove($tmp);

        $backup["files"] = explode("\n", $filelist);
        $backup["name"]  = $archive->getFilename();


        return $this->render('SNBackupBundle:Default:info.html.twig',
            array(
                "backup" => $backup
            ));
    }
}
