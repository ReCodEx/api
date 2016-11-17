<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\Helpers\SupplementaryFileStorage;
use App\V1Module\Presenters\ExercisesPresenter;
use App\Model\Entity\SupplementaryFile;
use Tester\Assert;

class TestExercisesPresenter extends Tester\TestCase
{
  private $adminLogin = "admin@admin.com";

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

  /** @var App\Model\Repository\SupplementaryFiles */
  protected $supplementaryFiles;

  /** @var App\Model\Repository\Logins */
  protected $logins;

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
    $this->supplementaryFiles = $container->getByType(\App\Model\Repository\SupplementaryFiles::class);
    $this->logins = $container->getByType(\App\Model\Repository\Logins::class);
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
    Assert::equal($this->presenter->exercises->findAll(), $result['payload']);
    Assert::equal(5, count($result['payload']));
  }

  public function testListSearchExercises()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin);

    $request = new Nette\Application\Request('V1:Exercises', 'GET', ['action' => 'default', 'search' => 'al']);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal(3, count($result['payload']));
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
    $token = PresenterTestHelper::login($this->container, $this->adminLogin);

    $request = new Nette\Application\Request('V1:Exercises', 'POST', ['action' => 'create']);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::equal($this->adminLogin, $result['payload']->getAuthor()->email);
  }

  public function testUpdateRuntimeConfigs()
  {
    $token = PresenterTestHelper::login($this->container, $this->adminLogin);

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
            'name' => 'runtimeConfigName',
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
    Assert::true($updatedRuntimeConfigs->exists(function ($key, $config) use ($environmentId) {
      if ($config->name == "runtimeConfigName"
          && $config->runtimeEnvironment->getId() == $environmentId) {
        return TRUE;
      }

      return FALSE;
    }));
  }

  public function testSupplementaryFilesUpload() {
    // Mock file server setup
    $filename = "task1.txt";
    $fileServerResponse = [
      $filename => "https://fs/tasks/hash1"
    ];

    /** @var Mockery\Mock $fileServerMock */
    $fileServerMock = Mockery::mock(App\Helpers\FileServerProxy::class);
    $fileServerMock->shouldReceive("sendSupplementaryFiles")->withAnyArgs()->andReturn($fileServerResponse)->once();
    $this->presenter->supplementaryFileStorage = new SupplementaryFileStorage($fileServerMock);

    // Finally, the test itself
    $token = PresenterTestHelper::login($this->container, $this->adminLogin);

    $exercise = current($this->presenter->exercises->findAll());
    $file = ['name' => $filename, 'type' => 'type', 'size' => 1, 'tmp_name' => 'tmpname', 'error' => UPLOAD_ERR_OK];
    $fileUpload = new Nette\Http\FileUpload($file);

    /** @var Nette\Application\Responses\JsonResponse $response */
    $response = $this->presenter->run(new Nette\Application\Request("V1:Exercises", "POST", [
      "action" => 'uploadSupplementaryFile',
      'id' => $exercise->id
    ], [], [$fileUpload]));

    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $payload = $response->getPayload()['payload'];
    Assert::type(App\Model\Entity\SupplementaryFile::class, $payload);
    Assert::equal($filename, $payload->getName());
  }

  public function testGetSupplementaryFiles() {
    $token = PresenterTestHelper::loginDefaultAdmin($this->container);

    // prepare files into exercise
    $user = $this->logins->getUser(PresenterTestHelper::ADMIN_LOGIN, PresenterTestHelper::ADMIN_PASSWORD);
    $exercise = current($this->presenter->exercises->findAll());
    $expectedFile1 = new SupplementaryFile("name1", "hashName1", "fileServerPath1", 1, $user, $exercise);
    $expectedFile2 = new SupplementaryFile("name2", "hashName2", "fileServerPath2", 2, $user, $exercise);
    $this->supplementaryFiles->persist($expectedFile1);
    $this->supplementaryFiles->persist($expectedFile2);
    $this->supplementaryFiles->flush();

    $request = new Nette\Application\Request("V1:Exercises", 'GET',
      ['action' => 'getSupplementaryFiles', 'id' => $exercise->getId()]);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);
    Assert::count(2, $result['payload']);

    $file1 = $result['payload'][0];
    $file2 = $result['payload'][1];
    Assert::same($expectedFile1, $file1);
    Assert::same($expectedFile2, $file2);
  }
}

$testCase = new TestExercisesPresenter();
$testCase->run();
