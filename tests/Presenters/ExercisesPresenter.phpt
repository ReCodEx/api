<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\Exceptions\NotFoundException;
use App\Helpers\ExerciseFileStorage;
use App\Helpers\FileServerProxy;
use App\Model\Entity\AdditionalExerciseFile;
use App\Model\Entity\UploadedFile;
use App\V1Module\Presenters\ExercisesPresenter;
use App\Model\Entity\ExerciseFile;
use Tester\Assert;

class TestExercisesPresenter extends Tester\TestCase
{
  private $adminLogin = "admin@admin.com";
  private $groupSupervisorLogin = "demoGroupSupervisor@example.com";

  /** @var ExercisesPresenter */
  protected $presenter;

  /** @var Kdyby\Doctrine\EntityManager */
  protected $em;

  /** @var  Nette\DI\Container */
  protected $container;

  /** @var App\Model\Repository\RuntimeEnvironments */
  protected $runtimeEnvironments;

  /** @var App\Model\Repository\HardwareGroups */
  protected $hardwareGroups;

  /** @var App\Model\Repository\ExerciseFiles */
  protected $supplementaryFiles;

  /** @var App\Model\Repository\Logins */
  protected $logins;

  /** @var Nette\Security\User */
  private $user;

  /** @var App\Model\Repository\Exercises */
  protected $exercises;

  /** @var App\Model\Repository\AdditionalExerciseFiles */
  protected $additionalFiles;

  public function __construct()
  {
    global $container;
    $this->container = $container;
    $this->em = PresenterTestHelper::prepareDatabase($container);
    $this->user = $container->getByType(\Nette\Security\User::class);
    $this->runtimeEnvironments = $container->getByType(\App\Model\Repository\RuntimeEnvironments::class);
    $this->hardwareGroups = $container->getByType(\App\Model\Repository\HardwareGroups::class);
    $this->supplementaryFiles = $container->getByType(\App\Model\Repository\ExerciseFiles::class);
    $this->logins = $container->getByType(\App\Model\Repository\Logins::class);
    $this->exercises = $container->getByType(App\Model\Repository\Exercises::class);
    $this->additionalFiles = $container->getByType(\App\Model\Repository\AdditionalExerciseFiles::class);
  }

  protected function setUp()
  {
    PresenterTestHelper::fillDatabase($this->container);

    $this->presenter = PresenterTestHelper::createPresenter($this->container, ExercisesPresenter::class);
  }

  protected function tearDown()
  {
    Mockery::close();

    if ($this->user->isLoggedIn()) {
      $this->user->logout(TRUE);
    }
  }

  public function testListAllExercises()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin);

    $request = new Nette\Application\Request('V1:Exercises', 'GET', ['action' => 'default']);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::same($this->presenter->exercises->findAll(), $result['payload']);
    Assert::count(count($this->presenter->exercises->findAll()), $result['payload']);
  }

  public function testAdminListSearchExercises()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin);

    $request = new Nette\Application\Request('V1:Exercises', 'GET', ['action' => 'default', 'search' => 'al']);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::count(5, $result['payload']);
  }

  public function testSupervisorListSearchExercises()
  {
    $token = PresenterTestHelper::login($this->container, $this->groupSupervisorLogin);

    $request = new Nette\Application\Request('V1:Exercises', 'GET', ['action' => 'default', 'search' => 'al']);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::count(4, $result['payload']);
  }

  public function testDetail()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin);

    $allExercises = $this->presenter->exercises->findAll();
    $exercise = array_pop($allExercises);

    $request = new Nette\Application\Request('V1:Exercises', 'GET', ['action' => 'detail', 'id' => $exercise->id]);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::same($exercise, $result['payload']);
  }

  public function testUpdateDetail()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin);

    $allExercises = $this->presenter->exercises->findAll();
    $exercise = array_pop($allExercises);

    $request = new Nette\Application\Request('V1:Exercises',
      'POST',
      ['action' => 'updateDetail', 'id' => $exercise->id],
      [
        'name' => 'new name',
        'version' => 1,
        'difficulty' => 'super hard',
        'isPublic' => FALSE,
        'description' => 'some neaty description',
        'localizedTexts' => [
          [
            'locale' => 'cs-CZ',
            'text' => 'new descr',
          ]
        ]
      ]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal('new name', $result['payload']->name);
    Assert::equal('super hard', $result['payload']->difficulty);
    Assert::equal(FALSE, $result['payload']->isPublic);
    Assert::equal('some neaty description', $result['payload']->description);

    $updatedLocalizedTexts = $result['payload']->localizedTexts;
    Assert::count(count($exercise->localizedTexts), $updatedLocalizedTexts);

    foreach ($exercise->localizedTexts as $localized) {
      Assert::true($updatedLocalizedTexts->contains($localized));
    }
    Assert::true($updatedLocalizedTexts->exists(function ($key, $localized) {
      if ($localized->locale == "cs-CZ"
          && $localized->text == "new descr") {
        return TRUE;
      }

      return FALSE;
    }));
  }

  public function testValidatePipeline()
  {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    $exercise = current($this->presenter->exercises->findAll());

    $request = new Nette\Application\Request('V1:Exercises', 'POST',
      ['action' => 'validate', 'id' => $exercise->getId()],
      ['version' => 2]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    $payload = $result['payload'];
    Assert::equal(200, $result['code']);

    Assert::true(is_array($payload));
    Assert::true(array_key_exists("versionIsUpToDate", $payload));
    Assert::false($payload["versionIsUpToDate"]);
  }

  public function testCreate()
  {
    PresenterTestHelper::login($this->container, $this->adminLogin);

    $request = new Nette\Application\Request('V1:Exercises', 'POST', ['action' => 'create']);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    $payload = $result['payload'];

    Assert::type(\App\Model\Entity\Exercise::class, $payload);
    Assert::equal($this->adminLogin, $payload->getAuthor()->email);
    Assert::equal("Exercise by " . $this->user->identity->getUserData()->getName(), $payload->getName());
    Assert::notEqual(null, $payload->getExerciseConfig());
  }

  public function testRemove()
  {
    PresenterTestHelper::login($this->container, $this->adminLogin);

    $exercise = current($this->presenter->exercises->findAll());

    $request = new Nette\Application\Request('V1:Exercises', 'DELETE',
      ['action' => 'remove', 'id' => $exercise->getId()]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal("OK", $result['payload']);

    Assert::exception(function () use ($exercise) {
      $this->presenter->exercises->findOrThrow($exercise->getId());
    }, NotFoundException::class);
  }

  public function testSupplementaryFilesUpload() {
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

    $user = $this->presenter->users->getByEmail($this->adminLogin);

    $file1 = new UploadedFile($filename1, new \DateTime, 0, $user, $filename1);
    $file2 = new UploadedFile($filename2, new \DateTime, 0, $user, $filename2);
    $this->presenter->uploadedFiles->persist($file1);
    $this->presenter->uploadedFiles->persist($file2);
    $this->presenter->uploadedFiles->flush();

    /** @var FileServerProxy|Mockery\Mock $fileServerMock */
    $fileServerMock = Mockery::mock(FileServerProxy::class);
    $fileServerMock->shouldReceive("sendSupplementaryFiles")->with([$file1])->andReturn($fileServerResponse1)->between(0, 1);
    $fileServerMock->shouldReceive("sendSupplementaryFiles")->with([$file2])->andReturn($fileServerResponse2)->between(0, 1);
    $fileServerMock->shouldReceive("sendSupplementaryFiles")->with([$file1, $file2])->andReturn($fileServerResponseMerged)->between(0, 1);
    $this->presenter->supplementaryFileStorage = new ExerciseFileStorage($fileServerMock);

    // mock file storage
    $mockFileStorage = Mockery::mock($this->presenter->uploadedFileStorage);
    $mockFileStorage->shouldDeferMissing();
    $mockFileStorage->shouldReceive("delete")->with($file1)->once();
    $mockFileStorage->shouldReceive("delete")->with($file2)->once();
    $this->presenter->uploadedFileStorage = $mockFileStorage;

    // Finally, the test itself
    $token = PresenterTestHelper::login($this->container, $this->adminLogin);

    $exercise = current($this->presenter->exercises->findAll());
    $files = [ $file1->getId(), $file2->getId() ];

    /** @var Nette\Application\Responses\JsonResponse $response */
    $response = $this->presenter->run(new Nette\Application\Request("V1:Exercises", "POST", [
      "action" => 'uploadSupplementaryFiles',
      'id' => $exercise->id
    ], [
      'files' => $files
    ]));

    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $payload = $response->getPayload()['payload'];
    Assert::count(2, $payload);

    foreach ($payload as $item) {
      Assert::type(App\Model\Entity\ExerciseFile::class, $item);
    }
  }

  public function testGetSupplementaryFiles() {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    // prepare files into exercise
    $user = $this->logins->getUser(PresenterTestHelper::ADMIN_LOGIN, PresenterTestHelper::ADMIN_PASSWORD);
    $exercise = current($this->presenter->exercises->findAll());
    $expectedFile1 = new ExerciseFile("name1", new DateTime(), 1, "hashName1", "fileServerPath1", $user, $exercise);
    $expectedFile2 = new ExerciseFile("name2", new DateTime(), 2, "hashName2", "fileServerPath2", $user, $exercise);
    $this->supplementaryFiles->persist($expectedFile1, FALSE);
    $this->supplementaryFiles->persist($expectedFile2, FALSE);
    $this->supplementaryFiles->flush();

    $request = new Nette\Application\Request("V1:Exercises", 'GET',
      ['action' => 'getSupplementaryFiles', 'id' => $exercise->getId()]);
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

  public function testGetAdditionalFiles() {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    // prepare files into exercise
    $user = $this->logins->getUser(PresenterTestHelper::ADMIN_LOGIN, PresenterTestHelper::ADMIN_PASSWORD);
    $exercise = $this->presenter->exercises->findOneBy([
      "name" => "aloha exercise"
    ]);
    $expectedFile1 = new AdditionalExerciseFile("name1", new DateTime(), 1, "hashName1", $user, $exercise);
    $expectedFile2 = new AdditionalExerciseFile("name2", new DateTime(), 2, "hashName2", $user, $exercise);
    $this->additionalFiles->persist($expectedFile1, FALSE);
    $this->additionalFiles->persist($expectedFile2, FALSE);
    $this->additionalFiles->flush();

    $request = new Nette\Application\Request("V1:Exercises", 'GET',
      ['action' => 'getAdditionalFiles', 'id' => $exercise->getId()]);
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

  public function testGetPipelines() {
    // @todo
  }

  public function testForkFrom()
  {
    PresenterTestHelper::login($this->container, $this->adminLogin);

    $user = $this->logins->getUser(PresenterTestHelper::ADMIN_LOGIN, PresenterTestHelper::ADMIN_PASSWORD);
    $exercise = current($this->presenter->exercises->findAll());

    $request = new Nette\Application\Request('V1:Exercises', 'GET',
      ['action' => 'forkFrom', 'id' => $exercise->getId()]);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    $forked = $result['payload'];
    Assert::type(\App\Model\Entity\Exercise::class, $forked);
    Assert::equal($exercise->getName(), $forked->getName());
    Assert::equal(1, $forked->getVersion());
    Assert::equal($user, $forked->getAuthor());
  }

}

$testCase = new TestExercisesPresenter();
$testCase->run();
