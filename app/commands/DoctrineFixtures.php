<?php

namespace App\Console;

use Doctrine;
use Entity;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zenify\DoctrineFixtures\Contract\Alice\AliceLoaderInterface;
use Kdyby\Doctrine\EntityManager;

/**
 * Fill in database with initial data. Actual data are stored in YAML file
 * in add/fixtures directory. Also, 'db:fill' command is registered to provide
 * convenient usage of this function.
 */
class DoctrineFixtures extends Command {

  /**
   * @inject @var EntityManager Entity manager
   */
  public $em;

  /**
   * @var AliceLoaderInterface Loader of YAML files with database values
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
  }

  /**
   * Execute the databaze filling.
   * @param InputInterface $input Console input, not used
   * @param OutputInterface $output Console output for logging
   * @return int 0 on success, 1 on error
   */
  protected function execute(InputInterface $input, OutputInterface $output) {
    try {
      // arg can be used file(s) or dir(s) with fixtures
      $this->aliceLoader->load(__DIR__ . '/../../fixtures');
      $output->writeLn('<info>[OK] - DB:FILL</info>');
      return 0; // zero return code means everything is ok
    } catch (\Exception $exc) {
      $output->writeLn('<error>DB:FILL - ' . $exc->getMessage() . '</error>');
      return 1; // non-zero return code means error
    }
  }
}