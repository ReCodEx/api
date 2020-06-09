<?php

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/TestResultMock.php';

use Tester\Assert;
use App\Helpers\Evaluation\UniversalScoreCalculator;
use App\Exceptions\SubmissionEvaluationFailedException;
/**
 * @testCase
 */
class TestUniversalScoreCalculator extends Tester\TestCase
{
    /**
     * Used for construction of universal score configs.
     * Name of the static function is transcribed to node type, arguments become children.
     * String arguments are converted into test-result nodes, numbers into literals.
     */
    public static function __callStatic($name, $arguments)
    {
        $children = [];
        foreach ($arguments as $arg) {
            if (is_string($arg)) {
                $children[] = [
                    'type' => 'test-result',
                    'test' => $arg,
                ];
            } else {
                $children[] = is_numeric($arg) ? (float)$arg : $arg;
            }
        }

        $res = [ 'type' => $name ];
        if ($children) {
            $res['children'] = $children;
        }
        return $res;
    }


    private static function randomStr($len)
    {
        return bin2hex(random_bytes($len));
    }

    /**
     * Generate random config parameters (with given prefix) and insert them into config structure (deeply).
     */
    private static function addDataExtensions(array &$config, $prefix = 'x-')
    {
        $count = rand(1, 3);
        for ($i = 0; $i < $count; ++$i) {
            $key = $prefix . self::randomStr(3);
            $value = self::randomStr(3);
            $config[$key] = $value;
        }

        if (array_key_exists('children', $config)) {
            foreach ($config['children'] as &$child) {
                if (is_array($child)) {
                    self::addDataExtensions($child, $prefix);
                }
            }
            unset($child);
        }
    }

    /**
     * Helper function that asserts two config structures for equality.
     * (i.e., it has the same semantics as Assert::equal)
     */
    private static function compareConfigs($expected, $actual)
    {
        Assert::equal(array_key_exists('children', $expected), array_key_exists('children', $actual));
        if (array_key_exists('children', $expected)) {
            $expectedChildren = $expected['children'];
            $actualChildren = $actual['children'];
            Assert::equal(count($expectedChildren), count($actualChildren));
            
            foreach ($expectedChildren as $key => $expectedChild) {
                $actualChild = $actualChildren[$key];
                if (is_array($actualChild) && is_array($expectedChild)) {
                    self::compareConfigs($expectedChild, $actualChild);
                } else {
                    Assert::equal($expectedChild, $actualChild);
                }
            }

            unset($expected['children']);
            unset($actual['children']);
        }

        Assert::equal($expected, $actual);
    }

    /**
     * Create sample configuration which uses most of function nodes.
     */
    private function getTestConfig()
    {
        return self::clamp(self::avg(
            self::max("a", "b", "c"),
            self::min("c", "d", "e"),
            self::div(
                self::sum(
                    self::sub(1, "a"),
                    self::mul(2, "b"),
                    self::mul(3, "d")
                ),
                self::neg(-6)
            )
        ));
    }

    /**
     * Convert associative array (test name => score) into test results mock.
     */
    private function prepareResults(array $data)
    {
        $results = [];
        foreach ($data as $name => $result) {
            $results[$name] = new TestResultMock($result);
        }
        return $results;
    }

    private function getCalc()
    {
        return new UniversalScoreCalculator();
    }

    /**
     * Instantiate the calculator and perform score computation (using sample config if not provided).
     */
    private function computeScore(array $results, array $config = null)
    {
        if ($config === null) {
            $config = $this->getTestConfig();
        }
        $results = $this->prepareResults($results);
        return $this->getCalc()->computeScore($config, $results);
    }

    public function testEvaluation1()
    {
        $res = $this->computeScore(["a" => 1.0, "b" => 1.0, "c" => 1.0, "d" => 1.0, "e" => 1.0]);
        Assert::equal(17.0 / 18.0, $res);
    }

    public function testEvaluation2()
    {
        $res = $this->computeScore(["a" => 0.0, "b" => 0.0, "c" => 0.0, "d" => 0.0, "e" => 0.0]);
        Assert::equal(1.0 / 18.0, $res);
    }

    public function testEvaluation3()
    {
        $res = $this->computeScore(["a" => 1.0, "b" => 0.0, "c" => 1.0, "d" => 0.0, "e" => 1.0]);
        Assert::equal(1.0 / 3.0, $res);
    }

    public function testEvaluation4()
    {
        $res = $this->computeScore(["a" => 0.0, "b" => 1.0, "c" => 0.0, "d" => 1.0, "e" => 0.0]);
        Assert::equal(2.0 / 3.0, $res);
    }

    public function testValidateConfig()
    {
        Assert::true($this->getCalc()->isScoreConfigValid($this->getTestConfig(), ["a", "b", "c", "d", "e"]));
    }

    public function _testBadConfig($config)
    {
        $calc = $this->getCalc();
        Assert::false($calc->isScoreConfigValid($config));
        Assert::exception(
            function () use ($calc, $config) {
                $calc->computeScore($config, ["a" => 1.0, "b" => 1.0, "c" => 1.0, "d" => 1.0, "e" => 1.0]);
            },
            SubmissionEvaluationFailedException::class
        );
    }

    public function testMalformedNodeMissingType()
    {
        $this->_testBadConfig(
            [ 'children' => [ $this->getTestConfig() ]]
        );
    }

    public function testMalformedNodeNullChild()
    {
        $this->_testBadConfig(self::clamp(null));
    }

    public function testInvalidFunctionName()
    {
        $this->_testBadConfig(self::foo(0.0));
    }

    public function testMissingChildren()
    {
        $this->_testBadConfig(self::sum());
    }

    public function testInvalidNumberOfChildrenUnary()
    {
        $this->_testBadConfig(self::neg(1.0, 0.0));
    }

    public function testInvalidNumberOfChildrenBinary()
    { 
        $this->_testBadConfig(self::sub(1, 2, 3));
    }

    public function testInvalidNumberOfChildrenLeaf()
    {
        $this->_testBadConfig( [ 'type' => 'test-result', 'children' => [ self::sum(1, 2, 3) ]] );
    }

    public function testUnknownReference()
    {
        $calc = $this->getCalc();
        $config = $this->getTestConfig();
        Assert::false($calc->isScoreConfigValid($config, ["a", "b", "c", "d"]));
        Assert::exception(
            function () use ($calc, $config) {
                $results = $this->prepareResults(["a" => 1.0, "b" => 1.0, "c" => 1.0, "d" => 1.0]);
                $calc->computeScore($config, $results);
            },
            SubmissionEvaluationFailedException::class
        );
    }

    public function testNormalization()
    {
        $config = $this->getTestConfig();
        self::addDataExtensions($config);
        $normalized = $this->getCalc()->validateAndNormalizeScore($config);
        self::compareConfigs($config, $normalized);
    }

    public function testNormalizationWithUnkonwParams()
    {
        $config = $this->getTestConfig();
        self::addDataExtensions($config);

        $pollutedConfig = $config; // polluted config will contain some foo-xxx params, which will be removed
        self::addDataExtensions($pollutedConfig, 'foo-');

        $normalized = $this->getCalc()->validateAndNormalizeScore($pollutedConfig);
        self::compareConfigs($config, $normalized);
    }

    public function testDefaultConfig()
    {
        $testNames = ["A test", "B", "test C", "Test D"];
        $config = $this->getCalc()->getDefaultConfig($testNames);
        $correct = self::clamp(self::avg(...$testNames));
        Assert::equal($correct, $config);
    }

    public function testEmptyDefaultConfig()
    {
        $config = $this->getCalc()->getDefaultConfig([]);
        Assert::equal(['type' => 'value', 'value' => 1.0], $config);
    }
}

$testCase = new TestUniversalScoreCalculator();
$testCase->run();
