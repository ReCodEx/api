<?php

use App\Exceptions\BadRequestException;
use App\Exceptions\WrongCredentialsException;
use App\Helpers\ExternalLogin\ExternalServiceAuthenticator;
use App\Helpers\ExternalLogin\UserData;
use App\Model\Entity\ExternalLogin;
use App\Model\Entity\Instance;
use App\Model\Entity\User;
use App\Model\Repository\ExternalLogins;
use App\Model\Repository\Logins;
use App\Model\Repository\Users;
use Tester\Assert;

include "../bootstrap.php";


/**
 * @testCase
 */
class ExternalServiceAuthenticatorTestCase extends Tester\TestCase
{

    /** @var  Nette\DI\Container */
    protected $container;

    public function __construct()
    {
        global $container;
        $this->container = $container;
    }

    // TODO
}

$case = new ExternalServiceAuthenticatorTestCase();
$case->run();
