<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Console\RemoveInactiveUsers;
use App\Model\Repository\UploadedFiles;
use App\Model\Repository\Users;
use App\Model\Entity\UploadedFile;
use App\Helpers\AnonymizationHelper;
use Tester\Assert;


/**
 * @testCase
 */
class TestRemoveInactiveUsers extends Tester\TestCase
{
    /** @var RemoveInactiveUsers */
    protected $command;

    /** @var Nette\DI\Container */
    private $container;

    /** @var Users */
    private $users;

    public function __construct()
    {
        global $container;
        $this->container = $container;
        $this->users = $container->getByType(Users::class);
    }

    protected function setUp()
    {
        PresenterTestHelper::fillDatabase($this->container);
        $this->command = new RemoveInactiveUsers(
            [
                "disableAfter" => "1 month",
                "deleteAfter" => "1 year",
                "roles" => [ 'student' ],
            ],
            $this->users,
            $this->container->getByType(AnonymizationHelper::class)
        );
    }

    protected function tearDown()
    {
        Mockery::close();
    }

    private static function getTime($sub)
    {
        $res = new DateTime();
        $res->sub(DateInterval::createFromDateString($sub));
        return $res;
    }

    public function testCleanup()
    {
        $users = $this->users->findByRoles('student');
        $count = count($users);
        foreach ($users as $user) {
            $user->overrideCreatedAt(self::getTime("5 years"));
            $user->updateLastAuthenticationAt(); // make sure user is active
        }

        // one to be disabled, one to be deleted
        $disabled = $users[0];
        $disabled->updateLastAuthenticationAt(self::getTime("2 month"));

        $users[1]->updateLastAuthenticationAt(self::getTime("2 years"));
        $users[1]->setIsAllowed(false);
        $users[2]->updateLastAuthenticationAt(self::getTime("2 years"));
        $this->users->flush();

        $this->command->run(
            new Symfony\Component\Console\Input\StringInput("--silent --really-delete"),
            new Symfony\Component\Console\Output\NullOutput()
        );

        $this->users->refresh($disabled);
        Assert::false($disabled->isAllowed());
        Assert::count($count - 1, $this->users->findByRoles('student'));
    }
}

$testCase = new TestRemoveInactiveUsers();
$testCase->run();
