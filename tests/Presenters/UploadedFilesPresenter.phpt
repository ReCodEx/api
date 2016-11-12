<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\V1Module\Presenters\UploadedFilesPresenter;
use App\Model\Entity\UploadedFile;
use App\Model\Repository\Logins;
use App\Exceptions\ForbiddenRequestException;
use App\Helpers\UploadedFileStorage;
use Tester\Assert;
use org\bovigo\vfs\vfsStream;

/**
 * @httpCode any
 */
class TestUploadedFilesPresenter extends Tester\TestCase
{
  private $userLogin = "submitUser1@example.com";
  private $userPassword = "password";

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
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);
    PresenterTestHelper::setToken($this->presenter, $token);

    $file = current($this->presenter->uploadedFiles->findAll());

    $request = new Nette\Application\Request($this->presenterPath, 'GET',
      ['action' => 'detail', 'id' => $file->getId()]);
    Assert::exception(function () use ($request) {
      $this->presenter->run($request);
    }, ForbiddenRequestException::class);
  }

  public function testDetail()
  {
    $token = PresenterTestHelper::login($this->container, $this->userLogin, $this->userPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

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
    $token = PresenterTestHelper::login($this->container, $this->userLogin, $this->userPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    $file = current($this->presenter->uploadedFiles->findAll());

    $request = new Nette\Application\Request($this->presenterPath, 'GET',
      ['action' => 'download', 'id' => $file->getId()]);
    Assert::exception(function () use ($request) {
      $this->presenter->run($request);
    }, \Nette\Application\BadRequestException::class);
  }

  public function testDownload()
  {
    $token = PresenterTestHelper::login($this->container, $this->userLogin, $this->userPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    // create virtual filesystem setup
    $filename = "file.ext";
    $content = "ContentOfContentedFile";
    $vfs = vfsStream::setup("root", NULL, [$filename => $content]);
    $vfsFile = $vfs->getChild($filename);

    // create new file upload
    $user = $this->logins->getUser($this->userLogin, $this->userPassword);
    $uploadedFile = new UploadedFile($vfsFile->url(), $filename, new \DateTime, 1, $user);
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
    $token = PresenterTestHelper::login($this->container, $this->userLogin, $this->userPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    // create virtual filesystem setup
    $filename = "file.ext";
    $content = "ContentOfContentedFile";
    $vfs = vfsStream::setup("root", NULL, [$filename => $content]);
    $vfsFile = $vfs->getChild($filename);

    // create new file upload
    $user = current($this->presenter->users->findAll());
    $uploadedFile = new UploadedFile($vfsFile->url(), $filename, new \DateTime, 1, $user);
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
    $token = PresenterTestHelper::login($this->container, $this->userLogin, $this->userPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    $request = new Nette\Application\Request($this->presenterPath, 'POST', ['action' => 'upload']);
    Assert::exception(function () use ($request) {
      $this->presenter->run($request);
    }, App\Exceptions\BadRequestException::class);
  }

  public function testUpload()
  {
    $token = PresenterTestHelper::login($this->container, $this->userLogin, $this->userPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    $user = current($this->presenter->users->findAll());
    $file = ['name' => "filename", 'type' => 'type', 'size' => 1, 'tmp_name' => 'tmpname', 'error' => ''];
    $fileUpload = new Nette\Http\FileUpload($file);
    $uploadedFile = new UploadedFile('filepath', 'name', new \DateTime, 1, $user);

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

}

$testCase = new TestUploadedFilesPresenter();
$testCase->run();