<?php

namespace SN\BackupBundle;

use SN\BackupBundle\Model\Config;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SNBackupBundle extends Bundle
{
    public function boot()
    {
        Config::setConfig($this->container->getParameter('sn_backup'));
        Config::isGaufrette($this->container);
    }
}
