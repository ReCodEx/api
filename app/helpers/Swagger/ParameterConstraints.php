<?php

namespace App\Helpers\Swagger;

class ParameterConstraints
{
    private array $constraints;

    /**
     * Constructs a container for swagger constraints.
     * Constructor parameter names match swagger keywords, see
     * https://swagger.io/docs/specification/v3_0/data-models/keywords/.
     * @param ?string $pattern String regex pattern.
     * @param ?int $minLength String min length.
     * @param ?int $maxLength String max length.
     */
    public function __construct(?string $pattern = null, ?int $minLength = null, ?int $maxLength = null)
    {
        # swagger patterns must not contain the bounding '/.../' slashes
        if ($pattern != null && strlen($pattern) >= 2) {
            $pattern = substr($pattern, 1, -1);
        }

        $this->constraints["pattern"] = $pattern;
        $this->constraints["minLength"] = $minLength;
        $this->constraints["maxLength"] = $maxLength;
    }

    /**
     * Adds constraints to a ParenthesesBuilder for swagger doc construction.
     * @param \App\Helpers\Swagger\ParenthesesBuilder $container The container for keywords and values.
     */
    public function addConstraints(ParenthesesBuilder $container)
    {
        foreach ($this->constraints as $keyword => $value) {
        // skip null values
            if ($value === null) {
                continue;
            }

            $container->addKeyValue($keyword, $value);
        }
    }
}
