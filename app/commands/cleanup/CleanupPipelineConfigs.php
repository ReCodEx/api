<?php

namespace App\Console;

use App\Model\Entity\Pipeline;
use App\Model\Repository\Pipelines;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A console command that removes pipeline configurations that are not associated with any pipeline and were created
 * before more than a given amount of days
 */
class CleanupPipelineConfigs extends Command
{

    /** @var Pipelines */
    private $pipelines;

    /** @var EntityManager */
    private $entityManager;

    public function __construct(Pipelines $pipelines, EntityManager $entityManager)
    {
        parent::__construct();
        $this->pipelines = $pipelines;
        $this->entityManager = $entityManager;
    }

    protected function configure()
    {
        $this->setName('db:cleanup:pipeline-configs')->setDescription(
            'Remove unused pipeline configs that are older than 14 days.'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $now = new DateTime();
        $deleted = 0;

        /** @var string[] $usedConfigs */
        $usedConfigs = [];

        /** @var Pipeline $pipeline */
        foreach ($this->pipelines->findAllAndIReallyMeanAllOkay() as $pipeline) {
            $usedConfigs[] = $pipeline->getPipelineConfig()->getId();
        }

        $deleteQuery = $this->entityManager->createQuery(
            '
      DELETE FROM App\Model\Entity\PipelineConfig c
      WHERE c.createdAt <= :date AND c.id NOT IN (:ids)
    '
        );

        $limit = clone $now;
        $limit->modify("-14 days");

        $deleteQuery->setParameter("date", $limit);
        $deleteQuery->setParameter("ids", $usedConfigs, Connection::PARAM_STR_ARRAY);

        $deleted += $deleteQuery->execute();
        $output->writeln(sprintf("Removed %d unused pipeline config entities", $deleted));
        return 0;
    }
}
