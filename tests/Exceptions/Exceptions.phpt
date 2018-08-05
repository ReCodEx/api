<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Exceptions\BadRequestException;
use App\Exceptions\CASMissingInfoException;
use App\Exceptions\CannotReceiveUploadedFileException;
use App\Exceptions\ForbiddenRequestException;
use App\Exceptions\HttpBasicAuthException;
use App\Exceptions\InternalServerErrorException;
use App\Exceptions\InvalidAccessTokenException;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\InvalidMembershipException;
use App\Exceptions\InvalidStateException;
use App\Exceptions\JobConfigLoadingException;
use App\Exceptions\JobConfigStorageException;
use App\Exceptions\LdapConnectException;
use App\Exceptions\MalformedJobConfigException;
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
  public function testBadRequestException() {
    Assert::exception(function() {
      try {
        throw new BadRequestException("message");
      } catch (\Exception $e) {
        Assert::true(strlen($e->getMessage()) > 0);
        throw $e;
      }
    }, BadRequestException::CLASS);
  }

  public function testCASMissingInfoException() {
    Assert::exception(function() {
      try {
        throw new CASMissingInfoException("message");
      } catch (\Exception $e) {
        Assert::true(strlen($e->getMessage()) > 0);
        throw $e;
      }
    }, CASMissingInfoException::CLASS);
  }

  public function testCannotReceiveUploadedFileException() {
    Assert::exception(function() {
      try {
        throw new CannotReceiveUploadedFileException("message");
      } catch (\Exception $e) {
        Assert::true(strlen($e->getMessage()) > 0);
        throw $e;
      }
    }, CannotReceiveUploadedFileException::CLASS);
  }

  public function testForbiddenRequestException() {
    Assert::exception(function() {
      try {
        throw new ForbiddenRequestException("message");
      } catch (\Exception $e) {
        Assert::true(strlen($e->getMessage()) > 0);
        throw $e;
      }
    }, ForbiddenRequestException::CLASS);
  }

  public function testHttpBasicAuthException() {
    Assert::exception(function() {
      try {
        throw new HttpBasicAuthException("message");
      } catch (\Exception $e) {
        Assert::true(strlen($e->getMessage()) > 0);
        Assert::count(1, $e->getAdditionalHttpHeaders());
        throw $e;
      }
    }, HttpBasicAuthException::CLASS);
  }

  public function testInternalServerErrorException() {
    Assert::exception(function() {
      try {
        throw new InternalServerErrorException("message");
      } catch (\Exception $e) {
        Assert::true(strlen($e->getMessage()) > 0);
        throw $e;
      }
    }, InternalServerErrorException::CLASS);
  }

  public function testInvalidAccessTokenException() {
    Assert::exception(function() {
      try {
        throw new InvalidAccessTokenException("message");
      } catch (\Exception $e) {
        Assert::true(strlen($e->getMessage()) > 0);
        Assert::count(1, $e->getAdditionalHttpHeaders());
        throw $e;
      }
    }, InvalidAccessTokenException::CLASS);
  }

  public function testInvalidArgumentException() {
    Assert::exception(function() {
      try {
        throw new InvalidArgumentException("message");
      } catch (\Exception $e) {
        Assert::true(strlen($e->getMessage()) > 0);
        throw $e;
      }
    }, InvalidArgumentException::CLASS);
  }

  public function testInvalidMembershipException() {
    Assert::exception(function() {
      try {
        throw new InvalidMembershipException("message");
      } catch (\Exception $e) {
        Assert::true(strlen($e->getMessage()) > 0);
        throw $e;
      }
    }, InvalidMembershipException::CLASS);
  }

  public function testInvalidStateException() {
    Assert::exception(function() {
      try {
        throw new InvalidStateException("message");
      } catch (\Exception $e) {
        Assert::true(strlen($e->getMessage()) > 0);
        throw $e;
      }
    }, InvalidStateException::CLASS);
  }

  public function testJobConfigLoadingException() {
    Assert::exception(function() {
      try {
        throw new JobConfigLoadingException("message");
      } catch (\Exception $e) {
        Assert::true(strlen($e->getMessage()) > 0);
        throw $e;
      }
    }, JobConfigLoadingException::CLASS);
  }

  public function testJobConfigStorageException() {
    Assert::exception(function() {
      try {
        throw new JobConfigStorageException("message");
      } catch (\Exception $e) {
        Assert::true(strlen($e->getMessage()) > 0);
        throw $e;
      }
    }, JobConfigStorageException::CLASS);
  }

  public function testLdapConnectException() {
    Assert::exception(function() {
      try {
        throw new LdapConnectException("message");
      } catch (\Exception $e) {
        Assert::true(strlen($e->getMessage()) > 0);
        throw $e;
      }
    }, LdapConnectException::CLASS);
  }

  public function testMalformedJobConfigException() {
    Assert::exception(function() {
      try {
        throw new MalformedJobConfigException("message");
      } catch (\Exception $e) {
        Assert::true(strlen($e->getMessage()) > 0);
        throw $e;
      }
    }, MalformedJobConfigException::CLASS);
  }

  public function testNoAccessTokenException() {
    Assert::exception(function() {
      try {
        throw new NoAccessTokenException("message");
      } catch (\Exception $e) {
        Assert::true(strlen($e->getMessage()) > 0);
        Assert::count(1, $e->getAdditionalHttpHeaders());
        throw $e;
      }
    }, NoAccessTokenException::CLASS);
  }

  public function testNotImplementedException() {
    Assert::exception(function() {
      try {
        throw new NotImplementedException("message");
      } catch (\Exception $e) {
        Assert::true(strlen($e->getMessage()) > 0);
        throw $e;
      }
    }, NotImplementedException::CLASS);
  }

  public function testNotReadyException() {
    Assert::exception(function() {
      try {
        throw new NotReadyException("message");
      } catch (\Exception $e) {
        Assert::true(strlen($e->getMessage()) > 0);
        throw $e;
      }
    }, NotReadyException::CLASS);
  }

  public function testResultsLoadingException() {
    Assert::exception(function() {
      try {
        throw new ResultsLoadingException("message");
      } catch (\Exception $e) {
        Assert::true(strlen($e->getMessage()) > 0);
        throw $e;
      }
    }, ResultsLoadingException::CLASS);
  }

  public function testSubmissionEvaluationFailedException() {
    Assert::exception(function() {
      try {
        throw new SubmissionEvaluationFailedException("message");
      } catch (\Exception $e) {
        Assert::true(strlen($e->getMessage()) > 0);
        throw $e;
      }
    }, SubmissionEvaluationFailedException::CLASS);
  }

  public function testSubmissionFailedException() {
    Assert::exception(function() {
      try {
        throw new SubmissionFailedException("message");
      } catch (\Exception $e) {
        Assert::true(strlen($e->getMessage()) > 0);
        throw $e;
      }
    }, SubmissionFailedException::CLASS);
  }

  public function testUnauthorizedException() {
    Assert::exception(function() {
      try {
        throw new UnauthorizedException("message");
      } catch (\Exception $e) {
        Assert::true(strlen($e->getMessage()) > 0);
        Assert::count(1, $e->getAdditionalHttpHeaders());
        throw $e;
      }
    }, UnauthorizedException::CLASS);
  }

  public function testUploadedFileException() {
    Assert::exception(function() {
      try {
        throw new UploadedFileException("message");
      } catch (\Exception $e) {
        Assert::true(strlen($e->getMessage()) > 0);
        throw $e;
      }
    }, UploadedFileException::CLASS);
  }

  public function testWrongCredentialsException() {
    Assert::exception(function() {
      try {
        throw new WrongCredentialsException("message");
      } catch (\Exception $e) {
        Assert::true(strlen($e->getMessage()) > 0);
        Assert::count(1, $e->getAdditionalHttpHeaders());
        throw $e;
      }
    }, WrongCredentialsException::CLASS);
  }

  public function testWrongHttpMethodException() {
    Assert::exception(function() {
      try {
        throw new WrongHttpMethodException("message");
      } catch (\Exception $e) {
        Assert::true(strlen($e->getMessage()) > 0);
        throw $e;
      }
    }, WrongHttpMethodException::CLASS);
  }

}

$testCase = new TestExceptions();
$testCase->run();
