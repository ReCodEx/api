<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Console\CleanupUploads;
use App\Model\Repository\UploadedFiles;
use App\Model\Repository\Users;
use App\Model\Entity\UploadedFile;
use App\Helpers\FileStorageManager;
use App\Helpers\TmpFilesHelper;
use App\Helpers\FileStorage\LocalFileStorage;
use App\Helpers\FileStorage\LocalHashFileStorage;
use Doctrine\ORM\EntityManagerInterface;
use Tester\Assert;


/**
 * @testCase
 */
class TestCleanupUploads extends Tester\TestCase
{
    /** @var CleanupUploads */
    protected $command;

    /** @var EntityManagerInterface */
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
        $this->em = PresenterTestHelper::getEntityManager($container);
        $this->users = $container->getByType(Users::class);
        $this->uploadedFiles = $container->getByType(UploadedFiles::class);

        $lfs = Mockery::mock(LocalFileStorage::class);
        $lfs->shouldReceive("delete")->with(Mockery::any())->andReturn(true)->times(5);
        $lfs->shouldReceive("deleteByFilter")->with(Mockery::any())->andReturn(0)->times(1);

        // patch container, since we cannot create actual file storage manarer
        $fsName = current($this->container->findByType(FileStorageManager::class));
        $this->container->removeService($fsName);
        $this->container->addService($fsName, new FileStorageManager(
            $lfs,
            Mockery::mock(LocalHashFileStorage::class),
            Mockery::mock(TmpFilesHelper::class),
            ""
        ));
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
            $this->uploadedFiles->remove($file, false);
        }

        // Insert our own uploaded files
        for ($i = 0; $i < 5; $i++) {
            $uploadedFile = new UploadedFile("filenameRecent", new DateTime(), 1, $user);
            $this->uploadedFiles->persist($uploadedFile, false);
        }

        for ($i = 0; $i < 5; $i++) {
            $uploadedFile = new UploadedFile("filenameOld", (new DateTime())->modify("-1 year"), 1, $user);
            $this->uploadedFiles->persist($uploadedFile, false);
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
