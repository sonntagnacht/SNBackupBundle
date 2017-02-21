<?php
/**
 * BugNerd
 * Created by PhpStorm.
 * File: GetCommand.php
 * User: thomas
 * Date: 09.02.17
 * Time: 14:31
 */

namespace SN\BackupBundle\Command;


use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GetCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('sn:backup:get')
            ->addArgument('id', InputArgument::OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getArgument('id') != null) {
//            $this->restoreBackup($input->getArgument('id'), $output, $input);
        } else {
            $this->renderList($output);
        }
    }

    protected function renderList(OutputInterface $output)
    {
        $config = sprintf("%s/../backup.json", $this->getContainer()->get('kernel')->getRootDir());
        $data   = file_get_contents($config);
        $output->writeln($data);
    }
}