<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Helpers\FileStorageManager;
use App\Helpers\FileStorage\LocalFileStorage;
use App\Helpers\FileStorage\LocalHashFileStorage;
use App\Helpers\FileStorage\LocalImmutableFile;
use App\Helpers\ExercisesConfig;
use App\Helpers\TmpFilesHelper;
use App\Model\Entity\Assignment;
use App\Model\Entity\AttachmentFile;
use App\Model\Entity\Exercise;
use App\Model\Entity\ExerciseFileLink;
use App\Model\Entity\UploadedFile;
use App\Model\Repository\Assignments;
use App\Model\Repository\AttachmentFiles;
use App\Model\Repository\Exercises;
use App\Model\Repository\ExerciseFiles;
use App\Model\Repository\Groups;
use App\Model\Repository\Logins;
use App\V1Module\Presenters\ExerciseFilesPresenter;
use App\Model\Entity\ExerciseFile;
use Doctrine\ORM\EntityManagerInterface;
use Tester\Assert;

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

/**
 * @testCase
 */
class TestExerciseFilesPresenter extends Tester\TestCase
{
    /** @var ExerciseFilesPresenter */
    protected $presenter;

    /** @var EntityManagerInterface */
    protected $em;

    /** @var  Nette\DI\Container */
    protected $container;

    /** @var ExerciseFiles */
    protected $exerciseFiles;

    /** @var Logins */
    protected $logins;

    /** @var Nette\Security\User */
    private $user;

    /** @var Assignments */
    protected $assignments;
    /** @var Groups */
    protected $groups;

    /** @var Exercises */
    protected $exercises;

    /** @var AttachmentFiles */
    protected $attachmentFiles;

    public function __construct()
    {
        global $container;
        $this->container = $container;
        $this->em = PresenterTestHelper::getEntityManager($container);
        $this->user = $container->getByType(\Nette\Security\User::class);
        $this->assignments = $container->getByType(Assignments::class);
        $this->attachmentFiles = $container->getByType(AttachmentFiles::class);
        $this->exercises = $container->getByType(Exercises::class);
        $this->exerciseFiles = $container->getByType(ExerciseFiles::class);
        $this->groups = $container->getByType(Groups::class);
        $this->logins = $container->getByType(Logins::class);

        // patch container, since we cannot create actual file storage manager
        $fsName = current($this->container->findByType(FileStorageManager::class));
        $this->container->removeService($fsName);
        $this->container->addService($fsName, new FileStorageManager(
            Mockery::mock(LocalFileStorage::class),
            Mockery::mock(LocalHashFileStorage::class),
            Mockery::mock(TmpFilesHelper::class),
            ""
        ));
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

    public function testExerciseFilesUpload()
    {
        $user = $this->presenter->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

        // Mock file server setup
        $filename1 = "task1.txt";
        $filename2 = "task2.txt";
        $file1 = new UploadedFile($filename1, new \DateTime(), 0, $user);
        $file2 = new UploadedFile($filename2, new \DateTime(), 0, $user);
        $this->presenter->uploadedFiles->persist($file1);
        $this->presenter->uploadedFiles->persist($file2);
        $this->presenter->uploadedFiles->flush();

        $fileStorage = Mockery::mock(FileStorageManager::class);
        $fileStorage->shouldReceive("storeUploadedExerciseFile")->with($file1)->once();
        $fileStorage->shouldReceive("storeUploadedExerciseFile")->with($file2)->once();
        $this->presenter->fileStorage = $fileStorage;

        // Finally, the test itself
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $exercise = current(array_filter(
            $this->presenter->exercises->findAll(),
            function ($exercise) {
                return $exercise->getExerciseFiles()->count() === 0;
            }
        ));
        Assert::truthy($exercise);

        $files = [$file1->getId(), $file2->getId()];

        /** @var Nette\Application\Responses\JsonResponse $response */
        $response = $this->presenter->run(
            new Nette\Application\Request(
                "V1:ExerciseFiles",
                "POST",
                [
                    "action" => 'uploadExerciseFiles',
                    'id' => $exercise->getId()
                ],
                [
                    'files' => $files
                ]
            )
        );

        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $payload = $response->getPayload()['payload'];
        Assert::count(2, $payload);

        foreach ($payload as $item) {
            Assert::type(App\Model\Entity\ExerciseFile::class, $item);
        }
    }

    public function testExerciseFilesUploadOverload()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $user = $this->presenter->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);
        $exercise = current(array_filter(
            $this->presenter->exercises->findAll(),
            function ($exercise) {
                return !$exercise->getFileLinks()->isEmpty();
            }
        ));
        Assert::truthy($exercise);

        $oldFiles = [];
        foreach ($exercise->getExerciseFiles() as $file) {
            /** @var ExerciseFile $file */
            $oldFiles[$file->getName()] = $file->getId();
        }
        $oldFile = $exercise->getFileLinks()->first()->getExerciseFile();
        $oldLinks = [];
        foreach ($exercise->getFileLinks() as $link) {
            if ($link->getExerciseFile()->getId() === $oldFile->getId()) {
                $oldLinks[$link->getKey()] = $link;
            }
        }

        // make an assignment (so we can check it is left unchanged)
        $group = current($this->groups->findAll());
        Assert::truthy($group);

        $assignment = Assignment::assignToGroup($exercise, $group, true, null);
        $this->assignments->persist($assignment);
        $assignmentFiles = [];
        foreach ($assignment->getExerciseFiles() as $file) {
            /** @var ExerciseFile $file */
            $assignmentFiles[$file->getName()] = $file->getId();
        }
        $assignmentLinks = $this->presenter->fileLinks->getLinksMapForAssignment($assignment->getId());

        // prepare a new file
        $filename = $oldFile->getName();
        $file = new UploadedFile($filename, new \DateTime(), 42, $user);
        $this->presenter->uploadedFiles->persist($file);
        $this->presenter->uploadedFiles->flush();

        // Mock file server setup
        $fileStorage = Mockery::mock(FileStorageManager::class);
        $fileStorage->shouldReceive("storeUploadedExerciseFile")->with($file)->once();
        $this->presenter->fileStorage = $fileStorage;

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            "V1:ExerciseFiles",
            "POST",
            [
                "action" => 'uploadExerciseFiles',
                'id' => $exercise->getId()
            ],
            [
                'files' => [$file->getId()]
            ]
        );

        // number of files hasn't changed
        Assert::count(count($oldFiles), $payload);
        foreach ($payload as $item) {
            Assert::type(App\Model\Entity\ExerciseFile::class, $item);
        }

        // all files (except the overloaded one) are unchanged, the overloaded one is new
        $newFileId = null;
        $this->presenter->exercises->refresh($exercise);
        Assert::count(count($oldFiles), $exercise->getExerciseFiles());
        foreach ($exercise->getExerciseFiles() as $file) {
            /** @var ExerciseFile $file */
            Assert::true(array_key_exists($file->getName(), $oldFiles));
            if ($file->getName() !== $filename) {
                Assert::equal($oldFiles[$file->getName()], $file->getId());
            } else {
                Assert::notEqual($oldFiles[$file->getName()], $file->getId());
                Assert::equal(42, $file->getFileSize());
                $newFileId = $file->getId();
            }
        }
        Assert::truthy($newFileId);

        // links has been properly updated
        foreach ($exercise->getFileLinks() as $link) {
            /** @var ExerciseFileLink $link */
            Assert::true(array_key_exists($link->getKey(), $oldLinks));
            $oldLink = $oldLinks[$link->getKey()];
            Assert::equal($oldLink->getSaveName(), $link->getSaveName());
            Assert::equal($oldLink->getRequiredRole(), $link->getRequiredRole());
            Assert::equal($newFileId, $link->getExerciseFile()->getId());
        }

        // assignment is unchanged
        $this->presenter->assignments->refresh($assignment);
        Assert::count(count($assignmentFiles), $assignment->getExerciseFiles());
        foreach ($assignment->getExerciseFiles() as $file) {
            /** @var ExerciseFile $file */
            Assert::true(array_key_exists($file->getName(), $assignmentFiles));
            Assert::equal($assignmentFiles[$file->getName()], $file->getId());
        }

        $newAssignmentLinks = $this->presenter->fileLinks->getLinksMapForAssignment($assignment->getId());
        Assert::same($assignmentLinks, $newAssignmentLinks);
    }

    public function testUploadTooManyExerciseFiles()
    {
        $user = $this->presenter->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);
        $fileLimit = 10;

        $restrictions = new ExercisesConfig(
            [
                "exerciseFileCountLimit" => $fileLimit
            ]
        );

        $this->presenter->restrictionsConfig = $restrictions;

        $files = [];
        for ($i = 0; $i < $fileLimit * 2; $i++) {
            $files[] = $file = new UploadedFile("...", new \DateTime(), 0, $user);
            $this->presenter->uploadedFiles->persist($file);
        }

        $fileStorage = Mockery::mock(FileStorageManager::class);
        $fileStorage->makePartial();
        $fileStorage->shouldNotReceive("storeUploadedExerciseFile");
        $this->presenter->fileStorage = $fileStorage;

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
                        "V1:ExerciseFiles",
                        "POST",
                        [
                            "action" => 'uploadExerciseFiles',
                            'id' => $exercise->getId()
                        ],
                        [
                            'files' => $files
                        ]
                    )
                );
            },
            \App\Exceptions\InvalidApiArgumentException::class
        );
    }

    public function testUploadTooBigExerciseFiles()
    {
        $user = $this->presenter->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);
        $sizeLimit = 5 * 1024;

        $restrictions = new ExercisesConfig(
            [
                "exerciseFileSizeLimit" => $sizeLimit
            ]
        );

        $this->presenter->restrictionsConfig = $restrictions;

        $files = [];
        for ($i = 0; $i < 10; $i++) {
            $files[] = $file = new UploadedFile("...", new \DateTime(), 1024, $user);
            $this->presenter->uploadedFiles->persist($file);
        }

        $fileStorage = Mockery::mock(FileStorageManager::class);
        $fileStorage->makePartial();
        $fileStorage->shouldNotReceive("storeUploadedExerciseFile");
        $this->presenter->fileStorage = $fileStorage;

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
                        "V1:ExerciseFiles",
                        "POST",
                        [
                            "action" => 'uploadExerciseFiles',
                            'id' => $exercise->getId()
                        ],
                        [
                            'files' => $files
                        ]
                    )
                );
            },
            \App\Exceptions\InvalidApiArgumentException::class
        );
    }

    public function testGetExerciseFiles()
    {
        $token = PresenterTestHelper::loginDefaultAdmin($this->container);

        // prepare files into exercise
        $user = $this->logins->getUser(PresenterTestHelper::ADMIN_LOGIN, PresenterTestHelper::ADMIN_PASSWORD, new Nette\Security\Passwords());
        $exercise = current(array_filter(
            $this->presenter->exercises->findAll(),
            function ($exercise) {
                return $exercise->getExerciseFiles()->count() === 0;
            }
        ));
        Assert::truthy($exercise);

        $expectedFile1 = new ExerciseFile(
            "name1",
            new DateTime(),
            1,
            "hashName1",
            $user,
            $exercise
        );
        $expectedFile2 = new ExerciseFile(
            "name2",
            new DateTime(),
            2,
            "hashName2",
            $user,
            $exercise
        );
        $this->exerciseFiles->persist($expectedFile1, false);
        $this->exerciseFiles->persist($expectedFile2, false);
        $this->exerciseFiles->flush();

        $request = new Nette\Application\Request(
            "V1:ExerciseFiles",
            'GET',
            ['action' => 'getExerciseFiles', 'id' => $exercise->getId()]
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

    public function testDeleteExerciseFile()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $user = $this->presenter->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);
        $exercise = current($this->presenter->exercises->findAll());
        $filesCount = $exercise->getExerciseFiles()->count();
        $file = new ExerciseFile(
            "name1",
            new DateTime(),
            1,
            "hashName1",
            $user,
            $exercise
        );
        $this->exerciseFiles->persist($file);
        Assert::count($filesCount + 1, $exercise->getExerciseFiles());

        $request = new Nette\Application\Request(
            "V1:ExerciseFiles",
            'DELETE',
            [
                'action' => 'deleteExerciseFile',
                'id' => $exercise->getId(),
                'fileId' => $file->getId()
            ]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal("OK", $result['payload']);
        Assert::count($filesCount, $exercise->getExerciseFiles());
    }

    public function testDownloadExerciseFilesArchive()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $exercise = current(array_filter(
            $this->presenter->exercises->findAll(),
            function ($exercise) {
                return $exercise->getExerciseFiles()->count() > 0;
            }
        ));
        Assert::truthy($exercise);

        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        foreach ($exercise->getExerciseFiles() as $file) {
            $mockFile = Mockery::mock(LocalImmutableFile::class);
            $mockFileStorage->shouldReceive("getExerciseFileByHash")->with($file->getHashName())->andReturn($mockFile)->once();
        }
        $this->presenter->fileStorage = $mockFileStorage;

        $request = new Nette\Application\Request(
            "V1:ExerciseFiles",
            'GET',
            ['action' => 'downloadExerciseFilesArchive', 'id' => $exercise->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(App\Responses\ZipFilesResponse::class, $response);
        Assert::equal("exercise-files-" . $exercise->getId() . '.zip', $response->getName());
    }

    public function testGetAttachmentFiles()
    {
        $token = PresenterTestHelper::loginDefaultAdmin($this->container);

        // prepare files into exercise
        $user = $this->logins->getUser(PresenterTestHelper::ADMIN_LOGIN, PresenterTestHelper::ADMIN_PASSWORD, new Nette\Security\Passwords());
        $exercise = $this->presenter->exercises->searchByName("An exercise")[0];
        $expectedFile1 = new AttachmentFile("name1", new DateTime(), 1, $user, $exercise);
        $expectedFile2 = new AttachmentFile("name2", new DateTime(), 2, $user, $exercise);
        $this->attachmentFiles->persist($expectedFile1, false);
        $this->attachmentFiles->persist($expectedFile2, false);
        $this->attachmentFiles->flush();

        $request = new Nette\Application\Request(
            "V1:ExerciseFiles",
            'GET',
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
        $file = new AttachmentFile("name", new DateTime(), 1, $user, $exercise);
        $this->attachmentFiles->persist($file);
        Assert::count($filesCount + 1, $exercise->getAttachmentFiles());

        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("deleteAttachmentFile")->with($file)->once();
        $this->presenter->fileStorage = $mockFileStorage;

        $request = new Nette\Application\Request(
            "V1:ExerciseFiles",
            'DELETE',
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

        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        foreach ($exercise->getAttachmentFiles() as $file) {
            $mockFile = Mockery::mock(LocalImmutableFile::class);
            $mockFileStorage->shouldReceive("getAttachmentFile")->with($file)->andReturn($mockFile)->once();
        }
        $this->presenter->fileStorage = $mockFileStorage;

        $request = new Nette\Application\Request(
            "V1:ExerciseFiles",
            'GET',
            ['action' => 'downloadAttachmentFilesArchive', 'id' => $exercise->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(App\Responses\ZipFilesResponse::class, $response);
        Assert::equal("exercise-attachment-" . $exercise->getId() . '.zip', $response->getName());
    }

    public function testAttachmentFilesUpload()
    {
        $user = $this->presenter->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);

        $exercise = current(array_filter(
            $this->presenter->exercises->findAll(),
            function ($exercise) {
                return $exercise->getAttachmentFiles()->count() === 0;
            }
        ));
        Assert::truthy($exercise);

        // Mock file server setup
        $filename1 = "task1.txt";
        $filename2 = "task2.txt";
        $file1 = new UploadedFile($filename1, new \DateTime(), 0, $user);
        $file2 = new UploadedFile($filename2, new \DateTime(), 0, $user);
        $this->presenter->uploadedFiles->persist($file1);
        $this->presenter->uploadedFiles->persist($file2);
        $this->presenter->uploadedFiles->flush();
        $files = [$file1->getId(), $file2->getId()];

        $fileStorage = Mockery::mock(FileStorageManager::class);
        $fileStorage->shouldReceive("storeUploadedAttachmentFile")->once();
        $fileStorage->shouldReceive("storeUploadedAttachmentFile")->once();
        $this->presenter->fileStorage = $fileStorage;

        // Finally, the test itself
        PresenterTestHelper::loginDefaultAdmin($this->container);

        /** @var Nette\Application\Responses\JsonResponse $response */
        $response = $this->presenter->run(
            new Nette\Application\Request(
                "V1:ExerciseFiles",
                "POST",
                [
                    "action" => 'uploadAttachmentFiles',
                    'id' => $exercise->getId()
                ],
                ['files' => $files]
            )
        );

        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $payload = $response->getPayload()['payload'];
        Assert::count(2, $payload);

        foreach ($payload as $item) {
            Assert::type(App\Model\Entity\AttachmentFile::class, $item);
        }
    }

    private function getExerciseWithLinks(): Exercise
    {
        $exercises = array_filter(
            $this->exercises->findAll(),
            function (Exercise $e) {
                return !$e->getFileLinks()->isEmpty(); // select the exercise with file links
            }
        );
        Assert::count(1, $exercises);
        return array_pop($exercises);
    }

    public function testGetExerciseFileLinks()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $exercise = $this->getExerciseWithLinks();

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            "V1:ExerciseFiles",
            'GET',
            ['action' => 'getFileLinks', 'id' => $exercise->getId()]
        );

        $expectedLinks = [];
        foreach ($exercise->getFileLinks() as $link) {
            $expectedLinks[$link->getId()] = $link;
        }

        foreach ($payload as $link) {
            Assert::true(array_key_exists($link->getId(), $expectedLinks));
            $expectedLink = $expectedLinks[$link->getId()];
            Assert::equal($expectedLink->getId(), $link->getId());
            Assert::equal($expectedLink->getExerciseFile()->getId(), $link->getExerciseFile()->getId());
            Assert::equal($expectedLink->getExercise()?->getId(), $link->getExercise()?->getId());
            Assert::equal($expectedLink->getKey(), $link->getKey());
            Assert::equal($expectedLink->getSaveName(), $link->getSaveName());
            Assert::equal($expectedLink->getRequiredRole(), $link->getRequiredRole());
            Assert::null($link->getAssignment());
        }
    }

    public function testCreateExerciseFileLink()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $exercise = $this->getExerciseWithLinks();
        $exerciseFile = $exercise->getExerciseFiles()->filter(function (ExerciseFile $ef) {
            return $ef->getName() === 'input.txt';
        })->first();
        Assert::truthy($exerciseFile);

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            "V1:ExerciseFiles",
            'POST',
            [
                'action' => 'createFileLink',
                'id' => $exercise->getId(),
            ],
            [
                'exerciseFileId' => $exerciseFile->getId(),
                'key' => 'test-key',
                'requiredRole' => 'supervisor',
                'saveName' => 'rename.txt'
            ]
        );

        Assert::equal($exerciseFile->getId(), $payload->getExerciseFile()->getId());
        Assert::equal('test-key', $payload->getKey());
        Assert::equal('supervisor', $payload->getRequiredRole());
        Assert::equal('rename.txt', $payload->getSaveName());

        Assert::count(3, $this->presenter->fileLinks->findAll());
    }

    public function testCreateExerciseFileLinkWithoutOptionalFields()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $exercise = $this->getExerciseWithLinks();
        $exerciseFile = $exercise->getExerciseFiles()->filter(function (ExerciseFile $ef) {
            return $ef->getName() === 'input.txt';
        })->first();
        Assert::truthy($exerciseFile);

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            "V1:ExerciseFiles",
            'POST',
            [
                'action' => 'createFileLink',
                'id' => $exercise->getId(),
            ],
            [
                'exerciseFileId' => $exerciseFile->getId(),
                'key' => 'test-key',
            ]
        );

        Assert::equal($exerciseFile->getId(), $payload->getExerciseFile()->getId());
        Assert::equal('test-key', $payload->getKey());
        Assert::null($payload->getRequiredRole());
        Assert::null($payload->getSaveName());

        Assert::count(3, $this->presenter->fileLinks->findAll());
    }

    public function testUpdateExerciseFileLink()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $exercise = $this->getExerciseWithLinks();
        $link = $exercise->getFileLinks()->filter(function (ExerciseFileLink $l) {
            return $l->getKey() === 'LIB';
        })->first();
        Assert::truthy($link);
        $exerciseFile = $link->getExerciseFile();
        $saveName = $link->getSaveName();

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            "V1:ExerciseFiles",
            'POST',
            [
                'action' => 'updateFileLink',
                'id' => $exercise->getId(),
                'linkId' => $link->getId(),
            ],
            [
                'key' => 'SPAM',
                'requiredRole' => 'supervisor',
            ]
        );

        $this->presenter->fileLinks->refresh($link);

        Assert::equal($link->getId(), $payload->getId());
        Assert::count(2, $this->presenter->fileLinks->findAll());

        Assert::equal($exerciseFile->getId(), $payload->getExerciseFile()->getId());
        Assert::equal('SPAM', $payload->getKey());
        Assert::equal('supervisor', $payload->getRequiredRole());
        Assert::equal($saveName, $payload->getSaveName()); // wasn't changed

        Assert::equal($exerciseFile->getId(), $link->getExerciseFile()->getId());
        Assert::equal('SPAM', $link->getKey());
        Assert::equal('supervisor', $link->getRequiredRole());
        Assert::equal($saveName, $link->getSaveName()); // wasn't changed
    }

    public function testUpdateExerciseFileLinkSetNulls()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $exercise = $this->getExerciseWithLinks();
        $link = $exercise->getFileLinks()->filter(function (ExerciseFileLink $l) {
            return $l->getKey() === 'LIB';
        })->first();
        Assert::truthy($link);
        $exerciseFile = $link->getExerciseFile();
        $key = $link->getKey();

        $payload = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            "V1:ExerciseFiles",
            'POST',
            [
                'action' => 'updateFileLink',
                'id' => $exercise->getId(),
                'linkId' => $link->getId(),
            ],
            [
                'requiredRole' => null,
                'saveName' => null,
            ]
        );

        $this->presenter->fileLinks->refresh($link);

        Assert::equal($link->getId(), $payload->getId());
        Assert::count(2, $this->presenter->fileLinks->findAll());

        Assert::equal($exerciseFile->getId(), $payload->getExerciseFile()->getId());
        Assert::equal($key, $payload->getKey()); // wasn't changed
        Assert::null($payload->getRequiredRole());
        Assert::null($payload->getSaveName());

        Assert::equal($exerciseFile->getId(), $link->getExerciseFile()->getId());
        Assert::equal($key, $link->getKey()); // wasn't changed
        Assert::null($link->getRequiredRole());
        Assert::null($link->getSaveName());
    }

    public function testDeleteExerciseFileLink()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $exercise = $this->getExerciseWithLinks();
        $link = $exercise->getFileLinks()->first();
        Assert::truthy($link);

        PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            "V1:ExerciseFiles",
            'DELETE',
            ['action' => 'deleteFileLink', 'id' => $exercise->getId(), 'linkId' => $link->getId()]
        );

        Assert::count(1, $this->presenter->fileLinks->findAll());
        $remaining = current($this->presenter->fileLinks->findAll());
        Assert::notEqual($link->getId(), $remaining->getId());
    }
}

(new TestExerciseFilesPresenter())->run();
