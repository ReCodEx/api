<?php

namespace App\Console;

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
   * Constructor, some magic with fixtures
   * @param AliceLoaderInterface $aliceLoader Stuff to load my YAML
   */
  public function __construct(AliceLoaderInterface $aliceLoader) {
    parent::__construct();
    $this->aliceLoader = $aliceLoader;
  }

  /**
   * Register the 'db:fill' command in the framework
   */
  protected function configure() {
    $this->setName('db:fill')->setDescription('Fill database with initial data.');
    $this->addArgument('groups', InputArgument::IS_ARRAY, 'Fixture groups to be loaded', ['base']);
  }

  /**
   * Execute the databaze filling.
   * @param InputInterface $input Console input, not used
   * @param OutputInterface $output Console output for logging
   * @return int 0 on success, 1 on error
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    $groups = $input->getArgument("groups");

    foreach ($groups as $group) {
      $this->aliceLoader->load(__DIR__ . '/../../fixtures/' . $group);
    }

    $output->writeln('<info>[OK] - DB:FILL</info>');
    return 0;
  }
}
