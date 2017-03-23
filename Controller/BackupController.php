<?php

namespace SN\BackupBundle\Controller;

use SN\BackupBundle\Model\BackupList;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

class BackupController extends Controller
{
    /**
     * @Route("/", name="backup_list")
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
     * @Route("/{id}/download", name="backup_download")
     * @param Request $request
     * @return Response
     */
    public function downloadAction(Request $request)
    {
        $list   = BackupList::factory();
        $backup = $list->getDumps()[$request->get('id')];

        $response    = new Response($backup->getFile()->getContent());
        $disposition = $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $backup->getFilename()
        );
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    /**
     * @Route("/{id}/information", name="backup_information" )
     */
    public function restoreAction(Request $request)
    {

        $config  = $this->getParameter('sn_backup');
        $list    = BackupList::factory();
        $archive = $list->getDumps()[$request->get('id')];


        return $this->render('SNBackupBundle:Backup:info.html.twig',
            array(
                "backup" => $archive
            ));
    }

    /**
     * @param Request $request
     * @Route("/delete", name="backup_delete")
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function deleteAction(Request $request)
    {
        $timestamp = $request->get('timestamp');
        $list      = BackupList::factory();
        $backup    = $list->getBackup($timestamp);
        $list->removeBackup($backup);
        $backup->remove();

        return $this->redirectToRoute('backup_list');

    }

    /**
     * @return \Symfony\Component\Form\Form|\Symfony\Component\Form\FormInterface
     */
    protected function deleteForm()
    {
        return $this->createFormBuilder()
            ->setAction($this->generateUrl('backup_delete'))
            ->setMethod('DELETE')
            ->getForm();
    }
}
