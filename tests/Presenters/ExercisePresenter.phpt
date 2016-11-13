<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\Model\Entity\UploadedFile;
use App\Model\Repository\UploadedFiles;
use App\Model\Repository\Users;
use App\V1Module\Presenters\ExercisesPresenter;
use org\bovigo\vfs\vfsStream;
use Tester\Assert;

class TestExercisesPresenter extends Tester\TestCase
{
  private $adminLogin = "admin@admin.com";
  private $adminPassword = "admin";

  /** @var ExercisesPresenter */
  protected $presenter;

  /** @var Kdyby\Doctrine\EntityManager */
  protected $em;

  /** @var  Nette\DI\Container */
  protected $container;

  /** @var App\Model\Repository\RuntimeEnvironment */
  protected $runtimeEnvironments;

  /** @var App\Model\Repository\HardwareGroups */
  protected $hardwareGroups;

  /** @var Nette\Security\User */
  private $user;

  public function __construct()
  {
    global $container;
    $this->container = $container;
    $this->em = PresenterTestHelper::prepareDatabase($container);
    $this->user = $container->getByType(\Nette\Security\User::class);
    $this->runtimeEnvironments = $container->getByType(\App\Model\Repository\RuntimeEnvironments::class);
    $this->hardwareGroups = $container->getByType(\App\Model\Repository\HardwareGroups::class);
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
    $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    $request = new Nette\Application\Request('V1:Exercises', 'GET', ['action' => 'default']);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal($this->presenter->exercises->findAll(), $result['payload']);
    Assert::equal(5, count($result['payload']));
  }

  public function testListSearchExercises()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    $request = new Nette\Application\Request('V1:Exercises', 'GET', ['action' => 'default', 'search' => 'al']);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal(3, count($result['payload']));
  }

  public function testDetail()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

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
    $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    $allExercises = $this->presenter->exercises->findAll();
    $exercise = array_pop($allExercises);

    $request = new Nette\Application\Request('V1:Exercises',
      'POST',
      ['action' => 'updateDetail', 'id' => $exercise->id],
      [
        'name' => 'new name',
        'difficulty' => 'super hard',
        'localizedAssignments' => [
          [
            'locale' => 'cs-CZ',
            'description' => 'new descr',
            'name' => 'SomeNeatyName'
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

    $updatedLocalizedAssignments = $result['payload']->localizedAssignments;
    Assert::count(count($exercise->localizedAssignments), $updatedLocalizedAssignments);

    foreach ($exercise->localizedAssignments as $localized) {
      Assert::true($updatedLocalizedAssignments->contains($localized));
    }
    Assert::true($updatedLocalizedAssignments->exists(function ($key, $localized) {
      if ($localized->locale == "cs-CZ"
          && $localized->description == "new descr"
          && $localized->name == "SomeNeatyName") {
        return TRUE;
      }

      return FALSE;
    }));
  }

  public function testCreate()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    $request = new Nette\Application\Request('V1:Exercises', 'POST', ['action' => 'create']);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal($this->adminLogin, $result['payload']->getAuthor()->email);
  }

  public function testForkFrom()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    $allExercises = $this->presenter->exercises->findAll();
    $exercise = array_pop($allExercises);

    $request = new Nette\Application\Request('V1:Exercises', 'POST', ['action' => 'forkFrom', 'id' => $exercise->id]);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal($this->adminLogin, $result['payload']->getAuthor()->email);
    Assert::notEqual($exercise->id, $result['payload']->id);
    Assert::equal(count($exercise->localizedAssignments), count($result['payload']->localizedAssignments));
    Assert::equal($exercise->difficulty, $result['payload']->difficulty);
  }

  public function testUpdateRuntimeConfigs()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    $allExercises = $this->presenter->exercises->findAll();
    $exercise = array_pop($allExercises);

    $environments = $this->runtimeEnvironments->findAll();
    $hardwareGroups = $this->hardwareGroups->findAll();
    $environmentId = array_pop($environments)->getId();
    $hardwareGroupId = array_pop($hardwareGroups)->getId();

    $request = new Nette\Application\Request('V1:Exercises',
      'POST',
      ['action' => 'updateRuntimeConfigs', 'id' => $exercise->id],
      [
        'runtimeConfigs' => [
          [
            'customName' => 'runtimeConfigName',
            'runtimeEnvironmentId' => $environmentId,
            'jobConfig' => 'JobConfiguration',
            'hardwareGroupId' => $hardwareGroupId
          ]
        ]
      ]
    );
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::type(App\Model\Entity\Exercise::class, $result['payload']);

    $updatedRuntimeConfigs = $result["payload"]->getSolutionRuntimeConfigs();
    Assert::true($updatedRuntimeConfigs->exists(function ($key, $config) use ($environmentId, $hardwareGroupId) {
      if ($config->customName == "runtimeConfigName"
          && $config->runtimeEnvironment->getId() == $environmentId) {
        return TRUE;
      }

      return FALSE;
    }));
  }

  public function testSupplementaryFilesUpload() {
    // File system setup
    $structure = [
      "file1.txt" => "Lorem ipsum",
      "file2.txt" => "Dolor sit amet",
      "file3.txt" => "Consectetuer adipiscing elit"
    ];

    $fs = vfsStream::setup("root", NULL, $structure);

    // Database setup

    /** @var UploadedFiles $fileRepository */
    $fileRepository = $this->container->getByType(UploadedFiles::class);
    $files = [];

    foreach (array_keys($structure) as $name) {
      $uploadedFile = new UploadedFile(
        vfsStream::path("root"),
        $name,
        new DateTime(),
        42,
        $this->container->getByType(Users::class)->getByEmail($this->adminLogin)
      );

      $fileRepository->persist($uploadedFile, FALSE);
      $files[] = $uploadedFile;
    }

    $fileRepository->flush();

    // Mock file server setup
    $fileServerResponse = [
      "task1.txt" => "https://fs/tasks/hash1",
      "task2.txt" => "https://fs/tasks/hash2",
      "task3.txt" => "https://fs/tasks/hash3",
    ];

    /** @var Mockery\Mock $fileServerMock */
    $fileServerMock = Mockery::mock(App\Helpers\FileServerProxy::class);
    $fileServerMock->shouldReceive("sendSupplementaryFiles")->withAnyArgs()->andReturn($fileServerResponse);

    // Finally, the test itself
    $token = PresenterTestHelper::login($this->container, $this->adminLogin, $this->adminPassword);
    PresenterTestHelper::setToken($this->presenter, $token);

    $allExercises = $this->presenter->exercises->findAll();
    $exercise = array_pop($allExercises);

    $this->presenter->fileServer = $fileServerMock;

    /** @var Nette\Application\Responses\JsonResponse $response */
    $response = $this->presenter->run(new Nette\Application\Request("V1:Exercises", "POST", [
      "action" => 'uploadSupplementaryFiles',
      'id' => $exercise->id
    ], [
      'files' => array_map(function (UploadedFile $file) { return $file->id; }, $files)
    ]));

    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);
    Assert::equal($fileServerResponse, $response->getPayload()['payload']);
  }
}

$testCase = new TestExercisesPresenter();
$testCase->run();
