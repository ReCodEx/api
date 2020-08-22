<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box\Params;

/**
 * Variables and other common things for job configuration.
 */
class ConfigParams
{
    public static $WORKER_ID = '${WORKER_ID}';
    public static $JOB_ID = '${JOB_ID}';
    public static $SOURCE_DIR = '${SOURCE_DIR}/';
    public static $EVAL_DIR = '${EVAL_DIR}/';
    public static $RESULT_DIR = '${RESULT_DIR}/';
    public static $TEMP_DIR = '${TEMP_DIR}/';
    public static $JUDGES_DIR = '${JUDGES_DIR}/';

    public static $PATH_DELIM = '/';

    public static $DUMPDIR_LIMIT = 65536;
}
