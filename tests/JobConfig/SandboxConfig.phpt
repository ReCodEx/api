<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Helpers\JobConfig\Loader;
use App\Helpers\JobConfig\SandboxConfig;
use App\Helpers\JobConfig\Limits;
use App\Helpers\JobConfig\UndefinedLimits;
use App\Exceptions\JobConfigLoadingException;

class TestSandboxConfig extends Tester\TestCase
{
  static $cfg = [
    "name" => "sandboxName",
    "limits" => [
      [ "hw-group-id" => "idA", "bound-directories" => [] ],
      [ "hw-group-id" => "idB", "bound-directories" => [] ]
    ]
  ];
  static $optional = [
    "name" => "optional",
    "stdin" => "optStdin",
    "stdout" => "optStdout",
    "stderr" => "optStderr",
    "output" => true,
    "chdir" => "/change/dir",
    "limits" => [
      [ "hw-group-id" => "optionalGroup", "bound-directories" => [] ]
    ]
  ];

  /** @var Loader */
  private $builder;

  public function __construct() {
    $this->builder = new Loader();
  }

  public function testMissingSandboxName() {
    Assert::exception(function () {
      $data = self::$cfg;
      unset($data["name"]);
      $this->builder->loadSandboxConfig($data);
    }, JobConfigLoadingException::class);
  }

  public function testLimitsIsNotArray() {
    Assert::exception(function () {
      $data = self::$cfg;
      unset($data["limits"]);
      $data["limits"] = "hello";
      $this->builder->loadSandboxConfig($data);
    }, JobConfigLoadingException::class);
  }

  public function testParsing() {
    $sandbox = $this->builder->loadSandboxConfig(self::$cfg);
    Assert::equal("sandboxName", $sandbox->getName());
    Assert::equal(null, $sandbox->getStdin());
    Assert::equal(null, $sandbox->getStdout());
    Assert::equal(null, $sandbox->getStderr());
    Assert::equal(2, count($sandbox->getLimitsArray()));
    Assert::true($sandbox->hasLimits("idA"));
    Assert::true($sandbox->hasLimits("idB"));
    Assert::equal(self::$cfg, $sandbox->toArray());
  }

  public function testOptional() {
    $sandbox = $this->builder->loadSandboxConfig(self::$optional);
    Assert::equal("optional", $sandbox->getName());
    Assert::equal("optStdin", $sandbox->getStdin());
    Assert::equal("optStdout", $sandbox->getStdout());
    Assert::equal("optStderr", $sandbox->getStderr());
    Assert::equal(true, $sandbox->getOutput());
    Assert::equal("/change/dir", $sandbox->getChdir());
    Assert::equal(1, count($sandbox->getLimitsArray()));
    Assert::true($sandbox->hasLimits("optionalGroup"));
    Assert::equal(self::$optional, $sandbox->toArray());
  }

  public function testHasLimits() {
    $sandbox = $this->builder->loadSandboxConfig(self::$cfg);
    Assert::true($sandbox->hasLimits("idA"));
    Assert::true($sandbox->hasLimits("idB"));
    Assert::false($sandbox->hasLimits("nonExistingGroup"));
  }

  public function testGetLimits() {
    $sandbox = $this->builder->loadSandboxConfig(self::$cfg);
    Assert::true($sandbox->hasLimits("idA"));
    Assert::type(Limits::class, $sandbox->getLimits("idA"));
    Assert::equal("idA", $sandbox->getLimits("idA")->getId());

    Assert::true($sandbox->hasLimits("idB"));
    Assert::type(Limits::class, $sandbox->getLimits("idB"));
    Assert::equal("idB", $sandbox->getLimits("idB")->getId());

    Assert::null($sandbox->getLimits("nonExistingGroup"));
  }

  public function testSetLimits() {
    $limits = $this->builder->loadLimits([ "hw-group-id" => "newGroup" ]);
    $sandbox = $this->builder->loadSandboxConfig(self::$cfg);
    $sandbox->setLimits($limits);

    Assert::type(Limits::class, $sandbox->getLimits("newGroup"));
    Assert::true($sandbox->hasLimits("newGroup"));
    Assert::equal("newGroup", $sandbox->getLimits("newGroup")->getId());
  }

  public function testReplaceLimits() {
    $limits = $this->builder->loadLimits([ "hw-group-id" => "idA", "time" => "25.5" ]);
    $sandbox = $this->builder->loadSandboxConfig(self::$cfg);

    Assert::true($sandbox->hasLimits("idA"));
    Assert::equal(0.0, $sandbox->getLimits("idA")->getTimeLimit());

    $sandbox->setLimits($limits);
    Assert::true($sandbox->hasLimits("idA"));
    Assert::equal(25.5, $sandbox->getLimits("idA")->getTimeLimit());
  }

  public function testRemoveLimits() {
    $sandbox = $this->builder->loadSandboxConfig(self::$cfg);
    Assert::true($sandbox->hasLimits("idA"));

    $sandbox->removeLimits("idA");
    Assert::true($sandbox->hasLimits("idA"));
    Assert::type(UndefinedLimits::class, $sandbox->getLimits("idA"));
  }

}

# Testing methods run
$testCase = new TestSandboxConfig();
$testCase->run();
