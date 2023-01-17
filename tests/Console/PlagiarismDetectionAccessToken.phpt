<?php

$container = require_once __DIR__ . "/../bootstrap.php";

use App\Console\PlagiarismDetectionAccessToken;
use App\Model\Repository\Users;
use App\Security\TokenScope;
use App\Security\Roles;
use App\Security\AccessToken;
use App\Security\AccessManager;
use Doctrine\ORM\EntityManagerInterface;
use Tester\Assert;


/**
 * @testCase
 */
class PlagiarismDetectionAccessTokenTest extends Tester\TestCase
{
    /** @var PlagiarismDetectionAccessToken */
    protected $command;

    /** @var EntityManagerInterface */
    protected $em;

    /** @var Nette\DI\Container */
    private $container;

    /** @var Users */
    private $users;

    /** @var AccessManager */
    private $accessManager;

    public function __construct()
    {
        global $container;
        $this->container = $container;
        $this->em = PresenterTestHelper::getEntityManager($container);
        $this->users = $container->getByType(Users::class);
        $this->accessManager = $container->getByType(AccessManager::class);
    }

    protected function setUp()
    {
        PresenterTestHelper::fillDatabase($this->container);
        $this->command = $this->container->getByType(PlagiarismDetectionAccessToken::class);
    }

    public function testCreateToken()
    {
        $admin = current(array_filter($this->users->findAll(), function ($u) {
            return $u->getRole() === Roles::SUPERADMIN_ROLE;
        }));
        Assert::notNull($admin);

        $memory = fopen('php://memory', 'rw');
        $this->command->run(
            new Symfony\Component\Console\Input\StringInput('--expiration=420 ' . $admin->getId()),
            new Symfony\Component\Console\Output\StreamOutput($memory)
        );
        fseek($memory, 0);
        $rawToken = stream_get_contents($memory);
        fclose($memory);

        $token = $this->accessManager->decodeToken($rawToken);
        Assert::type(AccessToken::class, $token);
        Assert::equal($admin->getId(), $token->getUserId());
        Assert::true($token->isInScope(TokenScope::PLAGIARISM));
        Assert::equal(420, $token->getExpirationTime());
    }
}

$testCase = new PlagiarismDetectionAccessTokenTest();
$testCase->run();
