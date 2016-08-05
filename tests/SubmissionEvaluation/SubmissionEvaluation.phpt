<?php

include '../bootstrap.php';

use Tester\Assert;

class TestSubmissionEvaluation extends Tester\TestCase
{
    public function testOne() {
        Assert::same("a", "b");
    }

    public function testTwo() {
        Assert::same("a", "a");
    }

}

# Testing methods run
$testCase = new TestSubmissionEvaluation;
$testCase->run();
