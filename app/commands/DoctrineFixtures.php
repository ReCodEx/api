<?php

namespace App\Console;

use Doctrine;
use Entity;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Zenify\DoctrineFixtures\Contract\Alice\AliceLoaderInterface;
use Kdyby\Doctrine\EntityManager;

class DoctrineFixtures extends Command {

  /**
   * @inject @var EntityManager
   */
  public $em;

  /**
   * @var AliceLoaderInterface
   */
  private $aliceLoader;


  public function __construct(AliceLoaderInterface $aliceLoader) {
    parent::__construct();
    $this->aliceLoader = $aliceLoader;
  }

  protected function configure() {
    $this->setName('db:fill')->setDescription('Fill database with initial data.');
  }

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