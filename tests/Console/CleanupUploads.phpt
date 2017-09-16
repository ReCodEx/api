<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\Console\CleanupUploads;
use App\Model\Repository\UploadedFiles;
use App\Model\Repository\Users;
use App\Model\Entity\UploadedFile;
use Tester\Assert;


/**
 * @testCase
 */
class TestCleanupUploads extends Tester\TestCase
{
  /** @var CleanupUploads */
  protected $command;

  /** @var Kdyby\Doctrine\EntityManager */
  protected $em;

  /** @var Nette\DI\Container */
  private $container;

  /** @var Users */
  private $users;

  /** @var UploadedFiles */
  private $uploadedFiles;

  public function __construct()
  {
    global $container;
    $this->container = $container;
    $this->em = PresenterTestHelper::prepareDatabase($container);
    $this->users = $container->getByType(Users::class);
    $this->uploadedFiles = $container->getByType(UploadedFiles::class);
  }

  protected function setUp()
  {
    PresenterTestHelper::fillDatabase($this->container);
    $this->command = $this->container->getByType(CleanupUploads::class);
  }

  protected function tearDown()
  {
    Mockery::close();
  }

  public function testCleanup()
  {
    $user = current($this->users->findAll());

    // Remove fixtures and stuff
    foreach ($this->uploadedFiles->findAll() as $file) {
      $this->uploadedFiles->remove($file, FALSE);
    }

    // Insert our own uploaded files
    for ($i = 0; $i < 5; $i++) {
      $uploadedFile = new UploadedFile("filenameRecent", new DateTime(), 1, $user, "url");
      $this->uploadedFiles->persist($uploadedFile, FALSE);
    }

    for ($i = 0; $i < 5; $i++) {
      $uploadedFile = new UploadedFile("filenameOld", (new DateTime())->modify("-1 year"), 1, $user, "url");
      $this->uploadedFiles->persist($uploadedFile, FALSE);
    }

    $this->uploadedFiles->flush();

    $this->command->run(
      new Symfony\Component\Console\Input\StringInput(""),
      new Symfony\Component\Console\Output\NullOutput()
    );

    /** @var UploadedFile $file */
    foreach ($this->uploadedFiles->findAll() as $file) {
      Assert::notEqual("filenameOld", $file->getName());
    }

    Assert::equal(5, count($this->uploadedFiles->findAll()));
  }
}

$testCase = new TestCleanupUploads();
$testCase->run();
