<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Model\Entity\AttachmentFile;
use App\V1Module\Presenters\UploadedFilesPresenter;
use App\Model\Entity\Assignment;
use App\Model\Entity\Exercise;
use App\Model\Entity\ExerciseFileLink;
use App\Model\Entity\Group;
use App\Model\Entity\UploadedFile;
use App\Model\Entity\UploadedPartialFile;
use App\Model\Repository\Assignments;
use App\Model\Repository\AssignmentSolutions;
use App\Model\Repository\Exercises;
use App\Model\Repository\Groups;
use App\Model\Repository\Logins;
use App\Model\Repository\Users;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\NotFoundException;
use App\Exceptions\CannotReceiveUploadedFileException;
use App\Helpers\FileStorageManager;
use App\Helpers\FileStorage\LocalImmutableFile;
use App\Helpers\TmpFilesHelper;
use App\Helpers\FileStorage\LocalFileStorage;
use App\Helpers\FileStorage\LocalHashFileStorage;
use App\Security\Roles;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Utils\Json;
use Tester\Assert;

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

/**
 * @httpCode any
 * @testCase
 */
class TestUploadedFilesPresenter extends Tester\TestCase
{
    private $userLogin = "submitUser1@example.com";
    private $userPassword = "password";

    private $otherUserLogin = "user1@example.com";
    private $supervisorLogin = "demoGroupSupervisor@example.com";

    /** @var UploadedFilesPresenter */
    protected $presenter;

    /** @var EntityManagerInterface */
    protected $em;

    /** @var  Nette\DI\Container */
    protected $container;

    /** @var Assignments */
    protected $assignments;

    /** @var Exercises */
    protected $exercises;

    /** @var Groups */
    protected $groups;

    /** @var Logins */
    protected $logins;

    /** @var AssignmentSolutions */
    protected $assignmentSolutions;

    /** @var Nette\Security\User */
    private $user;

    /** @var string */
    private $presenterPath = "V1:UploadedFiles";

    public function __construct()
    {
        global $container;
        $this->container = $container;
        $this->em = PresenterTestHelper::getEntityManager($container);
        $this->user = $container->getByType(\Nette\Security\User::class);
        $this->assignments = $container->getByType(Assignments::class);
        $this->exercises = $container->getByType(Exercises::class);
        $this->groups = $container->getByType(Groups::class);
        $this->logins = $container->getByType(Logins::class);
        $this->assignmentSolutions = $container->getByType(AssignmentSolutions::class);

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
        $this->presenter = PresenterTestHelper::createPresenter($this->container, UploadedFilesPresenter::class);
    }

    protected function tearDown()
    {
        Mockery::close();

        if ($this->user->isLoggedIn()) {
            $this->user->logout(true);
        }
    }

    public function testUserCannotAccessDetail()
    {
        PresenterTestHelper::login($this->container, $this->otherUserLogin);
        $file = current($this->presenter->uploadedFiles->findAll());
        $request = new Nette\Application\Request(
            $this->presenterPath,
            'GET',
            ['action' => 'detail', 'id' => $file->getId()]
        );
        Assert::exception(
            function () use ($request) {
                $this->presenter->run($request);
            },
            ForbiddenRequestException::class
        );
    }

    public function testDetail()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $file = current($this->presenter->uploadedFiles->findAll());

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'GET',
            ['action' => 'detail', 'id' => $file->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::type(UploadedFile::class, $result['payload']);
        Assert::equal($file->getId(), $result['payload']->getId());
    }

    public function testNotFoundDownload()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);

        $user = $this->logins->getUser($this->userLogin, $this->userPassword, new Nette\Security\Passwords());
        $uploadedFile = new UploadedFile("nonexistfile", new DateTime(), 1, $user);
        $this->presenter->uploadedFiles->persist($uploadedFile);
        $this->presenter->uploadedFiles->flush();

        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("getUploadedFile")->withArgs([$uploadedFile])->andReturn(null)->once();
        $this->presenter->fileStorage = $mockFileStorage;

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'GET',
            ['action' => 'download', 'id' => $uploadedFile->getId()]
        );
        Assert::exception(
            function () use ($request) {
                $this->presenter->run($request);
            },
            NotFoundException::class
        );
    }

    public function testDownload()
    {
        $token = PresenterTestHelper::login($this->container, $this->userLogin);

        $filename = "file.ext";

        // create new file upload
        $user = $this->logins->getUser($this->userLogin, $this->userPassword, new Nette\Security\Passwords());
        $uploadedFile = new UploadedFile($filename, new DateTime(), 1, $user);
        $this->presenter->uploadedFiles->persist($uploadedFile);
        $this->presenter->uploadedFiles->flush();

        // mock file storage
        $mockFile = Mockery::mock(LocalImmutableFile::class);
        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("getUploadedFile")->withArgs([$uploadedFile])->andReturn($mockFile)->once();
        $this->presenter->fileStorage = $mockFileStorage;

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'GET',
            ['action' => 'download', 'id' => $uploadedFile->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(App\Responses\StorageFileResponse::class, $response);
        Assert::equal($filename, $response->getName());
    }

    public function testContent()
    {
        $token = PresenterTestHelper::login($this->container, $this->userLogin);

        $contents = "ContentOfContentedFile";

        // create new file upload
        $user = $this->presenter->accessManager->getUser($this->presenter->accessManager->decodeToken($token));
        $uploadedFile = new UploadedFile("file.ext", new DateTime(), strlen($contents), $user);
        $this->presenter->uploadedFiles->persist($uploadedFile);
        $this->presenter->uploadedFiles->flush();

        // mock file storage
        $mockFile = Mockery::mock(LocalImmutableFile::class);
        $mockFile->shouldReceive("getContents")->andReturn($contents)->once();

        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("getUploadedFile")->withArgs([$uploadedFile])->andReturn($mockFile)->once();
        $this->presenter->fileStorage = $mockFileStorage;

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'GET',
            ['action' => 'content', 'id' => $uploadedFile->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal($contents, $result['payload']['content']);
        Assert::false($result['payload']['malformedCharacters']);
        Assert::false($result['payload']['tooLarge']);
    }

    public function testContentWeirdChars()
    {
        $token = PresenterTestHelper::login($this->container, $this->userLogin);

        $contents = iconv("UTF-8", "Windows-1250", "Žluťoučké kobylky");

        // create new file upload
        $user = $this->presenter->accessManager->getUser($this->presenter->accessManager->decodeToken($token));
        $uploadedFile = new UploadedFile("file.ext", new DateTime(), strlen($contents), $user);
        $this->presenter->uploadedFiles->persist($uploadedFile);
        $this->presenter->uploadedFiles->flush();

        // mock file storage
        $mockFile = Mockery::mock(LocalImmutableFile::class);
        $mockFile->shouldReceive("getContents")->andReturn($contents)->once();

        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("getUploadedFile")->withArgs([$uploadedFile])->andReturn($mockFile)->once();
        $this->presenter->fileStorage = $mockFileStorage;

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'GET',
            ['action' => 'content', 'id' => $uploadedFile->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::true($result['payload']['malformedCharacters']);
        Assert::false($result['payload']['tooLarge']);
        Assert::noError(
            function () use ($result) {
                Json::encode($result);
            }
        );
    }

    public function testContentWeirdTooManyChars()
    {
        $token = PresenterTestHelper::login($this->container, $this->userLogin);

        $size = 1024 * 1024;
        $contents = random_bytes($size);

        // create new file upload
        $user = $this->presenter->accessManager->getUser($this->presenter->accessManager->decodeToken($token));
        $uploadedFile = new UploadedFile("weird.bin", new DateTime(), $size, $user);
        $this->presenter->uploadedFiles->persist($uploadedFile);
        $this->presenter->uploadedFiles->flush();

        // mock file storage
        $mockFile = Mockery::mock(LocalImmutableFile::class);
        $mockFile->shouldReceive("getContents")->andReturn($contents)->once();

        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("getUploadedFile")->withArgs([$uploadedFile])->andReturn($mockFile)->once();
        $this->presenter->fileStorage = $mockFileStorage;

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'GET',
            ['action' => 'content', 'id' => $uploadedFile->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::true($result['payload']['malformedCharacters']);
        Assert::true($result['payload']['tooLarge']);
        Assert::noError(
            function () use ($result) {
                Json::encode($result);
            }
        );
    }

    public function testContentBom()
    {
        $token = PresenterTestHelper::login($this->container, $this->userLogin);

        // create virtual filesystem setup
        $filename = "file.ext";
        $contents = chr(0xef) . chr(0xbb) . chr(0xbf) . "Hello";

        // create new file upload
        $user = $this->presenter->accessManager->getUser($this->presenter->accessManager->decodeToken($token));
        $uploadedFile = new UploadedFile($filename, new DateTime(), 1, $user);
        $this->presenter->uploadedFiles->persist($uploadedFile);
        $this->presenter->uploadedFiles->flush();

        // mock file storage
        $mockFile = Mockery::mock(LocalImmutableFile::class);
        $mockFile->shouldReceive("getContents")->andReturn($contents)->once();

        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("getUploadedFile")->withArgs([$uploadedFile])->andReturn($mockFile)->once();
        $this->presenter->fileStorage = $mockFileStorage;

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'GET',
            ['action' => 'content', 'id' => $uploadedFile->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal("Hello", $result['payload']['content']);
        Assert::false($result['payload']['malformedCharacters']);
        Assert::false($result['payload']['tooLarge']);
        Assert::noError(
            function () use ($result) {
                Json::encode($result);
            }
        );
    }

    public function testNoFilesUpload()
    {
        $token = PresenterTestHelper::login($this->container, $this->userLogin);

        $request = new Nette\Application\Request($this->presenterPath, 'POST', ['action' => 'upload']);
        Assert::exception(
            function () use ($request) {
                $this->presenter->run($request);
            },
            App\Exceptions\BadRequestException::class
        );
    }

    public function testUpload()
    {
        $token = PresenterTestHelper::login($this->container, $this->userLogin);

        $user = current($this->presenter->users->findAll());
        $file = ['name' => "filename", 'type' => 'type', 'size' => 1, 'tmp_name' => 'tmpname', 'error' => 0];
        $fileUpload = new Nette\Http\FileUpload($file);

        // mock file storage
        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("storeUploadedFile")->withArgs([Mockery::any(), $fileUpload])->once();
        $this->presenter->fileStorage = $mockFileStorage;

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'POST',
            ['action' => 'upload'],
            [],
            [$fileUpload]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::type(UploadedFile::class, $result['payload']);
    }

    public function testGroupSupervisorCanDownloadSubmissions()
    {
        $token = PresenterTestHelper::login($this->container, $this->supervisorLogin);

        $solution = current(array_filter($this->assignmentSolutions->findAll(), function ($solution) {
            return $solution->getSolution()->getFiles()->count() > 0;
        }));
        Assert::truthy($solution);

        $file = current($solution->getSolution()->getFiles()->toArray());
        Assert::truthy($file);

        $mockFile = Mockery::mock(LocalImmutableFile::class);
        $mockStorage = Mockery::mock(FileStorageManager::class);
        $mockStorage->shouldReceive("getSolutionFile")->withArgs([$solution->getSolution(), $file->getName()])->andReturn($mockFile)->once();
        $this->presenter->fileStorage = $mockStorage;

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'GET',
            [
                'action' => 'download',
                'id' => $file->getId()
            ]
        );
        $response = $this->presenter->run($request);
        Assert::type(App\Responses\StorageFileResponse::class, $response);
        Assert::equal($file->getName(), $response->getName());
    }

    public function testGroupMemberCanAccessAttachmentFiles()
    {
        $token = PresenterTestHelper::login($this->container, $this->userLogin);

        /** @var EntityManagerInterface $em */
        $em = $this->container->getByType(EntityManagerInterface::class);
        $file = current($em->getRepository(AttachmentFile::class)->findAll());

        $mockFile = Mockery::mock(LocalImmutableFile::class);
        $mockStorage = Mockery::mock(FileStorageManager::class);
        $mockStorage->shouldReceive("getAttachmentFile")->withArgs([$file])->andReturn($mockFile)->once();
        $this->presenter->fileStorage = $mockStorage;

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'GET',
            [
                'action' => 'download',
                'id' => $file->getId()
            ]
        );
        $response = $this->presenter->run($request);
        Assert::type(App\Responses\StorageFileResponse::class, $response);
        Assert::equal($file->getName(), $response->getName());
    }

    public function testDownloadExerciseFile()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $user = $this->presenter->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);
        $file = new \App\Model\Entity\ExerciseFile("hefty name", new DateTime(), 1, "hash", $user);
        $this->presenter->exerciseFiles->persist($file);
        $this->presenter->exerciseFiles->flush();

        // mock everything you can
        $fileMock = Mockery::mock(LocalImmutableFile::class);
        $mockStorage = Mockery::mock(FileStorageManager::class);
        $mockStorage->shouldReceive("getExerciseFileByHash")->withArgs(["hash"])->andReturn($fileMock)->once();
        $this->presenter->fileStorage = $mockStorage;

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'GET',
            ['action' => 'downloadExerciseFile', 'id' => $file->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(App\Responses\StorageFileResponse::class, $response);
        Assert::equal($file->getName(), $response->getName());
    }

    public function testUploadPerPartes()
    {
        $token = PresenterTestHelper::login($this->container, $this->userLogin);

        // mock file storage
        $partialId = null;
        $argClosure = function ($arg) use (&$partialId) {
            return $arg instanceof UploadedPartialFile && $arg->getId() === $partialId;
        };
        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("storeUploadedPartialFileChunk")->withArgs($argClosure)->andReturn(8, 7);
        $mockFileStorage->shouldReceive("assembleUploadedPartialFile")->withArgs([Mockery::any(), Mockery::any()])->once();
        $this->presenter->fileStorage = $mockFileStorage;

        $partialFile = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            $this->presenterPath,
            'POST',
            ['action' => 'startPartial'],
            ['name' => 'foo.txt', 'size' => 15]
        );

        Assert::type(UploadedPartialFile::class, $partialFile);
        Assert::equal('foo.txt', $partialFile->getName());
        Assert::equal(15, $partialFile->getTotalSize());
        $partialId = $partialFile->getId(); // so the mock tests work properly

        // first chunk
        $partialFile1 = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            $this->presenterPath,
            'PUT',
            ['action' => 'appendPartial', 'id' => $partialFile->getId(), 'offset' => 0]
        );

        Assert::type(UploadedPartialFile::class, $partialFile1);
        Assert::equal($partialFile->getId(), $partialFile1->getId());
        Assert::equal(8, $partialFile1->getUploadedSize());
        Assert::equal(1, $partialFile1->getChunks());
        Assert::false($partialFile1->isUploadComplete());

        // second and last chunk
        $partialFile2 = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            $this->presenterPath,
            'PUT',
            ['action' => 'appendPartial', 'id' => $partialFile->getId(), 'offset' => 8]
        );

        Assert::type(UploadedPartialFile::class, $partialFile2);
        Assert::equal($partialFile->getId(), $partialFile2->getId());
        Assert::equal(15, $partialFile2->getUploadedSize());
        Assert::equal(2, $partialFile2->getChunks());
        Assert::true($partialFile2->isUploadComplete());

        // complete the upload
        $uploadedFile = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            $this->presenterPath,
            'POST',
            ['action' => 'completePartial', 'id' => $partialFile->getId()]
        );

        Assert::type(UploadedFile::class, $uploadedFile);
        Assert::equal('foo.txt', $uploadedFile->getName());
        Assert::equal(15, $uploadedFile->getFileSize());

        Assert::count(0, $this->presenter->uploadedPartialFiles->findAll());
    }

    public function testUploadPerPartesCanceled()
    {
        $token = PresenterTestHelper::login($this->container, $this->userLogin);

        // mock file storage
        $partialId = null;
        $argClosure = function ($arg) use (&$partialId) {
            return $arg instanceof UploadedPartialFile && $arg->getId() === $partialId;
        };
        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("storeUploadedPartialFileChunk")->withArgs($argClosure)->andReturn(8, 7);
        $mockFileStorage->shouldReceive("deleteUploadedPartialFileChunks")->withArgs($argClosure)->andReturn(1)->once();
        $this->presenter->fileStorage = $mockFileStorage;

        $partialFile = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            $this->presenterPath,
            'POST',
            ['action' => 'startPartial'],
            ['name' => 'foo.txt', 'size' => 15]
        );

        Assert::type(UploadedPartialFile::class, $partialFile);
        Assert::equal('foo.txt', $partialFile->getName());
        Assert::equal(15, $partialFile->getTotalSize());
        $partialId = $partialFile->getId(); // so the mock tests work properly

        // first chunk
        $partialFile1 = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            $this->presenterPath,
            'PUT',
            ['action' => 'appendPartial', 'id' => $partialFile->getId(), 'offset' => 0]
        );

        Assert::type(UploadedPartialFile::class, $partialFile1);
        Assert::equal($partialFile->getId(), $partialFile1->getId());
        Assert::equal(8, $partialFile1->getUploadedSize());
        Assert::equal(1, $partialFile1->getChunks());
        Assert::false($partialFile1->isUploadComplete());

        // cancel the upload
        PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            $this->presenterPath,
            'DELETE',
            ['action' => 'cancelPartial', 'id' => $partialFile->getId()]
        );

        Assert::count(0, $this->presenter->uploadedPartialFiles->findAll());
    }

    public function testUploadPerPartesFailIncomplete()
    {
        $token = PresenterTestHelper::login($this->container, $this->userLogin);

        // mock file storage
        $partialId = null;
        $argClosure = function ($arg) use (&$partialId) {
            return $arg instanceof UploadedPartialFile && $arg->getId() === $partialId;
        };
        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("storeUploadedPartialFileChunk")->withArgs($argClosure)->andReturn(8, 7);
        $this->presenter->fileStorage = $mockFileStorage;

        $partialFile = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            $this->presenterPath,
            'POST',
            ['action' => 'startPartial'],
            ['name' => 'foo.txt', 'size' => 15]
        );

        Assert::type(UploadedPartialFile::class, $partialFile);
        Assert::equal('foo.txt', $partialFile->getName());
        Assert::equal(15, $partialFile->getTotalSize());
        $partialId = $partialFile->getId(); // so the mock tests work properly

        // first chunk
        $partialFile1 = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            $this->presenterPath,
            'PUT',
            ['action' => 'appendPartial', 'id' => $partialFile->getId(), 'offset' => 0]
        );

        Assert::type(UploadedPartialFile::class, $partialFile1);
        Assert::equal($partialFile->getId(), $partialFile1->getId());
        Assert::equal(8, $partialFile1->getUploadedSize());
        Assert::equal(1, $partialFile1->getChunks());
        Assert::false($partialFile1->isUploadComplete());

        // complete the upload
        Assert::exception(
            function () use ($partialFile) {
                $uploadedFile = PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    $this->presenterPath,
                    'POST',
                    ['action' => 'completePartial', 'id' => $partialFile->getId()]
                );
            },
            CannotReceiveUploadedFileException::class,
            "Unable to finalize incomplete per-partes upload."
        );

        Assert::count(1, $this->presenter->uploadedPartialFiles->findAll());
    }

    public function testDigest()
    {
        $token = PresenterTestHelper::login($this->container, $this->userLogin);
        $contents = "ContentOfContentedFile";

        // create new file upload
        $user = $this->presenter->accessManager->getUser($this->presenter->accessManager->decodeToken($token));
        $uploadedFile = new UploadedFile("file.ext", new DateTime(), strlen($contents), $user);
        $this->presenter->uploadedFiles->persist($uploadedFile);
        $this->presenter->uploadedFiles->flush();

        // mock file storage
        $mockFile = Mockery::mock(LocalImmutableFile::class);
        $mockFile->shouldReceive("getDigest")->andReturn(sha1($contents))->once();

        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("getUploadedFile")->withArgs([$uploadedFile])->andReturn($mockFile)->once();
        $this->presenter->fileStorage = $mockFileStorage;

        $response = PresenterTestHelper::performPresenterRequest(
            $this->presenter,
            $this->presenterPath,
            'PUT',
            ['action' => 'digest', 'id' => $uploadedFile->getId()]
        );

        Assert::equal('sha1', $response['algorithm']);
        Assert::equal(sha1($contents), $response['digest']);
    }

    public function testDownloadDetectedPlagiarismSource()
    {
        PresenterTestHelper::login($this->container, 'demoGroupSupervisor@example.com');

        $similarFileEnt = current($this->presenter->detectedSimilarFiles->findAll());
        $file = $similarFileEnt->getSolutionFile();
        $similarity = $similarFileEnt->getDetectedSimilarity();

        // direct access by demoGroupSupervisor is forbidden (the file is in the child group in demoAssignment2)
        Assert::exception(
            function () use ($file) {
                PresenterTestHelper::performPresenterRequest(
                    $this->presenter,
                    $this->presenterPath,
                    'GET',
                    ['action' => 'download', 'id' => $file->getId()]
                );
            },
            ForbiddenRequestException::class
        );

        // but the file is in detected plagiarism candidates for a solution5 (accessible by demoGroupSupervisor),
        // so if we add a proper hint to the request, the download must succeed
        $mockFile = Mockery::mock(LocalImmutableFile::class);
        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("getSolutionFile")->withArgs([$file->getSolution(), $file->getName()])->andReturn($mockFile)->once();
        $this->presenter->fileStorage = $mockFileStorage;
        $request = new Nette\Application\Request(
            $this->presenterPath,
            'GET',
            [
                'action' => 'download',
                'id' => $file->getId(),
                'similarSolutionId' => $similarity->getTestedSolution()->getId()
            ]
        );
        $response = $this->presenter->run($request);
        Assert::type(App\Responses\StorageFileResponse::class, $response);
        Assert::equal($file->getName(), $response->getName());
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

    private function makeAssignmentWithLinks()
    {
        $exercise = $this->getExerciseWithLinks();
        $supervisor = $this->container->getByType(Users::class)->getByEmail(PresenterTestHelper::GROUP_SUPERVISOR_LOGIN);

        $group = current(array_filter(
            $this->groups->findAll(),
            function (Group $g) use ($supervisor) {
                return $g->isSupervisorOf($supervisor) && !$g->isOrganizational();
            }
        ));
        Assert::truthy($group);

        $assignment = Assignment::assignToGroup($exercise, $group, true, null);
        $this->assignments->persist($assignment);
        return $assignment;
    }

    public function testDownloadExerciseFileByLink()
    {
        // no login (should be visible for everyone)

        $exercise = $this->getExerciseWithLinks();
        $fileLink = $exercise->getFileLinks()->filter(
            function (ExerciseFileLink $link) {
                return $link->getRequiredRole() === null;
            }
        )->first();
        Assert::truthy($fileLink);
        $file = $fileLink->getExerciseFile();

        // mock everything you can
        $fileMock = Mockery::mock(LocalImmutableFile::class);
        $mockStorage = Mockery::mock(FileStorageManager::class);
        $mockStorage->shouldReceive("getExerciseFileByHash")
            ->withArgs([$file->getHashName()])
            ->andReturn($fileMock)->once();
        $this->presenter->fileStorage = $mockStorage;

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'GET',
            ['action' => 'downloadExerciseFileByLink', 'id' => $fileLink->getId()]
        );

        $response = $this->presenter->run($request);
        Assert::type(App\Responses\StorageFileResponse::class, $response);
        Assert::equal($file->getName(), $response->getName());
    }

    public function testDownloadAssignmentFileByLink()
    {
        // no login (should be visible for everyone)

        $assignment = $this->makeAssignmentWithLinks();

        Assert::count(2, $assignment->getFileLinks());
        $fileLink = $assignment->getFileLinks()->filter(
            function (ExerciseFileLink $link) {
                return $link->getRequiredRole() === null;
            }
        )->first();
        Assert::truthy($fileLink);
        $file = $fileLink->getExerciseFile();

        // mock everything you can
        $fileMock = Mockery::mock(LocalImmutableFile::class);
        $mockStorage = Mockery::mock(FileStorageManager::class);
        $mockStorage->shouldReceive("getExerciseFileByHash")
            ->withArgs([$file->getHashName()])
            ->andReturn($fileMock)->once();
        $this->presenter->fileStorage = $mockStorage;

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'GET',
            ['action' => 'downloadExerciseFileByLink', 'id' => $fileLink->getId()]
        );

        $response = $this->presenter->run($request);
        Assert::type(App\Responses\StorageFileResponse::class, $response);
        Assert::equal($file->getName(), $response->getName());
    }

    public function testDownloadExerciseFileByLinkWithRenaming()
    {
        PresenterTestHelper::login($this->container, PresenterTestHelper::GROUP_SUPERVISOR_LOGIN);

        $exercise = $this->getExerciseWithLinks();
        $fileLink = $exercise->getFileLinks()->filter(
            function (ExerciseFileLink $link) {
                return $link->getSaveName() !== null;
            }
        )->first();
        Assert::truthy($fileLink);
        $file = $fileLink->getExerciseFile();

        // mock everything you can
        $fileMock = Mockery::mock(LocalImmutableFile::class);
        $mockStorage = Mockery::mock(FileStorageManager::class);
        $mockStorage->shouldReceive("getExerciseFileByHash")
            ->withArgs([$file->getHashName()])
            ->andReturn($fileMock)->once();
        $this->presenter->fileStorage = $mockStorage;

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'GET',
            ['action' => 'downloadExerciseFileByLink', 'id' => $fileLink->getId()]
        );

        $response = $this->presenter->run($request);
        Assert::type(App\Responses\StorageFileResponse::class, $response);
        Assert::equal($fileLink->getSaveName(), $response->getName());
    }

    public function testDownloadAssignmentFileByLinkWithRenaming()
    {
        PresenterTestHelper::login($this->container, PresenterTestHelper::GROUP_SUPERVISOR_LOGIN);

        $assignment = $this->makeAssignmentWithLinks();
        $fileLink = $assignment->getFileLinks()->filter(
            function (ExerciseFileLink $link) {
                return $link->getSaveName() !== null;
            }
        )->first();
        Assert::truthy($fileLink);
        $file = $fileLink->getExerciseFile();

        // mock everything you can
        $fileMock = Mockery::mock(LocalImmutableFile::class);
        $mockStorage = Mockery::mock(FileStorageManager::class);
        $mockStorage->shouldReceive("getExerciseFileByHash")
            ->withArgs([$file->getHashName()])
            ->andReturn($fileMock)->once();
        $this->presenter->fileStorage = $mockStorage;

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'GET',
            ['action' => 'downloadExerciseFileByLink', 'id' => $fileLink->getId()]
        );

        $response = $this->presenter->run($request);
        Assert::type(App\Responses\StorageFileResponse::class, $response);
        Assert::equal($fileLink->getSaveName(), $response->getName());
    }

    public function testDownloadExerciseFileByLinkDeniedByRole()
    {
        PresenterTestHelper::login($this->container, PresenterTestHelper::GROUP_SUPERVISOR_LOGIN);

        $exercise = $this->getExerciseWithLinks();
        $fileLink = $exercise->getFileLinks()->filter(
            function (ExerciseFileLink $link) {
                return $link->getRequiredRole() !== null;
            }
        )->first();
        Assert::truthy($fileLink);
        $fileLink->setRequiredRole(Roles::EMPOWERED_SUPERVISOR_ROLE); // set role that current user does not have
        $this->presenter->fileLinks->persist($fileLink);

        Assert::exception(
            function () use ($fileLink) {
                $request = new Nette\Application\Request(
                    $this->presenterPath,
                    'GET',
                    ['action' => 'downloadExerciseFileByLink', 'id' => $fileLink->getId()]
                );
                $this->presenter->run($request);
            },
            ForbiddenRequestException::class
        );
    }

    public function testDownloadAssignmentFileByLinkDeniedByRole()
    {
        PresenterTestHelper::login($this->container, PresenterTestHelper::STUDENT_GROUP_MEMBER_LOGIN);

        $assignment = $this->makeAssignmentWithLinks();
        $fileLink = $assignment->getFileLinks()->filter(
            function (ExerciseFileLink $link) {
                return $link->getRequiredRole() !== null;
            }
        )->first();
        Assert::truthy($fileLink);
        $fileLink->setRequiredRole(Roles::SUPERVISOR_ROLE); // set role that current user does not have
        $this->presenter->fileLinks->persist($fileLink);

        Assert::exception(
            function () use ($fileLink) {
                $request = new Nette\Application\Request(
                    $this->presenterPath,
                    'GET',
                    ['action' => 'downloadExerciseFileByLink', 'id' => $fileLink->getId()]
                );
                $this->presenter->run($request);
            },
            ForbiddenRequestException::class
        );
    }

    public function testDownloadExerciseFileByLinkDeniedByExerciseAcl()
    {
        PresenterTestHelper::login($this->container, PresenterTestHelper::ANOTHER_SUPERVISOR_LOGIN);

        $exercise = $this->getExerciseWithLinks();
        $fileLink = $exercise->getFileLinks()->filter(
            function (ExerciseFileLink $link) {
                return $link->getRequiredRole() !== null;
            }
        )->first();
        Assert::truthy($fileLink);

        Assert::exception(
            function () use ($fileLink) {
                $request = new Nette\Application\Request(
                    $this->presenterPath,
                    'GET',
                    ['action' => 'downloadExerciseFileByLink', 'id' => $fileLink->getId()]
                );
                $this->presenter->run($request);
            },
            ForbiddenRequestException::class
        );
    }

    public function testDownloadAssignmentFileByLinkDeniedByAssignmentAcl()
    {
        PresenterTestHelper::login($this->container, PresenterTestHelper::NONMEMBER_STUDENT_LOGIN);

        $assignment = $this->makeAssignmentWithLinks();
        $fileLink = $assignment->getFileLinks()->filter(
            function (ExerciseFileLink $link) {
                return $link->getRequiredRole() !== null;
            }
        )->first();
        Assert::truthy($fileLink);

        Assert::exception(
            function () use ($fileLink) {
                $request = new Nette\Application\Request(
                    $this->presenterPath,
                    'GET',
                    ['action' => 'downloadExerciseFileByLink', 'id' => $fileLink->getId()]
                );
                $this->presenter->run($request);
            },
            ForbiddenRequestException::class
        );
    }

    public function testDownloadExerciseFileByKey()
    {
        PresenterTestHelper::login($this->container, PresenterTestHelper::GROUP_SUPERVISOR_LOGIN);

        $exercise = $this->getExerciseWithLinks();
        $fileLink = $exercise->getFileLinks()->filter(
            function (ExerciseFileLink $link) {
                return $link->getSaveName() !== null;
            }
        )->first();
        Assert::truthy($fileLink);
        $file = $fileLink->getExerciseFile();

        // mock everything you can
        $fileMock = Mockery::mock(LocalImmutableFile::class);
        $mockStorage = Mockery::mock(FileStorageManager::class);
        $mockStorage->shouldReceive("getExerciseFileByHash")
            ->withArgs([$file->getHashName()])
            ->andReturn($fileMock)->once();
        $this->presenter->fileStorage = $mockStorage;

        $request = new Nette\Application\Request(
            $this->presenterPath,
            'GET',
            [
                'action' => 'downloadExerciseFileLinkByKey',
                'id' => $exercise->getId(),
                'linkKey' => $fileLink->getKey()
            ]
        );

        $response = $this->presenter->run($request);
        Assert::type(App\Responses\StorageFileResponse::class, $response);
        Assert::equal($fileLink->getSaveName(), $response->getName());
    }
}

$testCase = new TestUploadedFilesPresenter();
$testCase->run();
