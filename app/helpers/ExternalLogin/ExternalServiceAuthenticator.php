<?php

namespace App\Helpers\ExternalLogin;

use App\Exceptions\BadRequestException;
use App\Exceptions\FrontendErrorMappings;
use App\Exceptions\WrongCredentialsException;
use App\Exceptions\InvalidExternalTokenException;
use App\Exceptions\ForbiddenRequestException;
use App\Model\Entity\Instance;
use App\Model\Entity\User;
use App\Model\Repository\ExternalLogins;
use App\Model\Repository\Logins;
use App\Model\Repository\Users;
use App\Model\Repository\Instances;
use App\Helpers\EmailVerificationHelper;
use App\Helpers\FailureHelper;
use Nette\Utils\Arrays;
use Nette\Http\IResponse;
use Tracy\ILogger;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use DomainException;
use UnexpectedValueException;
use InvalidArgumentException;

/**
 * Mapper of service identification to object instance
 */
class ExternalServiceAuthenticator
{
    /** @var ExternalLogins */
    private $externalLogins;

    /** @var Users */
    private $users;

    /** @var Logins */
    private $logins;

    /** @var Instances */
    public $instances;

    /** @var EmailVerificationHelper */
    public $emailVerificationHelper;

    /** @var FailureHelper */
    public $failureHelper;

    /** @var ILogger|null */
    public $logger = null;

    /**
     * @var array [ name => { jwtSecret, expiration } ]
     */
    private $authenticators = [];

    /**
     * Constructor with instantiation of all login services
     * @param array $authenticators (each one holding 'name' and 'jwtSecret')
     * @param ExternalLogins $externalLogins
     * @param Users $users
     * @param Logins $logins
     * @param EmailVerificationHelper $emailVerificationHelper
     */
    public function __construct(
        array $authenticators,
        ExternalLogins $externalLogins,
        Users $users,
        Logins $logins,
        Instances $instances,
        EmailVerificationHelper $emailVerificationHelper,
        FailureHelper $failureHelper,
        ?ILogger $logger = null,
    ) {
        $this->externalLogins = $externalLogins;
        $this->users = $users;
        $this->logins = $logins;
        $this->instances = $instances;
        $this->emailVerificationHelper = $emailVerificationHelper;
        $this->failureHelper = $failureHelper;
        $this->logger = $logger;

        foreach ($authenticators as $auth) {
            if (!empty($auth['name'] && !empty($auth['jwtSecret']))) {
                $this->authenticators[$auth['name']] = (object)[
                    'jwtSecret' => $auth['jwtSecret'],
                    'expiration' => Arrays::get($auth, 'expiration', 60),
                    'defaultRole' => Arrays::get($auth, 'defaultRole', null),
                    // if set, users may register even when external authenticator does not provide role
                    'usedAlgorithm' => Arrays::get($auth, 'jwtAlgorithm', 'HS256'),
                    'extraIds' => Arrays::get($auth, 'extraIds', []),
                ];
            }
        }
    }

    private function log($level, $msg, ...$args)
    {
        if (!$this->logger) {
            return;
        }

        if ($args) {
            $msg = sprintf($msg, ...$args);
        }
        $this->logger->log($msg, $level);
    }

    /**
     * Verify that given authenticator exists.
     * @param string $name of the external authenticator
     * @return bool
     */
    public function hasAuthenticator(string $name): bool
    {
        return array_key_exists($name, $this->authenticators);
    }

    /**
     * Authenticate a user against given external authentication service.
     * The external identification may be paired with already existing account by email.
     * The user may be registered if no account exists, but correct instance must be determined
     * (the instanceId must be either in token, passed as argument, or only one instance in the system exists).
     * @param string $authName name of the external authenticator
     * @param string $token form the external authentication service
     * @param string|null $instanceId identifier of an instance where the user should be registered
     *                                (this may be overridden by a value in the token)
     * @return User
     * @throws BadRequestException
     * @throws WrongCredentialsException
     */
    public function authenticate(string $authName, string $token, ?string $instanceId = null): User
    {
        $user = null;
        $decodedToken = $this->decodeToken($authName, $token);

        // try to get the user by external ID
        try {
            // wrap data from token in UserData (which also performs important checks)
            $userData = new UserData($decodedToken, $this->authenticators[$authName]->defaultRole);
        } catch (InvalidArgumentException $e) {
            throw new InvalidExternalTokenException($token, $e->getMessage(), $e);
        }
        $user = $this->externalLogins->getUser($authName, $userData->getId());

        // try to match existing local user by email address
        if ($user === null) {
            $user = $this->tryConnect($authName, $userData);
            if ($user) {
                $this->log(
                    ILogger::INFO,
                    "User '%s' was paired with external ID='%s' (%s)",
                    $user->getId(),
                    $userData->getId(),
                    $authName
                );
            }
        }

        // try to register a new user
        if ($user === null) {
            $instance = $this->getInstance($decodedToken, $instanceId);
            if ($instance) {
                if ($userData->getRole() === null) {
                    throw new WrongCredentialsException(
                        "User registration failed since '$authName' was not able to provide any role.",
                        FrontendErrorMappings::E400_105__EXTERNAL_AUTH_FAILED_MISSING_ROLE,
                        ["service" => $authName]
                    );
                }

                $user = $userData->createEntity($instance);
                $this->users->persist($user);
                // connect the account to the login method
                $this->externalLogins->connect($authName, $user, $userData->getId());
                $this->emailVerificationHelper->process($user, true); // true = just created
                $this->log(
                    ILogger::INFO,
                    "User '%s' just registered via external auth '%s' (ID='%s')",
                    $user->getId(),
                    $authName,
                    $userData->getId()
                );
            }
        }

        // failures throw exceptions...
        if ($user === null) {
            throw new WrongCredentialsException(
                "User authenticated through '$authName' has no corresponding account in ReCodEx.",
                FrontendErrorMappings::E400_104__EXTERNAL_AUTH_FAILED_USER_NOT_FOUND,
                ["service" => $authName]
            );
        } elseif (!$user->isAllowed()) {
            throw new ForbiddenRequestException(
                "Forbidden Request - User account was disabled",
                IResponse::S403_Forbidden,
                FrontendErrorMappings::E403_002__USER_NOT_ALLOWED
            );
        }

        $this->handleExtraIds($user, $decodedToken, $this->authenticators[$authName]->extraIds);

        return $user;
    }

    /**
     * Attempt to decode and verify the token.
     * @param string $authName name of the external authenticator
     * @param string $token
     * @return object data of decoded token
     * @throws BadRequestException
     * @throws InvalidExternalTokenException
     */
    private function decodeToken(string $authName, string $token)
    {
        if (!$this->hasAuthenticator($authName)) {
            throw new BadRequestException("Unknown external authenticator name '$authName'.");
        }

        $authenticator = $this->authenticators[$authName];
        try {
            $decodedToken = JWT::decode($token, new Key($authenticator->jwtSecret, $authenticator->usedAlgorithm));
        } catch (DomainException $e) {
            throw new InvalidExternalTokenException($token, $e->getMessage(), $e);
        } catch (UnexpectedValueException $e) {
            throw new InvalidExternalTokenException($token, $e->getMessage(), $e);
        }

        if (empty($decodedToken->iat) || (int)$decodedToken->iat + (int)$authenticator->expiration < time()) {
            throw new InvalidExternalTokenException($token, 'Token has expired.');
        }

        return $decodedToken;
    }

    /**
     * Try connecting given external user to local ReCodEx user account by email.
     * @param string $authName
     * @param UserData $userData
     * @return User|null
     */
    private function tryConnect(string $authName, UserData $userData): ?User
    {
        $user = $this->users->getByEmail($userData->getMail());
        if ($user) {
            $this->externalLogins->connect($authName, $user, $userData->getId());
            // and also clear local account password just to be sure
            $this->logins->clearUserPassword($user);
        }
        return $user;
    }

    /**
     * Retrieve instance entity where the user should be registered
     * @param object $decodedToken which is searched for instanceId key
     * @param string $instanceId instance suggested externally
     */
    private function getInstance($decodedToken, ?string $instanceId = null): ?Instance
    {
        if (!empty($decodedToken->instanceId)) {
            $instanceId = $decodedToken->instanceId;
        }

        // fetch the entity from DB
        if ($instanceId) {
            $instance = $this->instances->get($instanceId);
            if (!$instance) {
                return null;
            }
            return $instance;
        }

        // if there is only one instance in the system, let's use it as default
        $instances = $this->instances->findAll();
        if (count($instances) === 1) {
            return $instances[0];
        }

        return null;
    }

    /**
     * Process possible additional (extra) identifiers present in the token.
     * @param User $user being authenticated
     * @param object $decodedToken
     * @param string[] $allowedServices whose extra IDs may be added from the token
     */
    private function handleExtraIds(User $user, $decodedToken, array $allowedServices)
    {
        if (empty($decodedToken->extId)) {
            return;
        }

        $this->log(ILogger::DEBUG, "User '%s' got extra IDs: %s", $user->getId(), json_encode($decodedToken->extId));

        foreach ($decodedToken->extId as $service => $eid) {
            if (!in_array($service, $allowedServices)) {
                $this->log(
                    ILogger::DEBUG,
                    "User '%s' got new [%s] ID='%s', but this auth service is not allowed",
                    $user->getId(),
                    $service,
                    $eid
                );

                continue;  // skip services that are not allowed
            }

            $extUser = $this->externalLogins->getUser($service, $eid);
            if ($extUser) {  // a user with given ID exists
                if ($extUser->getId() !== $user->getId()) {
                    // Identity crisis! ID belongs to another user...
                    $this->failureHelper->report(
                        FailureHelper::TYPE_API_ERROR,
                        sprintf(
                            "User '%s' was provided with extra ID '%s' (%s), "
                                . "but that is already associated with user '%s'.",
                            $user->getId(),
                            $eid,
                            $service,
                            $extUser->getId()
                        )
                    );
                }

                continue; // either already exist or we cannot proceed anyway
            }

            $login = $this->externalLogins->findByUser($user, $service);
            if ($login) { // a connected external login record for the auth service already exist
                if ($login->getExternalId() !== $eid) {
                    // extra ID has changed (strange, but possible)
                    $this->log(
                        ILogger::INFO,
                        "User '%s' got new [%s] ID='%s', but already had different ID='%s'",
                        $user->getId(),
                        $service,
                        $eid,
                        $login->getExternalId()
                    );
                    $login->setExternalId($eid);
                    $this->externalLogins->persist($login);
                }

                continue; // either already exist or was duly updated
            }

            $this->externalLogins->connect($service, $user, $eid);
            $this->log(ILogger::INFO, "User '%s' got new extra ID='%s' [%s]", $user->getId(), $eid, $service);
        }
    }
}
