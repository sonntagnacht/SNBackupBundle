<?php

namespace SN\BackupBundle\Controller;

use SN\BackupBundle\Model\Backup;
use SN\BackupBundle\Model\BackupList;
use SN\ToolboxBundle\Helper\CommandHelper;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

class BackupController extends Controller
{
    /**
     * @Route("/", name="sn_backup_list")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction()
    {
        $config  = $this->getParameter('sn_backup');
        $backups = array_reverse(BackupList::factory()->getDumps()->toArray());

        return $this->render('SNBackupBundle:Backup:index.html.twig',
            array(
                "backups"     => $backups,
                "delete_form" => $this->deleteForm()->createView()
            ));
    }

    /**
     * @Route("/create", name="sn_backup_create")
     * @param Request $request
     * @return Response
     */
    public function createAction(Request $request)
    {
        $root_dir = $this->get('kernel')->getRootDir();
        $cmd      = sprintf('php %s/../bin/console sn:backup:dump', $root_dir);
        CommandHelper::executeCommand($cmd);
        $url = $this->generateUrl('sn_backup_list');

        $response = $this->render('SNBackupBundle:Backup:create.html.twig');
        $response->headers->set('Refresh', '3; url=' . $url);

        return $response;
    }

    /**
     * @Route("/{id}/download", name="sn_backup_download")
     * @param Request $request
     * @return Response
     */
    public function downloadAction(Request $request)
    {
        $list    = BackupList::factory();
        $backups = array_reverse(BackupList::factory()->getDumps()->toArray());
        /**
         * @var $backup Backup
         */
        $backup = $backups[$request->get('id')];

        $response    = new Response($backup->getFile()->getContent());
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $backup->getFilename()
        );
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    /**
     * @Route("/{id}/information", name="sn_backup_information" )
     * @param Request $request
     * @return Response
     */
    public function informationAction(Request $request)
    {

        $config  = $this->getParameter('sn_backup');
        $backups = array_reverse(BackupList::factory()->getDumps()->toArray());
        $backup  = $backups[$request->get('id')];


        return $this->render('SNBackupBundle:Backup:info.html.twig',
            array(
                "backup" => $backup
            ));
    }

    /**
     * @param Request $request
     * @Route("/delete", name="sn_backup_delete")
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteAction(Request $request)
    {
        $timestamp = $request->get('timestamp');
        $list      = BackupList::factory();
        $backup    = $list->getBackup(new \DateTime("@$timestamp"));
        $list->removeBackup($backup);
        $backup->remove();

        return $this->redirectToRoute('sn_backup_list');

    }

    /**
     * @return \Symfony\Component\Form\Form|\Symfony\Component\Form\FormInterface
     */
    protected function deleteForm()
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('sn_backup_delete'))
            ->setMethod('DELETE')
            ->getForm();
    }
}
