<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Model\Entity\AttachmentFile;
use App\V1Module\Presenters\UploadedFilesPresenter;
use App\Model\Entity\UploadedFile;
use App\Model\Repository\Logins;
use App\Exceptions\ForbiddenRequestException;
use App\Helpers\UploadedFileStorage;
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

        $file = current($this->presenter->uploadedFiles->findAll());

        $request = new Nette\Application\Request(
            $this->presenterPath, 'GET',
            ['action' => 'download', 'id' => $file->getId()]
        );
        Assert::exception(
            function () use ($request) {
                $this->presenter->run($request);
            },
            \Nette\Application\BadRequestException::class
        );
    }

    public function testDownload()
    {
        $token = PresenterTestHelper::login($this->container, $this->userLogin);

        // create virtual filesystem setup
        $filename = "file.ext";
        $content = "ContentOfContentedFile";
        $vfs = vfsStream::setup("root", null, [$filename => $content]);
        $vfsFile = $vfs->getChild($filename);

        // create new file upload
        $user = $this->logins->getUser($this->userLogin, $this->userPassword);
        $uploadedFile = new UploadedFile($filename, new DateTime(), 1, $user, $vfsFile->url());
        $this->presenter->uploadedFiles->persist($uploadedFile);
        $this->presenter->uploadedFiles->flush();

        $request = new Nette\Application\Request(
            $this->presenterPath, 'GET',
            ['action' => 'download', 'id' => $uploadedFile->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\FileResponse::class, $response);

        Assert::equal($vfsFile->url(), $response->getFile());
        Assert::equal($filename, $response->getName());
    }

    public function testContent()
    {
        $token = PresenterTestHelper::login($this->container, $this->userLogin);

        // create virtual filesystem setup
        $filename = "file.ext";
        $content = "ContentOfContentedFile";
        $vfs = vfsStream::setup("root", null, [$filename => $content]);
        $vfsFile = $vfs->getChild($filename);

        // create new file upload
        $user = $this->presenter->accessManager->getUser($this->presenter->accessManager->decodeToken($token));
        $uploadedFile = new UploadedFile($filename, new DateTime(), 1, $user, $vfsFile->url());
        $this->presenter->uploadedFiles->persist($uploadedFile);
        $this->presenter->uploadedFiles->flush();

        $request = new Nette\Application\Request(
            $this->presenterPath, 'GET',
            ['action' => 'content', 'id' => $uploadedFile->getId()]
        );
        $response = $this->presenter->run($request);
        Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

        $result = $response->getPayload();
        Assert::equal(200, $result['code']);
        Assert::equal($content, $result['payload']['content']);
        Assert::false($result['payload']['malformedCharacters']);
        Assert::false($result['payload']['tooLarge']);
    }

    public function testContentWeirdChars()
    {
        $token = PresenterTestHelper::login($this->container, $this->userLogin);

        // create virtual filesystem setup
        $filename = "file.ext";
        $content = iconv("UTF-8", "Windows-1250", "Žluťoučké kobylky");
        $vfs = vfsStream::setup("root", null, [$filename => $content]);
        $vfsFile = $vfs->getChild($filename);

        // create new file upload
        $user = $this->presenter->accessManager->getUser($this->presenter->accessManager->decodeToken($token));
        $uploadedFile = new UploadedFile($filename, new DateTime(), 1, $user, $vfsFile->url());
        $this->presenter->uploadedFiles->persist($uploadedFile);
        $this->presenter->uploadedFiles->flush();

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

    public function testContentEvenWeirderChars()
    {
        $token = PresenterTestHelper::login($this->container, $this->userLogin);

        // create new file upload
        $user = $this->presenter->accessManager->getUser($this->presenter->accessManager->decodeToken($token));
        $path = __DIR__ . "/uploads/weird.zip";
        $uploadedFile = new UploadedFile("weird.zip", new DateTime(), filesize($path), $user, $path);
        $this->presenter->uploadedFiles->persist($uploadedFile);
        $this->presenter->uploadedFiles->flush();

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
        $content = chr(0xef) . chr(0xbb) . chr(0xbf) . "Hello";
        $vfs = vfsStream::setup("root", null, [$filename => ""]);
        $vfsFile = $vfs->getChild($filename);

        $f = fopen($vfsFile->url(), "wb");
        fwrite($f, $content);
        fclose($f);

        // create new file upload
        $user = $this->presenter->accessManager->getUser($this->presenter->accessManager->decodeToken($token));
        $uploadedFile = new UploadedFile($filename, new DateTime(), 1, $user, $vfsFile->url());
        $this->presenter->uploadedFiles->persist($uploadedFile);
        $this->presenter->uploadedFiles->flush();

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
        $uploadedFile = new UploadedFile('name', new DateTime(), 1, $user, 'filepath');

        // mock file storage
        $mockFileStorage = Mockery::mock(UploadedFileStorage::class);
        $mockFileStorage->shouldReceive("store")->withArgs([$fileUpload, Mockery::any()])->andReturn(
            $uploadedFile
        )->once();
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

        $filename = "file.ext";
        $content = "ContentOfContentedFile";
        $vfs = vfsStream::setup("root", null, [$filename => $content]);

        $file = current($this->presenter->uploadedFiles->findAll());
        $file->localFilePath = $vfs->getChild($filename)->url();
        $this->em->flush();

        $request = new Nette\Application\Request(
            $this->presenterPath, 'GET', [
            'action' => 'download',
            'id' => $file->id
        ]
        );
        $response = $this->presenter->run($request);

        Assert::type(Nette\Application\Responses\FileResponse::class, $response);
    }

    public function testGroupMemberCanAccessAttachmentFiles()
    {
        $token = PresenterTestHelper::login($this->container, $this->userLogin);

        $filename = "file.ext";
        $content = "ContentOfContentedFile";
        $vfs = vfsStream::setup("root", null, [$filename => $content]);

        /** @var EntityManager $em */
        $em = $this->container->getByType(EntityManager::class);

        $file = current($em->getRepository(AttachmentFile::class)->findAll());
        $file->localFilePath = $vfs->getChild($filename)->url();
        $this->em->flush();

        $request = new Nette\Application\Request(
            $this->presenterPath, 'GET', [
            'action' => 'download',
            'id' => $file->id
        ]
        );
        $response = $this->presenter->run($request);

        Assert::type(Nette\Application\Responses\FileResponse::class, $response);
    }

    public function testOutsiderCannotAccessAttachmentFiles()
    {
        $token = PresenterTestHelper::login($this->container, $this->otherUserLogin);

        $filename = "file.ext";
        $content = "ContentOfContentedFile";
        $vfs = vfsStream::setup("root", null, [$filename => $content]);

        /** @var EntityManager $em */
        $em = $this->container->getByType(EntityManager::class);

        $file = current($em->getRepository(AttachmentFile::class)->findAll());

        $request = new Nette\Application\Request(
            $this->presenterPath, 'GET', [
            'action' => 'download',
            'id' => $file->id
        ]
        );

        // an exception being thrown is success in this case - it means that the FileResponse
        // tries to access the file, but the file does not exist on this machine
        Assert::throws(
            function () use ($request) {
                $this->presenter->run($request);
            },
            \Nette\Application\BadRequestException::class,
            "File '/some/path' doesn't exist."
        );
    }

    public function testDownloadResultArchive()
    {
        PresenterTestHelper::loginDefaultAdmin($this->container);
        $user = $this->presenter->users->getByEmail(PresenterTestHelper::ADMIN_LOGIN);
        $file = new \App\Model\Entity\SupplementaryExerciseFile("hefty name", new DateTime(), 1, "hash", $user);
        $this->presenter->supplementaryFiles->persist($file);
        $this->presenter->supplementaryFiles->flush();

        // mock everything you can
        $mockGuzzleStream = Mockery::mock(Psr\Http\Message\StreamInterface::class);
        $mockGuzzleStream->shouldReceive("getSize")->andReturn(0);
        $mockGuzzleStream->shouldReceive("eof")->andReturn(true);

        $mockProxy = Mockery::mock(App\Helpers\FileServerProxy::class);
        $mockProxy->shouldReceive("getFileserverFileStream")->withAnyArgs()->andReturn($mockGuzzleStream);
        $this->presenter->fileServerProxy = $mockProxy;

        $request = new Nette\Application\Request(
            'V1:UploadedFiles',
            'GET',
            ['action' => 'downloadSupplementaryFile', 'id' => $file->id]
        );
        $response = $this->presenter->run($request);
        Assert::same(App\Responses\GuzzleResponse::class, get_class($response));

        // Check invariants
        Assert::equal($file->getName(), $response->getName());
    }

}

$testCase = new TestUploadedFilesPresenter();
$testCase->run();
