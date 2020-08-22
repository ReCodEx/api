<?php

/**
 * Mock of App\Model\Entity\TestResult
 */
class TestResultMock
{
    private $score;

    public function __construct($score)
    {
        $this->score = $score;
    }

    public function getScore()
    {
        return $this->score;
    }
}
