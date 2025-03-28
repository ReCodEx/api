<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Exceptions\BadRequestException;
use App\Exceptions\CASMissingInfoException;
use App\Exceptions\CannotReceiveUploadedFileException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\HttpBasicAuthException;
use App\Exceptions\InternalServerException;
use App\Exceptions\InvalidAccessTokenException;
use App\Exceptions\InvalidApiArgumentException;
use App\Exceptions\InvalidMembershipException;
use App\Exceptions\InvalidStateException;
use App\Exceptions\JobConfigLoadingException;
use App\Exceptions\LdapConnectException;
use App\Exceptions\NoAccessTokenException;
use App\Exceptions\NotImplementedException;
use App\Exceptions\NotReadyException;
use App\Exceptions\ResultsLoadingException;
use App\Exceptions\SubmissionEvaluationFailedException;
use App\Exceptions\SubmissionFailedException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\UploadedFileException;
use App\Exceptions\WrongCredentialsException;
use App\Exceptions\WrongHttpMethodException;


/**
 * @testCase
 */
class TestExceptions extends Tester\TestCase
{
    public function testBadRequestException()
    {
        Assert::exception(
            function () {
                try {
                    throw new BadRequestException("message");
                } catch (Exception $e) {
                    Assert::true(strlen($e->getMessage()) > 0);
                    throw $e;
                }
            },
            BadRequestException::class
        );
    }

    public function testCASMissingInfoException()
    {
        Assert::exception(
            function () {
                try {
                    throw new CASMissingInfoException("message");
                } catch (Exception $e) {
                    Assert::true(strlen($e->getMessage()) > 0);
                    throw $e;
                }
            },
            CASMissingInfoException::class
        );
    }

    public function testCannotReceiveUploadedFileException()
    {
        Assert::exception(
            function () {
                try {
                    throw new CannotReceiveUploadedFileException("message", -1);
                } catch (Exception $e) {
                    Assert::true(strlen($e->getMessage()) > 0);
                    throw $e;
                }
            },
            CannotReceiveUploadedFileException::class
        );
    }

    public function testForbiddenRequestException()
    {
        Assert::exception(
            function () {
                try {
                    throw new ForbiddenRequestException("message");
                } catch (Exception $e) {
                    Assert::true(strlen($e->getMessage()) > 0);
                    throw $e;
                }
            },
            ForbiddenRequestException::class
        );
    }

    public function testHttpBasicAuthException()
    {
        Assert::exception(
            function () {
                try {
                    throw new HttpBasicAuthException("message");
                } catch (Exception $e) {
                    Assert::true(strlen($e->getMessage()) > 0);
                    Assert::count(1, $e->getAdditionalHttpHeaders());
                    throw $e;
                }
            },
            HttpBasicAuthException::class
        );
    }

    public function testInternalServerErrorException()
    {
        Assert::exception(
            function () {
                try {
                    throw new InternalServerException("message");
                } catch (Exception $e) {
                    Assert::true(strlen($e->getMessage()) > 0);
                    throw $e;
                }
            },
            InternalServerException::class
        );
    }

    public function testInvalidAccessTokenException()
    {
        Assert::exception(
            function () {
                try {
                    throw new InvalidAccessTokenException("message");
                } catch (Exception $e) {
                    Assert::true(strlen($e->getMessage()) > 0);
                    Assert::count(1, $e->getAdditionalHttpHeaders());
                    throw $e;
                }
            },
            InvalidAccessTokenException::class
        );
    }

    public function testInvalidApiArgumentException()
    {
        Assert::exception(
            function () {
                try {
                    throw new InvalidApiArgumentException("message");
                } catch (Exception $e) {
                    Assert::true(strlen($e->getMessage()) > 0);
                    throw $e;
                }
            },
            InvalidApiArgumentException::class
        );
    }

    public function testInvalidMembershipException()
    {
        Assert::exception(
            function () {
                try {
                    throw new InvalidMembershipException("message");
                } catch (Exception $e) {
                    Assert::true(strlen($e->getMessage()) > 0);
                    throw $e;
                }
            },
            InvalidMembershipException::class
        );
    }

    public function testInvalidStateException()
    {
        Assert::exception(
            function () {
                try {
                    throw new InvalidStateException("message");
                } catch (Exception $e) {
                    Assert::true(strlen($e->getMessage()) > 0);
                    throw $e;
                }
            },
            InvalidStateException::class
        );
    }

    public function testJobConfigLoadingException()
    {
        Assert::exception(
            function () {
                try {
                    throw new JobConfigLoadingException("message");
                } catch (Exception $e) {
                    Assert::true(strlen($e->getMessage()) > 0);
                    throw $e;
                }
            },
            JobConfigLoadingException::class
        );
    }

    public function testLdapConnectException()
    {
        Assert::exception(
            function () {
                try {
                    throw new LdapConnectException("message");
                } catch (Exception $e) {
                    Assert::true(strlen($e->getMessage()) > 0);
                    throw $e;
                }
            },
            LdapConnectException::class
        );
    }

    public function testNoAccessTokenException()
    {
        Assert::exception(
            function () {
                try {
                    throw new NoAccessTokenException("message");
                } catch (Exception $e) {
                    Assert::true(strlen($e->getMessage()) > 0);
                    Assert::count(1, $e->getAdditionalHttpHeaders());
                    throw $e;
                }
            },
            NoAccessTokenException::class
        );
    }

    public function testNotImplementedException()
    {
        Assert::exception(
            function () {
                try {
                    throw new NotImplementedException("message");
                } catch (Exception $e) {
                    Assert::true(strlen($e->getMessage()) > 0);
                    throw $e;
                }
            },
            NotImplementedException::class
        );
    }

    public function testNotReadyException()
    {
        Assert::exception(
            function () {
                try {
                    throw new NotReadyException("message");
                } catch (Exception $e) {
                    Assert::true(strlen($e->getMessage()) > 0);
                    throw $e;
                }
            },
            NotReadyException::class
        );
    }

    public function testResultsLoadingException()
    {
        Assert::exception(
            function () {
                try {
                    throw new ResultsLoadingException("message");
                } catch (Exception $e) {
                    Assert::true(strlen($e->getMessage()) > 0);
                    throw $e;
                }
            },
            ResultsLoadingException::class
        );
    }

    public function testSubmissionEvaluationFailedException()
    {
        Assert::exception(
            function () {
                try {
                    throw new SubmissionEvaluationFailedException("message");
                } catch (Exception $e) {
                    Assert::true(strlen($e->getMessage()) > 0);
                    throw $e;
                }
            },
            SubmissionEvaluationFailedException::class
        );
    }

    public function testSubmissionFailedException()
    {
        Assert::exception(
            function () {
                try {
                    throw new SubmissionFailedException("message");
                } catch (Exception $e) {
                    Assert::true(strlen($e->getMessage()) > 0);
                    throw $e;
                }
            },
            SubmissionFailedException::class
        );
    }

    public function testUnauthorizedException()
    {
        Assert::exception(
            function () {
                try {
                    throw new UnauthorizedException("message");
                } catch (Exception $e) {
                    Assert::true(strlen($e->getMessage()) > 0);
                    Assert::count(1, $e->getAdditionalHttpHeaders());
                    throw $e;
                }
            },
            UnauthorizedException::class
        );
    }

    public function testUploadedFileException()
    {
        Assert::exception(
            function () {
                try {
                    throw new UploadedFileException("message");
                } catch (Exception $e) {
                    Assert::true(strlen($e->getMessage()) > 0);
                    throw $e;
                }
            },
            UploadedFileException::class
        );
    }

    public function testWrongCredentialsException()
    {
        Assert::exception(
            function () {
                try {
                    throw new WrongCredentialsException("message");
                } catch (Exception $e) {
                    Assert::true(strlen($e->getMessage()) > 0);
                    Assert::count(1, $e->getAdditionalHttpHeaders());
                    throw $e;
                }
            },
            WrongCredentialsException::class
        );
    }

    public function testWrongHttpMethodException()
    {
        Assert::exception(
            function () {
                try {
                    throw new WrongHttpMethodException("message");
                } catch (Exception $e) {
                    Assert::true(strlen($e->getMessage()) > 0);
                    throw $e;
                }
            },
            WrongHttpMethodException::class
        );
    }
}

$testCase = new TestExceptions();
$testCase->run();
