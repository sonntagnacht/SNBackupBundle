<?php
/**
 * SNBundle
 * Created by PhpStorm.
 * File: CleanupCommand.php
 * User: thomas
 * Date: 16.03.17
 * Time: 12:41
 */

namespace SN\BackupBundle\Command;


use Doctrine\Common\Collections\ArrayCollection;
use SN\BackupBundle\Model\Backup;
use SN\BackupBundle\Model\BackupList;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class CleanupCommand extends ContainerAwareCommand
{
    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    public function configure()
    {
        $backupTypeDescription = sprintf('The type of the backup [%s]',
            join(',', array(Backup::TYPE_DAILY, Backup::TYPE_MONTHLY, Backup::TYPE_WEEKLY, Backup::TYPE_YEARLY)));

        $this->setName('sn:backup:cleanup')
            ->setDescription('Delete older Backups')
            ->addArgument('type',
                InputArgument::REQUIRED,
                $backupTypeDescription)
            ->addArgument('older than', InputArgument::REQUIRED)
            ->addOption('force', 'f', InputOption::VALUE_NONE);
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $type = $input->getArgument('type');
        $time = strtoupper($input->getArgument('older than'));
        $time = new \DateInterval(sprintf("P%s", $time));

        if (!in_array($type,
            array(
                Backup::TYPE_DAILY,
                Backup::TYPE_WEEKLY,
                Backup::TYPE_MONTHLY,
                Backup::TYPE_YEARLY
            ))
        ) {
            throw new \InvalidArgumentException(sprintf('The type [%s] is unknown.', $type));
        }

        $backupList = BackupList::factory($type);
        $now        = new \DateTime();
        $now->sub($time);
        $backupRemove = new ArrayCollection();

        foreach ($backupList->getDumps() as $backup) {
            if ($backup->getDateTime() <= $now) {
                $backupRemove->add($backup);
            }
        }

        if ($backupRemove->count() == 0) {
            return;
        }

        if (!$input->getOption('force')) {
            $this->renderList($output, $backupRemove->toArray());

            $helper   = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                sprintf(
                    'Do you want delete this backup(s) [y/N]? ')
                , false,
                '/^(y)/i');

            if (!$helper->ask($input, $output, $question)) {
                return;
            }
        }

        /**
         * @var $backup Backup
         */
        foreach ($backupRemove as $backup) {
            $backupList->removeBackup($backup);
        }

        $output->writeln(sprintf("%s backup(s) successfully removed", $backupRemove->count()));

    }

    /**
     * @param OutputInterface $output
     * @param array $backups
     */
    protected function renderList(OutputInterface $output, array $backups)
    {
        $backup = new Table($output);
        $backup->setHeaders(array("Date", "Type", "Version", "Commit"));
        foreach ($backups as $id => $dump) {
            $backup->addRow(array(
                date("d/m/Y H:i", $dump->getTimestamp()),
                $dump->getType(),
                $dump->getVersion(),
                $dump->getCommit(true)
            ));
        }
        $backup->render();
    }

}