<?php

namespace App\Helpers\ZenifyFixtures\Alice;

use Faker\Generator as FakerGenerator;
use Nelmio\Alice\Loader\NativeLoader;
use Nelmio\Alice\Parser\ChainableParserInterface;
use Nelmio\Alice\Parser\ParserRegistry;
use Nelmio\Alice\ParserInterface;

class CustomNativeLoader extends NativeLoader
{
    /**
     * @var ChainableParserInterface[]
     */
    private $parsers = [];

    /**
     * CustomNativeLoader constructor.
     * @param FakerGenerator|null $fakerGenerator
     * @param ChainableParserInterface ...$parsers
     */
    public function __construct(FakerGenerator $fakerGenerator = null, ChainableParserInterface ...$parsers)
    {
        $this->parsers = $parsers; // this needs to be first
        parent::__construct($fakerGenerator);
    }


    protected function createParser(): ParserInterface
    {
        return new ParserRegistry($this->parsers);
    }

    protected function getBlacklistedFunctions(): array
    {
        return [
            'current',
            'date'
        ];
    }
}
