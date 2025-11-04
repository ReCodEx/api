<?php

namespace App\Console;

use App\Model\Entity\Pipeline;
use Doctrine\DBAL;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Id\AssignedGenerator;
use Nette\Utils\Finder;
use Nette\Utils\FileInfo;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Zenify\DoctrineFixtures\Contract\Alice\AliceLoaderInterface;

/**
 * Fill in database with initial data. Actual data are stored in YAML file
 * in add/fixtures directory. Also, 'db:fill' command is registered to provide
 * convenient usage of this function.
 */
#[AsCommand(name: 'db:fill', description: 'Clear the database and fill it with initial data.')]
class DoctrineFixtures extends Command
{
    protected static $defaultName = 'db:fill';

    /**
     * Loader of YAML files with database values
     * @var AliceLoaderInterface
     */
    private $aliceLoader;

    /**
     * Database connection used to clear database
     * @var DBAL\Connection
     */
    private $dbConnection;

    /**
     * Entity manager used for setting of db metadata
     * @var EntityManagerInterface
     */
    private $em;

    /**
     * Constructor, some magic with fixtures
     * @param AliceLoaderInterface $aliceLoader Fixture loader
     * @param DBAL\Connection $dbConnection Database connection
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        AliceLoaderInterface $aliceLoader,
        DBAL\Connection $dbConnection,
        EntityManagerInterface $entityManager
    ) {
        parent::__construct();
        $this->aliceLoader = $aliceLoader;
        $this->dbConnection = $dbConnection;
        $this->em = $entityManager;
    }

    /**
     * Register the 'db:fill' command in the framework
     */
    protected function configure()
    {
        $this->addOption(
            'test',
            't',
            InputOption::VALUE_OPTIONAL,
            'Determines if command was executed within tests',
            false
        );
        $this->addArgument('groups', InputArgument::IS_ARRAY, 'Fixture groups to be loaded', ['base']);
    }

    /**
     * Execute the database filling.
     * @param InputInterface $input Console input, not used
     * @param OutputInterface $output Console output for logging
     * @return int 0 on success, 1 on error
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->clearDatabase();

        if ($input->getOption("test")) {
            // Pipeline fixtures can set their own ids
            $metadata = $this->em->getClassMetadata(Pipeline::class);
            $metadata->setIdGenerator(new AssignedGenerator());
        }

        $fixtureDir = __DIR__ . '/../../fixtures';
        $fixtureFiles = [];

        foreach ($input->getArgument("groups") as $group) {
            $groupFiles = [];

            /** @var FileInfo $file */
            foreach (
                Finder::findFiles("*.neon", "*.yml", "*.yaml", "*.json")
                    ->in($fixtureDir . "/" . $group) as $file
            ) {
                $groupFiles[] = $file->getRealPath();
            }

            sort($groupFiles);
            $fixtureFiles = array_merge($fixtureFiles, $groupFiles);
        }

        $this->aliceLoader->load($fixtureFiles);

        $output->writeln('<info>[OK] - DB:FILL</info>');
        return 0;
    }

    /**
     * Truncate all tables in the database
     */
    protected function clearDatabase()
    {
        $platform = $this->dbConnection->getDatabasePlatform()->getName();

        if ($platform === 'mysql') {
            $this->dbConnection->executeQuery("SET FOREIGN_KEY_CHECKS = 0");
        } else {
            if ($platform === 'sqlite') {
                $this->dbConnection->executeQuery("PRAGMA foreign_keys = OFF");
            }
        }

        foreach ($this->dbConnection->getSchemaManager()->listTables() as $table) {
            $tableName = $table->getName();
            if ($tableName === "doctrine_migrations") {
                // do not clear migrations table... it is crucial
                continue;
            }

            if ($platform === 'mysql') {
                if (!str_starts_with($tableName, '``')) {
                    $tableName = '`' . $tableName . '`';
                }
            } else {
                if ($platform === 'sqlite') {
                    if (!str_starts_with($tableName, '``')) {
                        $tableName = '"' . $tableName . '"';
                    }
                }
            }

            if ($platform === "mysql") {
                $this->dbConnection->executeQuery(sprintf("TRUNCATE %s", $tableName));
            } else {
                if ($platform === "sqlite") {
                    $this->dbConnection->executeQuery(sprintf("DELETE FROM %s", $tableName));
                }
            }
        }

        if ($platform === 'pdo_mysql') {
            $this->dbConnection->executeQuery("SET FOREIGN_KEY_CHECKS = 1");
        } else {
            if ($platform === 'sqlite') {
                $this->dbConnection->executeQuery("PRAGMA foreign_keys = ON");
            }
        }
    }
}
