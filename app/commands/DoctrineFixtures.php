<?php

namespace App\Console;

use Doctrine\DBAL;
use Nette\Utils\Finder;
use Nette\Utils\Strings;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zenify\DoctrineFixtures\Contract\Alice\AliceLoaderInterface;

/**
 * Fill in database with initial data. Actual data are stored in YAML file
 * in add/fixtures directory. Also, 'db:fill' command is registered to provide
 * convenient usage of this function.
 */
class DoctrineFixtures extends Command {

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
   * Constructor, some magic with fixtures
   * @param AliceLoaderInterface $aliceLoader Fixture loader
   * @param DBAL\Connection $dbConnection Database connection
   */
  public function __construct(AliceLoaderInterface $aliceLoader, DBAL\Connection $dbConnection) {
    parent::__construct();
    $this->aliceLoader = $aliceLoader;
    $this->dbConnection = $dbConnection;
  }

  /**
   * Register the 'db:fill' command in the framework
   */
  protected function configure() {
    $this->setName('db:fill')->setDescription('Clear the database and fill it with initial data.');
    $this->addArgument('groups', InputArgument::IS_ARRAY, 'Fixture groups to be loaded', ['base']);
  }

  /**
   * Execute the database filling.
   * @param InputInterface $input Console input, not used
   * @param OutputInterface $output Console output for logging
   * @return int 0 on success, 1 on error
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $this->clearDatabase();

    $fixtureDir = __DIR__ . '/../../fixtures';
    $fixtureFiles = [];

    foreach ($input->getArgument("groups") as $group) {
      $groupFiles = [];

      /** @var SplFileInfo $file */
      foreach (Finder::findFiles("*.neon", "*.yml", "*.yaml")->in($fixtureDir . "/" . $group) as $file) {
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
    $this->dbConnection->executeQuery("SET FOREIGN_KEY_CHECKS = 0");

    foreach ($this->dbConnection->getSchemaManager()->listTables() as $table) {
      $tableName = $table->getQuotedName($this->dbConnection->getDatabasePlatform());
      $tableName = Strings::replace($tableName, "/``(.*)``/", "`\1``");
      $this->dbConnection->executeQuery(sprintf("TRUNCATE %s", $tableName));
    }

    $this->dbConnection->executeQuery("SET FOREIGN_KEY_CHECKS = 1");
  }
}
