<?php

namespace App\Helpers\EvaluationResults;

interface ITestResult {

  public function getId(): string;
  public function getStatus(): string;
  public function getStats();
  public function getScore(): float;
  public function didExecutionMeetLimits(): bool;
  public function isTimeOK(): bool;
  public function isMemoryOK(): bool;
  public function getExitCode(): int;
  public function getUsedMemoryRatio(): float;
  public function getUsedTimeRatio(): float;
  public function getMessage(): string;
  public function getJudgeOutput();
}
