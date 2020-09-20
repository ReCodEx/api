<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Model\Entity\AttachmentFile;
use App\V1Module\Presenters\UploadedFilesPresenter;
use App\Model\Entity\UploadedFile;
use App\Model\Entity\SolutionFile;
use App\Model\Repository\Logins;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\NotFoundException;
use App\Helpers\FileStorageManager;
use App\Helpers\FileStorage\LocalImmutableFile;
use App\Helpers\TmpFilesHelper;
use App\Helpers\FileStorage\LocalFileStorage;
use App\Helpers\FileStorage\LocalHashFileStorage;
use Doctrine\ORM\EntityManager;
use Nette\Utils\Json;
use Tester\Assert;
use org\bovigo\vfs\vfsStream;

/**
 * @httpCode any
 * @testCase
 */
class TestUploadedFilesPresenter extends Tester\TestCase
{
    private $userLogin = "submitUser1@example.com";
    private $userPassword = "password";

    private $otherUserLogin = "user1@example.com";
    private $otherUserPassword = "password1";

    private $supervisorLogin = "demoGroupSupervisor@example.com";
    private $supervisorPassword = "password";

    /** @var UploadedFilesPresenter */
    protected $presenter;

    /** @var Kdyby\Doctrine\EntityManager */
    protected $em;

    /** @var  Nette\DI\Container */
    protected $container;

    /** @var Logins */
    protected $logins;

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
        $this->logins = $container->getByType(Logins::class);

        // patch container, since we cannot create actual file storage manarer
        $fsName = current($this->container->findByType(FileStorageManager::class));
        $this->container->removeService($fsName);
        $this->container->addService($fsName, new FileStorageManager(
            Mockery::mock(LocalFileStorage::class),
            Mockery::mock(LocalHashFileStorage::class),
            Mockery::mock(TmpFilesHelper::class)
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
        $token = PresenterTestHelper::login($this->container, $this->otherUserLogin);
        $file = current($this->presenter->uploadedFiles->findBy(["isPublic" => false]));
        $request = new Nette\Application\Request(
            $this->presenterPath, 'GET',
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
        $token = PresenterTestHelper::loginDefaultAdmin($this->container);

        $file = current($this->presenter->uploadedFiles->findAll());

        $request = new Nette\Application\Request(
            $this->presenterPath, 'GET',
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
        $token = PresenterTestHelper::loginDefaultAdmin($this->container);

        $user = $this->logins->getUser($this->userLogin, $this->userPassword);
        $uploadedFile = new UploadedFile("nonexistfile", new DateTime(), 1, $user);
        $this->presenter->uploadedFiles->persist($uploadedFile);
        $this->presenter->uploadedFiles->flush();

        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("getUploadedFile")->withArgs([$uploadedFile])->andReturn(null)->once();
        $this->presenter->fileStorage = $mockFileStorage;

        $request = new Nette\Application\Request(
            $this->presenterPath, 'GET',
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
        $user = $this->logins->getUser($this->userLogin, $this->userPassword);
        $uploadedFile = new UploadedFile($filename, new DateTime(), 1, $user);
        $this->presenter->uploadedFiles->persist($uploadedFile);
        $this->presenter->uploadedFiles->flush();

        // mock file storage
        $mockFile = Mockery::mock(LocalImmutableFile::class);
        $mockFileStorage = Mockery::mock(FileStorageManager::class);
        $mockFileStorage->shouldReceive("getUploadedFile")->withArgs([$uploadedFile])->andReturn($mockFile)->once();
        $this->presenter->fileStorage = $mockFileStorage;
        
        $request = new Nette\Application\Request(
            $this->presenterPath, 'GET',
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
            $this->presenterPath, 'GET',
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
            $this->presenterPath, 'GET',
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
    
        $size = 1024*1024;
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
            $this->presenterPath, 'GET',
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
            $this->presenterPath, 'GET',
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
            $this->presenterPath, 'POST',
            ['action' => 'upload'], [], [$fileUpload]
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

        $em = $this->container->getByType(EntityManager::class);
        $file = current($em->getRepository(SolutionFile::class)->findAll());
        Assert::truthy($file);

        $mockFile = Mockery::mock(LocalImmutableFile::class);
        $mockStorage = Mockery::mock(FileStorageManager::class);
        $mockStorage->shouldReceive("getSolutionFile")->withArgs([$file->getSolution(), $file->getName()])->andReturn($mockFile)->once();
        $this->presenter->fileStorage = $mockStorage;

        $request = new Nette\Application\Request(
            $this->presenterPath, 'GET', [
            'action' => 'download',
            'id' => $file->id
        ]
        );
        $response = $this->presenter->run($request);
        Assert::type(App\Responses\StorageFileResponse::class, $response);
        Assert::equal($file->getName(), $response->getName());
    }

    public function testGroupMemberCanAccessAttachmentFiles()
    {
        $token = PresenterTestHelper::login($this->container, $this->userLogin);

        /** @var EntityManager $em */
        $em = $this->container->getByType(EntityManager::class);
        $file = current($em->getRepository(AttachmentFile::class)->findAll());

        $mockFile = Mockery::mock(LocalImmutableFile::class);
        $mockStorage = Mockery::mock(FileStorageManager::class);
        $mockStorage->shouldReceive("getAttachmentFile")->withArgs([$file])->andReturn($mockFile)->once();
        $this->presenter->fileStorage = $mockStorage;

        $request = new Nette\Application\Request(
            $this->presenterPath, 'GET', [
            'action' => 'download',
            'id' => $file->id
        ]
        );
        $response = $this->presenter->run($request);
        Assert::type(App\Responses\StorageFileResponse::class, $response);
        Assert::equal($file->getName(), $response->getName());
    }

    public function testOutsiderCannotAccessAttachmentFiles()
    {
        $token = PresenterTestHelper::login($this->container, $this->otherUserLogin);

        /** @var EntityManager $em */
        $em = $this->container->getByType(EntityManager::class);
        $file = current($em->getRepository(AttachmentFile::class)->findAll());

        $mockStorage = Mockery::mock(FileStorageManager::class);
        $mockStorage->shouldReceive("getAttachmentFile")->withArgs([$file])->andReturn(null)->once();
        $this->presenter->fileStorage = $mockStorage;

        $request = new Nette\Application\Request(
            $this->presenterPath, 'GET', [
            'action' => 'download',
            'id' => $file->id
        ]
        );

        Assert::exception(function () use ($request) {
                $this->presenter->run($request);
        }, NotFoundException::class, "Not Found - File not found in the storage");
    }

    public function testDownloadResultArchive()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $user = $this->presenter->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);
        $file = new \App\Model\Entity\SupplementaryExerciseFile("hefty name", new DateTime(), 1, "hash", $user);
        $this->presenter->supplementaryFiles->persist($file);
        $this->presenter->supplementaryFiles->flush();

        // mock everything you can
        $fileMock = Mockery::mock(LocalImmutableFile::class);
        $mockStorage = Mockery::mock(FileStorageManager::class);
        $mockStorage->shouldReceive("getSupplementaryFileByHash")->withArgs([ "hash" ])->andReturn($fileMock)->once();
        $this->presenter->fileStorage = $mockStorage;

        $request = new Nette\Application\Request(
            'V1:UploadedFiles',
            'GET',
            ['action' => 'downloadSupplementaryFile', 'id' => $file->id]
        );
        $response = $this->presenter->run($request);
        Assert::type(App\Responses\StorageFileResponse::class, $response);
        Assert::equal($file->getName(), $response->getName());
    }

}

$testCase = new TestUploadedFilesPresenter();
$testCase->run();
