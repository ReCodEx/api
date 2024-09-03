<?php

use App\Model\Entity\User;
use App\Model\Repository\Users;
use App\Security\AccessManager;
use App\Security\TokenScope;
use Doctrine\Common\EventManager;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Nette\Application\Request;
use Nette\Application\Response;
use Nette\Application\Responses\ForwardResponse;
use Nette\Application\Responses\JsonResponse;
use Nette\DI\Container;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use Nette\Utils\JsonException;
use Nettrine\ORM\EntityManagerDecorator;
use Symfony\Component\Process\Process;

class PresenterTestHelper
{
    public const ADMIN_LOGIN = "admin@admin.com";
    public const ADMIN_PASSWORD = "admin";

    public const STUDENT_GROUP_MEMBER_LOGIN = "demoGroupMember1@example.com";
    public const GROUP_SUPERVISOR_LOGIN = "demoGroupSupervisor@example.com";
    public const GROUP_SUPERVISOR2_LOGIN = "demoGroupSupervisor2@example.com";
    public const ANOTHER_SUPERVISOR_LOGIN = "anotherSupervisor@example.com";

    private static function createEntityManager(
        string $dbPath,
        Configuration $configuration,
        EventManager $eventManager
    ): EntityManagerDecorator {
        return new EntityManagerDecorator(EntityManager::create(
            ["driver" => "pdo_sqlite", "path" => $dbPath],
            $configuration,
            $eventManager
        ));
    }

    public static function replaceService(Container $container, $service, $type = null)
    {
        $type = $type ?? get_class($service);
        $emServiceName = $container->findByType($type)[0];
        $container->removeService($emServiceName);
        $container->addService($emServiceName, $service);
    }

    /**
     * @throws Tester\AssertException
     * @throws JsonException
     */
    public static function extractPayload(Response $response, $jsonify = true)
    {
        Tester\Assert::type(JsonResponse::class, $response);

        /** @var JsonResponse $response */
        Tester\Assert::same(200, $response->getPayload()["code"]);
        $payload = $response->getPayload()["payload"];

        if ($jsonify) {
            return Json::decode(Json::encode($payload), Json::FORCE_ARRAY);
        }

        return $payload;
    }

    public static function getEntityManager(Container $container): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = $container->getByType(EntityManagerInterface::class);
        return $em;
    }

    public static function fillDatabase(Container $container, string $group = "demo")
    {
        $tmpDir = $container->getParameters()["tempDir"] . DIRECTORY_SEPARATOR . "testDB";
        if (is_dir("/tmp")) { // Creating a sqlite db in tmpfs is much faster than on a regular file system
            $tmpDir = "/tmp/ReCodEx" . DIRECTORY_SEPARATOR . "testDB";
        }

        FileSystem::createDir($tmpDir);

        $dbPath = $tmpDir . DIRECTORY_SEPARATOR . "database_" . $group . ".db";
        $dumpPath = $tmpDir . DIRECTORY_SEPARATOR . "database_" . $group . ".sql";
        $originalEm = static::getEntityManager($container);

        $lockHandle = fopen($dbPath . ".lock", "c+");
        flock($lockHandle, LOCK_EX);

        if (!is_file($dbPath) || !is_file($dumpPath) || filesize($dumpPath) === 0) {
            // Create a new entity manager connected to a temporary sqlite database
            $schemaEm = self::createEntityManager(
                $dbPath,
                $originalEm->getConfiguration(),
                $originalEm->getEventManager()
            );
            static::replaceService($container, $schemaEm, EntityManagerDecorator::class);

            $schemaTool = new Doctrine\ORM\Tools\SchemaTool($schemaEm);
            $schemaTool->dropSchema($schemaEm->getMetadataFactory()->getAllMetadata());
            $schemaTool->createSchema($schemaEm->getMetadataFactory()->getAllMetadata());

            $command = $container->getByType(App\Console\DoctrineFixtures::class);

            $input = new Symfony\Component\Console\Input\ArgvInput(["index.php", "-test", "base", $group]);
            $output = new Symfony\Component\Console\Output\NullOutput();

            $command->run($input, $output);
            $originalEm->flush();
            $originalEm->clear();

            $sqliteProcess = new Process(["sqlite3", "--bail", $dbPath]);
            $sqliteProcess->setInput(".dump");
            $rc = $sqliteProcess->run();

            if ($rc !== 0) {
                throw new RuntimeException(
                    'Could not run sqlite export. Make sure "sqlite3" is installed and accessible through $PATH.'
                );
            }

            file_put_contents($dumpPath, $sqliteProcess->getOutput());

            // Replace the temporary entity manager with the original one
            static::replaceService($container, $originalEm, EntityManagerDecorator::class);
        }

        flock($lockHandle, LOCK_UN);
        $originalEm->getConnection()->executeStatement(file_get_contents($dumpPath));
        $originalEm->clear();
    }

    public static function createPresenter(Nette\DI\Container $container, string $class): Nette\Application\UI\Presenter
    {
        $names = $container->findByType($class);
        $name = reset($names);

        /** @var Nette\Application\UI\Presenter $presenter */
        $presenter = $container->createService($name);
        $presenter->autoCanonicalize = false;

        return $presenter;
    }

    public static function login(
        Container $container,
        string $login,
        array $scopes = [TokenScope::MASTER, TokenScope::REFRESH]
    ): string {
        /** @var \Nette\Security\User $userSession */
        $userSession = $container->getByType(\Nette\Security\User::class);
        $user = $container->getByType(Users::class)->getByEmail($login);

        /** @var AccessManager $accessManager */
        $accessManager = $container->getByType(AccessManager::class);
        $tokenText = $accessManager->issueToken($user, null, $scopes);
        $token = $accessManager->decodeToken($tokenText);

        $userSession->login(new \App\Security\Identity($user, $token));
        return $tokenText;
    }

    public static function loginDefaultAdmin(
        Container $container,
        array $scopes = [TokenScope::MASTER, TokenScope::REFRESH]
    ): string {
        return self::login($container, self::ADMIN_LOGIN, $scopes);
    }

    public static function getUser(Container $container, $login = null): User
    {
        $login = $login ?? self::ADMIN_LOGIN;
        return $container->getByType(Users::class)->getByEmail($login);
    }

    public static function jsonResponse($payload)
    {
        return Json::decode(Json::encode($payload), Json::FORCE_ARRAY);
    }

    /**
     * Perform regular presenter request and make common asserts.
     * @param mixed $presenter The presenter which should handle the request.
     * @param string $module String representing the module path (e.g., 'V1:Exercises').
     * @param string $method HTTP method of the request (GET, POST, ...).
     * @param array $params Parameters of the request.
     * @param array $post Body of the request (must be POST).
     * @param int $expectedCode Expected HTTP response code (200 by default).
     * @return array|null Payload subtree of JSON request.
     * @throws Exception
     */
    public static function performPresenterRequest(
        $presenter,
        string $module,
        string $method = 'GET',
        array $params = [],
        array $post = [],
        $expectedCode = 200
    ) {
        $request = new Request($module, $method, $params, $post);
        $response = $presenter->run($request);
        while ($response instanceof ForwardResponse) {
            $response = $presenter->run($response->getRequest());
        }
        Tester\Assert::type(JsonResponse::class, $response);

        $result = $response->getPayload();
        Tester\Assert::equal($expectedCode, $result['code']);
        return array_key_exists('payload', $result) ? $result['payload'] : null;
    }

    /**
     * Traverse a list of objects, assoc. arrays, or string IDs and convert it into an array indexed by the IDs.
     * @param array $list List of entities to be processed.
     * @return array Indexed by IDs, values are trues.
     * @throws Exception
     */
    public static function extractIdsMap(array $list)
    {
        $res = [];
        foreach ($list as $item) {
            if (is_array($item) && !empty($item['id'])) {
                $res[$item['id']] = true;
            } else {
                if (is_object($item) && !empty($item->getId())) {
                    $res[$item->getId()] = true;
                } else {
                    Tester\Assert::match('#^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$#', $item);
                    $res[(string)$item->id] = true;
                }
            }
        }
        return $res;
    }

    private static function flatten(array &$res, string $keyPrefix, array $array, string $separator)
    {
        foreach ($array as $name => $value) {
            $name = "$keyPrefix$separator$name";
            if (is_array($value)) {
                self::flatten($res, $name, $value, $separator);
            } else {
                $res[$name] = $value;
            }
        }
    }

    /**
     * Take array representing nested JSON structure and flatten it.
     * Keys are concatenated using given string as separator.
     * The result is sorted by keys.
     * @param array $array Input structure encoded in nested assoc arrays.
     * @param string $separator String used as glue for keys.
     * @return array Sorted array where all scalar values are kept and keys concatenated.
     */
    public static function flattenNestedStructure(array $array, string $separator = '/')
    {
        $res = [];
        self::flatten($res, '', $array, $separator);
        ksort($res);
        return $res;
    }
}
