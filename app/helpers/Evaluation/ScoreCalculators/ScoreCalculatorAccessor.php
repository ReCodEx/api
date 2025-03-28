<?php

namespace App\Helpers\Evaluation;

use App\Helpers\Evaluation\IScoreCalculator;
use Nette;
use Nette\Utils\Arrays;
use InvalidArgumentException;

/**
 * Provides access to different implementations of score calculation
 */
class ScoreCalculatorAccessor
{
    use Nette\SmartObject;

    private $calculators;

    /**
     * ScoreCalculatorAccessor constructor.
     * @param array $calculators array of recognized calculators (instances of {@link IScoreCalculator})
     * @throws InvalidArgumentException
     */
    public function __construct(array $calculators)
    {
        if (count($calculators) === 0) {
            throw new InvalidArgumentException("No score calculators provided");
        }

        $this->calculators = [];
        foreach ($calculators as $calculator) {
            $id = $calculator->getId();
            if (!empty($this->calculators[$id])) {
                throw new InvalidArgumentException("Provided calculators contain duplicit IDs ($id)");
            }
            $this->calculators[$id] = $calculator;
        }
    }

    /**
     * @param null|string $id
     * @return IScoreCalculator
     */
    public function getCalculator(?string $id): IScoreCalculator
    {
        if (empty($id)) {
            return $this->getDefaultCalculator();
        }

        return Arrays::get($this->calculators, $id, $this->getDefaultCalculator());
    }

    public function getDefaultCalculator(): IScoreCalculator
    {
        return reset($this->calculators);
    }
}
