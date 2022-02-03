<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box\Params;

/**
 * Some default priorities for boxes.
 */
class Priorities
{
    public static $INITIATION = 100;
    public static $EXECUTION = 90;
    public static $EVALUATION = 80;
    public static $DEFAULT = 42;
    public static $DUMP_RESULTS = 1;

    public static function defaultPriorityOfType(TaskType $type)
    {
        $translation = [
            TaskType::$INITIATION => self::$INITIATION,
            TaskType::$EXECUTION => self::$EXECUTION,
            TaskType::$EVALUATION => self::$EVALUATION,
        ];
        return array_key_exists($type, $translation) ? $type[$translation] : self::$DEFAULT;
    }
}
