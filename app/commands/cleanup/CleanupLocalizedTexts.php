<?php

namespace App\Console;

use App\Model\Entity\Assignment;
use App\Model\Entity\Exercise;
use App\Model\Repository\Assignments;
use App\Model\Repository\Exercises;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A console command that removes localized texts that are not associated with any entity and were created before more
 * than a given amount of days
 */
class CleanupLocalizedTexts extends Command
{
    protected static $defaultName = 'db:cleanup:localized-texts';

    /** @var Exercises */
    private $exercises;

    /** @var Assignments */
    private $assignments;

    /** @var EntityManagerInterface */
    private $entityManager;

    public function __construct(Exercises $exercises, Assignments $assignments, EntityManagerInterface $entityManager)
    {
        parent::__construct();
        $this->exercises = $exercises;
        $this->assignments = $assignments;
        $this->entityManager = $entityManager;
    }

    protected function configure()
    {
        $this->setName('db:cleanup:localized-texts')->setDescription(
            'Remove unused localized texts that are older than 14 days.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $now = new DateTime();
        $deleted = 0;

        /** @var string[] $usedTexts */
        $usedTexts = [];

        /** @var Exercise $exercise */
        foreach ($this->exercises->findAllAndIReallyMeanAllOkay() as $exercise) {
            foreach ($exercise->getLocalizedTexts() as $localizedText) {
                $usedTexts[] = $localizedText->getId();
            }
        }

        /** @var Assignment $assignment */
        foreach ($this->assignments->findAllAndIReallyMeanAllOkay() as $assignment) {
            foreach ($assignment->getLocalizedTexts() as $localizedText) {
                $usedTexts[] = $localizedText->getId();
            }
        }

        $deleteQuery = $this->entityManager->createQuery(
            'DELETE FROM App\Model\Entity\LocalizedExercise l WHERE l.createdAt <= :date AND l.id NOT IN (:ids)'
        );

        $limit = clone $now;
        $limit->modify("-14 days");
        $deleteQuery->setParameter("date", $limit);

        $deleteQuery->setParameter("ids", $usedTexts, Connection::PARAM_STR_ARRAY);

        $deleted += $deleteQuery->execute();

        $output->writeln(sprintf("Removed %d unused entities", $deleted));
        return 0;
    }
}
