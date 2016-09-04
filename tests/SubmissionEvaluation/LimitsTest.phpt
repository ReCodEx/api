<?php

include '../bootstrap.php';

use Tester\Assert;
use App\Helpers\JobConfig\Limits;

class LimitsTest extends Tester\TestCase
{

  public function testHardwareGroupId() {
    $limits = new Limits([ "hw-group-id" => "XYZ_abc" ]);
    Assert::equal("XYZ_abc", $limits->getId());
  }

}

# Testing methods run
$testCase = new LimitsTest;
$testCase->run();
