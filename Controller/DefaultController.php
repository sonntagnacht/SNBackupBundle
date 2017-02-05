<?php

namespace SN\BackupBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('SNBackupBundle:Default:index.html.twig');
    }
}
