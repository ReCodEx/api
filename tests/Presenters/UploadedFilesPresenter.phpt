<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\Model\Entity\AdditionalExerciseFile;
use App\V1Module\Presenters\UploadedFilesPresenter;
use App\Model\Entity\UploadedFile;
use App\Model\Repository\Logins;
use App\Exceptions\ForbiddenRequestException;
use App\Helpers\UploadedFileStorage;
use Doctrine\ORM\EntityManager;
use Tester\Assert;
use org\bovigo\vfs\vfsStream;

/**
 * @httpCode any
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
    $this->em = PresenterTestHelper::prepareDatabase($container);
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
      $this->user->logout(TRUE);
    }
  }

  public function testUserCannotAccessDetail()
  {
    $token = PresenterTestHelper::login($this->container, $this->otherUserLogin);

    $file = current($this->presenter->uploadedFiles->findAll());

    $request = new Nette\Application\Request($this->presenterPath, 'GET',
      ['action' => 'detail', 'id' => $file->getId()]);
    Assert::exception(function () use ($request) {
      $this->presenter->run($request);
    }, ForbiddenRequestException::class);
  }

  public function testDetail()
  {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    $file = current($this->presenter->uploadedFiles->findAll());

    $request = new Nette\Application\Request($this->presenterPath, 'GET',
      ['action' => 'detail', 'id' => $file->getId()]);
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

    $request = new Nette\Application\Request($this->presenterPath, 'GET',
      ['action' => 'download', 'id' => $file->getId()]);
    Assert::exception(function () use ($request) {
      $this->presenter->run($request);
    }, \Nette\Application\BadRequestException::class);
  }

  public function testDownload()
  {
    $token = PresenterTestHelper::login($this->container, $this->userLogin);

    // create virtual filesystem setup
    $filename = "file.ext";
    $content = "ContentOfContentedFile";
    $vfs = vfsStream::setup("root", NULL, [$filename => $content]);
    $vfsFile = $vfs->getChild($filename);

    // create new file upload
    $user = $this->logins->getUser($this->userLogin, $this->userPassword);
    $uploadedFile = new UploadedFile($filename, new \DateTime, 1, $user, $vfsFile->url());
    $this->presenter->uploadedFiles->persist($uploadedFile);
    $this->presenter->uploadedFiles->flush();

    $request = new Nette\Application\Request($this->presenterPath, 'GET',
      ['action' => 'download', 'id' => $uploadedFile->getId()]);
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
    $vfs = vfsStream::setup("root", NULL, [$filename => $content]);
    $vfsFile = $vfs->getChild($filename);

    // create new file upload
    $user = $this->presenter->accessManager->getUser($this->presenter->accessManager->decodeToken($token));
    $uploadedFile = new UploadedFile($filename, new \DateTime, 1, $user, $vfsFile->url());
    $this->presenter->uploadedFiles->persist($uploadedFile);
    $this->presenter->uploadedFiles->flush();

    $request = new Nette\Application\Request($this->presenterPath, 'GET',
      ['action' => 'content', 'id' => $uploadedFile->getId()]);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal($content, $result['payload']);
  }

  public function testNoFilesUpload()
  {
    $token = PresenterTestHelper::login($this->container, $this->userLogin);

    $request = new Nette\Application\Request($this->presenterPath, 'POST', ['action' => 'upload']);
    Assert::exception(function () use ($request) {
      $this->presenter->run($request);
    }, App\Exceptions\BadRequestException::class);
  }

  public function testUpload()
  {
    $token = PresenterTestHelper::login($this->container, $this->userLogin);

    $user = current($this->presenter->users->findAll());
    $file = ['name' => "filename", 'type' => 'type', 'size' => 1, 'tmp_name' => 'tmpname'];
    $fileUpload = new Nette\Http\FileUpload($file);
    $uploadedFile = new UploadedFile('name', new \DateTime, 1, $user, 'filepath');

    // mock file storage
    $mockFileStorage = Mockery::mock(UploadedFileStorage::class);
    $mockFileStorage->shouldReceive("store")->withArgs([$fileUpload, Mockery::any()])->andReturn($uploadedFile)->once();
    $this->presenter->fileStorage = $mockFileStorage;

    $request = new Nette\Application\Request($this->presenterPath, 'POST',
      ['action' => 'upload'], [], [$fileUpload]);
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
    $vfs = vfsStream::setup("root", NULL, [$filename => $content]);

    $file = current($this->presenter->uploadedFiles->findAll());
    $file->localFilePath = $vfs->getChild($filename)->url();
    $this->em->flush();

    $request = new Nette\Application\Request($this->presenterPath, 'GET', [
      'action' => 'download',
      'id' => $file->id
    ]);
    $response = $this->presenter->run($request);

    Assert::type(Nette\Application\Responses\FileResponse::class, $response);
  }

  public function testGroupMemberCanAccessAdditionalFiles()
  {
    $token = PresenterTestHelper::login($this->container, $this->userLogin);

    $filename = "file.ext";
    $content = "ContentOfContentedFile";
    $vfs = vfsStream::setup("root", NULL, [$filename => $content]);

    /** @var EntityManager $em */
    $em = $this->container->getByType(EntityManager::class);

    $file = current($em->getRepository(AdditionalExerciseFile::class)->findAll());
    $file->localFilePath = $vfs->getChild($filename)->url();
    $this->em->flush();

    $request = new Nette\Application\Request($this->presenterPath, 'GET', [
      'action' => 'download',
      'id' => $file->id
    ]);
    $response = $this->presenter->run($request);

    Assert::type(Nette\Application\Responses\FileResponse::class, $response);
  }

  public function testOutsiderCannotAccessAdditionalFiles()
  {
    $token = PresenterTestHelper::login($this->container, $this->otherUserLogin);

    $filename = "file.ext";
    $content = "ContentOfContentedFile";
    $vfs = vfsStream::setup("root", NULL, [$filename => $content]);

    /** @var EntityManager $em */
    $em = $this->container->getByType(EntityManager::class);

    $file = current($em->getRepository(AdditionalExerciseFile::class)->findAll());

    $request = new Nette\Application\Request($this->presenterPath, 'GET', [
      'action' => 'download',
      'id' => $file->id
    ]);

    Assert::exception(function () use ($request) {
      $this->presenter->run($request);
    }, ForbiddenRequestException::class);
  }
}

$testCase = new TestUploadedFilesPresenter();
$testCase->run();