<?php
$container = require_once __DIR__ . "/../bootstrap.php";

use App\Exceptions\NotFoundException;
use App\Model\Entity\Pipeline;
use App\V1Module\Presenters\ExercisesPresenter;
use Tester\Assert;


/**
 * @testCase
 */
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

  /** @var App\Model\Repository\SupplementaryExerciseFiles */
  protected $supplementaryFiles;

  /** @var App\Model\Repository\Logins */
  protected $logins;

  /** @var Nette\Security\User */
  private $user;

  /** @var App\Model\Repository\Exercises */
  protected $exercises;

  /** @var App\Model\Repository\Pipelines */
  protected $pipelines;

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
    $this->supplementaryFiles = $container->getByType(\App\Model\Repository\SupplementaryExerciseFiles::class);
    $this->logins = $container->getByType(\App\Model\Repository\Logins::class);
    $this->exercises = $container->getByType(App\Model\Repository\Exercises::class);
    $this->additionalFiles = $container->getByType(\App\Model\Repository\AdditionalExerciseFiles::class);
    $this->pipelines = $container->getByType(App\Model\Repository\Pipelines::class);
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

    // check score config
    Assert::equal("testWeights: {  }\n", $payload->getScoreConfig());
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

  public function testGetPipelines() {
    PresenterTestHelper::loginDefaultAdmin($this->container);

    // prepare pipelines into exercise
    $user = $this->logins->getUser(PresenterTestHelper::ADMIN_LOGIN, PresenterTestHelper::ADMIN_PASSWORD);
    $exercise = current($this->exercises->findAll());
    $pipeline = current($this->pipelines->findAll());
    $pipeline1 = Pipeline::forkFrom($user, $pipeline, $exercise);
    $pipeline2 = Pipeline::forkFrom($user, $pipeline, $exercise);
    $pipeline1->setId("testGetPipelines1");
    $pipeline2->setId("testGetPipelines2");
    $this->pipelines->persist($pipeline1, FALSE);
    $this->pipelines->persist($pipeline2, FALSE);
    $this->pipelines->flush();

    $request = new Nette\Application\Request("V1:Exercises", 'GET',
      ['action' => 'getPipelines', 'id' => $exercise->getId()]);
    $response = $this->presenter->run($request);
    Assert::type(Nette\Application\Responses\JsonResponse::class, $response);

    $result = $response->getPayload();
    Assert::equal(200, $result['code']);

    $payload = $result['payload'];
    Assert::count(2, $payload);

    Assert::contains($pipeline1, $payload);
    Assert::contains($pipeline2, $payload);
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
