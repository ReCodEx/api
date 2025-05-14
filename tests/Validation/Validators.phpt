<?php

use App\Helpers\MetaFormats\FormatDefinitions\UserFormat;
use App\Helpers\MetaFormats\Validators\BaseValidator;
use App\Helpers\MetaFormats\Validators\VArray;
use App\Helpers\MetaFormats\Validators\VBool;
use App\Helpers\MetaFormats\Validators\VDouble;
use App\Helpers\MetaFormats\Validators\VInt;
use App\Helpers\MetaFormats\Validators\VMixed;
use App\Helpers\MetaFormats\Validators\VObject;
use App\Helpers\MetaFormats\Validators\VString;
use App\Helpers\MetaFormats\Validators\VTimestamp;
use App\Helpers\MetaFormats\Validators\VUuid;
use Tester\Assert;

$container = require_once __DIR__ . "/../bootstrap.php";

/**
 * @testCase
 */
class TestValidators extends Tester\TestCase
{
    /** @var  Nette\DI\Container */
    protected $container;

    public function __construct()
    {
        global $container;
        $this->container = $container;
    }

    private static function getAssertionFailedMessage(
        BaseValidator $validator,
        mixed $value,
        bool $expectedValid,
        bool $strict
    ): string {
        $classTokens = explode("\\", get_class($validator));
        $class = $classTokens[array_key_last($classTokens)];
        $strictString = $strict ? "strict" : "permissive";
        $expectedString = $expectedValid ? "valid" : "invalid";
        $valueString = json_encode($value);
        return "Asserts that the value <$valueString> using $strictString validator <$class> is $expectedString";
    }

    private static function assertAllValid(BaseValidator $validator, array $values, bool $strict)
    {
        foreach ($values as $value) {
            $failMessage = self::getAssertionFailedMessage($validator, $value, true, $strict);
            Assert::true($validator->validate($value), $failMessage);
        }
    }

    private static function assertAllInvalid(BaseValidator $validator, array $values, bool $strict)
    {
        foreach ($values as $value) {
            $failMessage = self::getAssertionFailedMessage($validator, $value, false, $strict);
            Assert::false($validator->validate($value), $failMessage);
        }
    }

    /**
     * Test a validator against a set of input values. The strictness mode is set automatically by the method.
     * @param App\Helpers\MetaFormats\Validators\BaseValidator $validator The validator to be tested.
     * @param array $strictValid Valid values in the strict mode.
     * @param array $strictInvalid Invalid values in the strict mode.
     * @param array $permissiveValid Valid values in the permissive mode.
     * @param array $permissiveInvalid Invalid values in the permissive mode.
     */
    private static function validatorTester(
        BaseValidator $validator,
        array $strictValid,
        array $strictInvalid,
        array $permissiveValid,
        array $permissiveInvalid
    ): void {
        // test strict
        $validator->setStrict(true);
        self::assertAllValid($validator, $strictValid, true);
        self::assertAllInvalid($validator, $strictInvalid, true);
        // all invalid values in the permissive mode have to be invalid in the strict mode
        self::assertAllInvalid($validator, $permissiveInvalid, true);

        // test permissive
        $validator->setStrict(false);
        self::assertAllValid($validator, $permissiveValid, false);
        self::assertAllInvalid($validator, $permissiveInvalid, false);
        // all valid values in the strict mode have to be valid in the permissive mode
        self::assertAllValid($validator, $strictValid, false);
    }

    public function testVBool()
    {
        $validator = new VBool();
        $strictValid = [true, false];
        $strictInvalid = [0, 1, -1, [], "0", "1", "true", "false", "", "text"];
        $permissiveValid = [true, false, 0, 1, "0", "1", "true", "false"];
        $permissiveInvalid = [-1, [], "", "text"];
        self::validatorTester($validator, $strictValid, $strictInvalid, $permissiveValid, $permissiveInvalid);
    }

    public function testVInt()
    {
        $validator = new VInt();
        $strictValid = [0, 1, -1];
        $strictInvalid = [0.0, 2.5, "0", "1", "-1", "0.0", "", false, []];
        $permissiveValid = [0, 1, -1, 0.0, "0", "1", "-1", "0.0"];
        $permissiveInvalid = ["", 2.5, false, []];
        self::validatorTester($validator, $strictValid, $strictInvalid, $permissiveValid, $permissiveInvalid);
    }

    public function testVTimestamp()
    {
        // timestamps are just ints (unix timestamps, timestamps can be negative)
        $validator = new VTimestamp();
        $strictValid = [0, 1, -1];
        $strictInvalid = [0.0, 2.5, "0", "1", "-1", "0.0", "", false, []];
        $permissiveValid = [0, 1, -1, 0.0, "0", "1", "-1", "0.0"];
        $permissiveInvalid = ["", 2.5, false, []];
        self::validatorTester($validator, $strictValid, $strictInvalid, $permissiveValid, $permissiveInvalid);
    }

    public function testVDouble()
    {
        $validator = new VDouble();
        $strictValid = [0, 1, -1, 0.0, 2.5];
        $strictInvalid = ["0", "1", "-1", "0.0", "2.5", "", false, []];
        $permissiveValid = [0, 1, -1, 0.0, 2.5, "0", "1", "-1", "0.0", "2.5"];
        $permissiveInvalid = ["", false, []];
        self::validatorTester($validator, $strictValid, $strictInvalid, $permissiveValid, $permissiveInvalid);
    }

    public function testVArrayShallow()
    {
        // no nested validators, strictness has no effect
        $validator = new VArray();
        $valid = [[], [[]], [0], [[], 0]];
        $invalid = ["[]", 0, false, ""];
        self::validatorTester($validator, $valid, $invalid, $valid, $invalid);
    }

    public function testVArrayNested()
    {
        // nested array validator, strictness has no effect
        $validator = new VArray(new VArray());
        $valid = [[[]], []]; // an array without any nested arrays is still valid (it just has 0 elements)
        $invalid = [[0], [[], 0], "[]", 0, false, ""];
        self::validatorTester($validator, $valid, $invalid, $valid, $invalid);
    }

    public function testVArrayNestedInt()
    {
        // nested int validator, strictness affects int validation
        $validator = new VArray(new VInt());
        $strictValid = [[], [0]];
        $strictInvalid = [["0"], [0.0], [[]], [[], 0], "[]", 0, false, ""];
        $permissiveValid = [[], [0], ["0"], [0.0]];
        $permissiveInvalid = [[[]], [[], 0], "[]", 0, false, ""];
        self::validatorTester($validator, $strictValid, $strictInvalid, $permissiveValid, $permissiveInvalid);
    }

    public function testVArrayDoublyNestedInt()
    {
        // doubly nested int validator, strictness affects int validation through the middle array validator
        $validator = new VArray(new VArray(new VInt()));
        $strictValid = [[], [[]], [[0]]];
        $strictInvalid = [[0], [["0"]], [[0.0]], [[], 0], "[]", 0, false, ""];
        $permissiveValid = [[], [[]], [[0]], [["0"]], [[0.0]]];
        $permissiveInvalid = [[0], [[], 0], "[]", 0, false, ""];
        self::validatorTester($validator, $strictValid, $strictInvalid, $permissiveValid, $permissiveInvalid);
    }

    public function testVStringBasic()
    {
        // strictness does not affect strings
        $validator = new VString();
        $valid = ["", "text"];
        $invalid = [0, false, []];
        self::validatorTester($validator, $valid, $invalid, $valid, $invalid);
    }

    public function testVStringLength()
    {
        // strictness does not affect strings
        $validator = new VString(minLength: 2);
        $valid = ["ab", "text"];
        $invalid = ["", "a", 0, false, []];
        self::validatorTester($validator, $valid, $invalid, $valid, $invalid);

        $validator = new VString(maxLength: 2);
        $valid = ["", "a", "ab"];
        $invalid = ["abc", "text", 0, false, []];
        self::validatorTester($validator, $valid, $invalid, $valid, $invalid);

        $validator = new VString(minLength: 2, maxLength: 3);
        $valid = ["ab", "abc"];
        $invalid = ["", "a", "text", 0, false, []];
        self::validatorTester($validator, $valid, $invalid, $valid, $invalid);
    }

    public function testVStringRegex()
    {
        // strictness does not affect strings
        $validator = new VString(regex: "/^A[0-9a-f]{2}$/");
        $valid = ["A2c", "Add", "A00"];
        $invalid = ["2c", "a2c", "A2g", "A2cc", "", 0, false, []];
        self::validatorTester($validator, $valid, $invalid, $valid, $invalid);
    }

    public function testVStringComplex()
    {
        // strictness does not affect strings
        $validator = new VString(minLength: 1, maxLength: 2, regex: "/^[0-9a-f]*$/");
        $valid = ["a", "aa", "0a"];
        $invalid = ["", "g", "aaa", 0, false, []];
        self::validatorTester($validator, $valid, $invalid, $valid, $invalid);
    }

    public function testVUuid()
    {
        // strictness does not affect strings
        $validator = new VUuid();
        $valid = ["10000000-2000-4000-8000-160000000000"];
        $invalid = ["g0000000-2000-4000-8000-160000000000", "010000000-2000-4000-8000-160000000000", 0, false, []];
        self::validatorTester($validator, $valid, $invalid, $valid, $invalid);
    }

    public function testVMixed()
    {
        // accepts everything
        $validator = new VMixed();
        $valid = [0, 1.2, -1, "", false, [], new VMixed()];
        $invalid = [];
        self::validatorTester($validator, $valid, $invalid, $valid, $invalid);
    }

    public function testVObject()
    {
        // accepts all formats (content is not validated, that is done with the checkedAssign method)
        $validator = new VObject(UserFormat::class);
        $valid = [new UserFormat()];
        $invalid = [0, 1.2, -1, "", false, [], new VMixed()];
        self::validatorTester($validator, $valid, $invalid, $valid, $invalid);
    }
}

(new TestValidators())->run();
