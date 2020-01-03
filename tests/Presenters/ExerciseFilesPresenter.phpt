<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Exceptions\NotFoundException;
use App\Helpers\ExerciseFileStorage;
use App\Helpers\ExerciseRestrictionsConfig;
use App\Helpers\FileServerProxy;
use App\Model\Entity\AttachmentFile;
use App\Model\Entity\UploadedFile;
use App\V1Module\Presenters\ExerciseFilesPresenter;
use App\Model\Entity\SupplementaryExerciseFile;
use Tester\Assert;


/**
 * @testCase
 */
class TestExerciseFilesPresenter extends Tester\TestCase
{
    /** @var ExerciseFilesPresenter */
    protected $presenter;

    /** @var Kdyby\Doctrine\EntityManager */
    protected $em;

    /** @var  Nette\DI\Container */
    protected $container;

    /** @var App\Model\Repository\SupplementaryExerciseFiles */
    protected $supplementaryFiles;

    /** @var App\Model\Repository\Logins */
    protected $logins;

    /** @var Nette\Security\User */
    private $user;

    /** @var App\Model\Repository\Exercises */
    protected $exercises;

    /** @var App\Model\Repository\AttachmentFiles */
    protected $attachmentFiles;

    public function __construct()
    {
        global $container;
        $this->container = $container;
        $this->em = PresenterTestHelper::getEntityManager($container);
        $this->user = $container->getByType(\Nette\Security\User::class);
        $this->supplementaryFiles = $container->getByType(\App\Model\Repository\SupplementaryExerciseFiles::class);
        $this->logins = $container->getByType(\App\Model\Repository\Logins::class);
        $this->exercises = $container->getByType(App\Model\Repository\Exercises::class);
        $this->attachmentFiles = $container->getByType(\App\Model\Repository\AttachmentFiles::class);
    }

    protected function setUp()
    {
        PresenterTestHelper::fillDatabase($this->container);

        $this->presenter = PresenterTestHelper::createPresenter($this->container, ExerciseFilesPresenter::class);
    }

    protected function tearDown()
    {
        Mockery::close();

        if ($this->user->isLoggedIn()) {
            $this->user->logout(true);
        }
    }

    public function testSupplementaryFilesUpload()
    {
        // Mock file server setup
        $filename1 = "task1.txt";
        $filename2 = "task2.txt";
        $fileServerResponse1 = [
            $filename1 => "https://fs/tasks/hash1",
        ];
        $fileServerResponse2 = [
            $filename2 => "https://fs/tasks/hash2"
        ];
        $fileServerResponseMerged = array_merge($fileServerResponse1, $fileServerResponse2);

        $user = $this->presenter->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

        $file1 = new UploadedFile($filename1, new \DateTime(), 0, $user, $filename1);
        $file2 = new UploadedFile($filename2, new \DateTime(), 0, $user, $filename2);
        $this->presenter->uploadedFiles->persist($file1);
        $this->presenter->uploadedFiles->persist($file2);
        $this->presenter->uploadedFiles->flush();

        /** @var FileServerProxy|Mockery\Mock $fileServerMock */
        $fileServerMock = Mockery::mock(FileServerProxy::class);
        $fileServerMock->shouldReceive("sendSupplementaryFiles")->with([$file1])->andReturn(
            $fileServerResponse1
        )->between(0, 1);
        $fileServerMock->shouldReceive("sendSupplementaryFiles")->with([$file2])->andReturn(
            $fileServerResponse2
        )->between(0, 1);
        $fileServerMock->shouldReceive("sendSupplementaryFiles")->with([$file1, $file2])->andReturn(
            $fileServerResponseMerged
        )->between(0, 1);
        $this->presenter->supplementaryFileStorage = new ExerciseFileStorage($fileServerMock);

        // mock file storage
        $mockFileStorage = Mockery::mock(\App\Helpers\UploadedFileStorage::class);
        $mockFileStorage->shouldDeferMissing();
        $mockFileStorage->shouldReceive("delete")->with($file1)->once();
        $mockFileStorage->shouldReceive("delete")->with($file2)->once();
        $this->presenter->uploadedFileStorage = $mockFileStorage;

        // Finally, the test itself
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $exercise = current($this->presenter->exercises->findAll());
        $files = [$file1->getId(), $file2->getId()];

        /** @var Nette\Application\Responses\JsonResponse $response */
        $response = $this->presenter->run(
            new Nette\Application\Request(
                "V1:ExerciseFiles", "POST", [
                "action" => 'uploadSupplementaryFiles',
                'id' => $exercise->id
            ], [
                    'files' => $files
                ]
            )
        );

        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $payload = $response->getPayload()['payload'];
        Assert::count(2, $payload);

        foreach ($payload as $item) {
            Assert::type(App\Model\Entity\SupplementaryExerciseFile::class, $item);
        }
    }

    public function testUploadTooManySupplementaryFiles()
    {
        $user = $this->presenter->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);
        $fileLimit = 10;

        $restrictions = new ExerciseRestrictionsConfig(
            [
                "supplementaryFileCountLimit" => $fileLimit
            ]
        );

        $this->presenter->restrictionsConfig = $restrictions;

        $files = [];
        for ($i = 0; $i < $fileLimit * 2; $i++) {
            $files[] = $file = new UploadedFile("...", new \DateTime(), 0, $user, "...");
            $this->presenter->uploadedFiles->persist($file);
        }


        /** @var FileServerProxy|Mockery\Mock $fileServerMock */
        $fileServerMock = Mockery::mock(FileServerProxy::class);
        $fileServerMock->shouldNotReceive("sendSupplementaryFiles");
        $this->presenter->supplementaryFileStorage = new ExerciseFileStorage($fileServerMock);

        // mock file storage
        $mockFileStorage = Mockery::mock(\App\Helpers\UploadedFileStorage::class);
        $mockFileStorage->shouldDeferMissing();
        $mockFileStorage->shouldNotReceive("delete");
        $this->presenter->uploadedFileStorage = $mockFileStorage;

        // Finally, the test itself
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $exercise = current($this->presenter->exercises->findAll());
        $files = array_map(
            function (UploadedFile $file) {
                return $file->getId();
            },
            $files
        );

        /** @var Nette\Application\Responses\JsonResponse $response */
        Assert::exception(
            function () use ($exercise, $files) {
                $this->presenter->run(
                    new Nette\Application\Request(
                        "V1:ExerciseFiles", "POST", [
                        "action" => 'uploadSupplementaryFiles',
                        'id' => $exercise->id
                    ], [
                            'files' => $files
                        ]
                    )
                );
            },
            \App\Exceptions\InvalidArgumentException::class
        );
    }

    public function testUploadTooBigSupplementaryFiles()
    {
        $user = $this->presenter->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);
        $sizeLimit = 5 * 1024;

        $restrictions = new ExerciseRestrictionsConfig(
            [
                "supplementaryFileSizeLimit" => $sizeLimit
            ]
        );

        $this->presenter->restrictionsConfig = $restrictions;

        $files = [];
        for ($i = 0; $i < 10; $i++) {
            $files[] = $file = new UploadedFile("...", new \DateTime(), 1024, $user, "...");
            $this->presenter->uploadedFiles->persist($file);
        }


        /** @var FileServerProxy|Mockery\Mock $fileServerMock */
        $fileServerMock = Mockery::mock(FileServerProxy::class);
        $fileServerMock->shouldNotReceive("sendSupplementaryFiles");
        $this->presenter->supplementaryFileStorage = new ExerciseFileStorage($fileServerMock);

        // mock file storage
        $mockFileStorage = Mockery::mock(\App\Helpers\UploadedFileStorage::class);
        $mockFileStorage->shouldDeferMissing();
        $mockFileStorage->shouldNotReceive("delete");
        $this->presenter->uploadedFileStorage = $mockFileStorage;

        // Finally, the test itself
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $exercise = current($this->presenter->exercises->findAll());
        $files = array_map(
            function (UploadedFile $file) {
                return $file->getId();
            },
            $files
        );

        /** @var Nette\Application\Responses\JsonResponse $response */
        Assert::exception(
            function () use ($exercise, $files) {
                $this->presenter->run(
                    new Nette\Application\Request(
                        "V1:ExerciseFiles", "POST", [
                        "action" => 'uploadSupplementaryFiles',
                        'id' => $exercise->id
                    ], [
                            'files' => $files
                        ]
                    )
                );
            },
            \App\Exceptions\InvalidArgumentException::class
        );
    }

    public function testGetSupplementaryFiles()
    {
        $token = PresenterTestHelper::loginDefaultAdmin($this->container);

        // prepare files into exercise
        $user = $this->logins->getUser(PresenterTestHelper::ADMIN_LOGIN, PresenterTestHelper::ADMIN_PASSWORD);
        $exercise = current($this->presenter->exercises->findAll());
        $expectedFile1 = new SupplementaryExerciseFile(
            "name1",
            new DateTime(),
            1,
            "hashName1",
            "fileServerPath1",
            $user,
            $exercise
        );
        $expectedFile2 = new SupplementaryExerciseFile(
            "name2",
            new DateTime(),
            2,
            "hashName2",
            "fileServerPath2",
            $user,
            $exercise
        );
        $this->supplementaryFiles->persist($expectedFile1, false);
        $this->supplementaryFiles->persist($expectedFile2, false);
        $this->supplementaryFiles->flush();

        $request = new Nette\Application\Request(
            "V1:ExerciseFiles", 'GET',
            ['action' => 'getSupplementaryFiles', 'id' => $exercise->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::count(2, $result['payload']);
        $expectedFiles = [$expectedFile1, $expectedFile2];

        sort($expectedFiles);
        sort($result['payload']);
        Assert::equal($expectedFiles, $result['payload']);
    }

    public function testDeleteSupplementaryFile()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $user = $this->presenter->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);
        $exercise = current($this->presenter->exercises->findAll());
        $filesCount = $exercise->getSupplementaryEvaluationFiles()->count();
        $file = new SupplementaryExerciseFile(
            "name1",
            new DateTime(),
            1,
            "hashName1",
            "fileServerPath1",
            $user,
            $exercise
        );
        $this->supplementaryFiles->persist($file);
        Assert::count($filesCount + 1, $exercise->getSupplementaryEvaluationFiles());

        $request = new Nette\Application\Request(
            "V1:ExerciseFiles", 'DELETE',
            [
                'action' => 'deleteSupplementaryFile',
                'id' => $exercise->getId(),
                'fileId' => $file->getId()
            ]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal("OK", $result['payload']);
        Assert::count($filesCount, $exercise->getSupplementaryEvaluationFiles());
    }

    public function testDownloadSupplementaryFilesArchive()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $exercise = current($this->presenter->exercises->findAll());

        $mockFileserverProxy = Mockery::mock(App\Helpers\FileServerProxy::class);
        $mockFileserverProxy->shouldReceive("downloadFile")->andReturn()->zeroOrMoreTimes();
        $this->presenter->fileServerProxy = $mockFileserverProxy;

        $request = new Nette\Application\Request(
            "V1:ExerciseFiles",
            'GET',
            ['action' => 'downloadSupplementaryFilesArchive', 'id' => $exercise->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::same(App\Responses\ZipFilesResponse::class, get_class($response));

        // Check invariants
        Assert::equal("exercise-supplementary-" . $exercise->getId() . '.zip', $response->getName());
    }

    public function testGetAttachmentFiles()
    {
        $token = PresenterTestHelper::loginDefaultAdmin($this->container);

        // prepare files into exercise
        $user = $this->logins->getUser(PresenterTestHelper::ADMIN_LOGIN, PresenterTestHelper::ADMIN_PASSWORD);
        $exercise = $this->presenter->exercises->searchByName("An exercise")[0];
        $expectedFile1 = new AttachmentFile("name1", new DateTime(), 1, "hashName1", $user, $exercise);
        $expectedFile2 = new AttachmentFile("name2", new DateTime(), 2, "hashName2", $user, $exercise);
        $this->attachmentFiles->persist($expectedFile1, false);
        $this->attachmentFiles->persist($expectedFile2, false);
        $this->attachmentFiles->flush();

        $request = new Nette\Application\Request(
            "V1:ExerciseFiles", 'GET',
            ['action' => 'getAttachmentFiles', 'id' => $exercise->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::count(2, $result['payload']);

        $expectedFilesIds = [$expectedFile1->getId(), $expectedFile2->getId()];
        sort($expectedFilesIds);

        $payloadIds = array_map(
            function ($item) {
                return $item->getId();
            },
            $result['payload']
        );
        sort($payloadIds);

        Assert::equal($expectedFilesIds, $payloadIds);
    }

    public function testDeleteAttachmentFile()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $user = $this->presenter->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);
        $exercise = current($this->presenter->exercises->findAll());
        $filesCount = $exercise->getAttachmentFiles()->count();
        $file = new AttachmentFile("name", new DateTime(), 1, "localPath", $user, $exercise);
        $this->attachmentFiles->persist($file);
        Assert::count($filesCount + 1, $exercise->getAttachmentFiles());

        $request = new Nette\Application\Request(
            "V1:ExerciseFiles", 'DELETE',
            [
                'action' => 'deleteAttachmentFile',
                'id' => $exercise->getId(),
                'fileId' => $file->getId()
            ]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal("OK", $result['payload']);
        Assert::count($filesCount, $exercise->getAttachmentFiles());
    }

    public function testDownloadAttachmentFilesArchive()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $exercise = current($this->presenter->exercises->findAll());

        $request = new Nette\Application\Request(
            "V1:ExerciseFiles",
            'GET',
            ['action' => 'downloadAttachmentFilesArchive', 'id' => $exercise->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::same(App\Responses\ZipFilesResponse::class, get_class($response));

        // Check invariants
        Assert::equal("exercise-attachment-" . $exercise->getId() . '.zip', $response->getName());
    }

}

(new TestExerciseFilesPresenter())->run();
