<?php

namespace App\Exceptions;

class FrontendErrorMappings
{

  /** General accepted */
  const E202_000__ACCEPTED = "202-000";

  /** General bad request */
  const E400_000__BAD_REQUEST = "400-000";
  /** General wrong credentials */
  const E400_001__WRONG_CREDENTIALS = "400-001";
  /** General job config error */
  const E400_100__JOB_CONFIG = "400-100";
  /** General exercise config error */
  const E400_200__EXERCISE_CONFIG = "400-200";

  /** General exercise compilation error */
  const E400_300__EXERCISE_COMPILATION = "400-300";
  /** File '{$filename}' is already defined by author of the exercise */
  const E400_301__EXERCISE_COMPILATION_FILE_DEFINED = "400-301";
  /** Submitted files contains two or more files with the same name */
  const E400_302__EXERCISE_COMPILATION_DUPLICATE_FILES = "400-302";
  /** None of the submitted files matched regular expression '{$regex}' in variable '{$variable}' */
  const E400_303__EXERCISE_COMPILATION_VAR_NOT_MATCHED = "400-303";
  /** Variable '{$variable}' was not provided on submit */
  const E400_304__EXERCISE_COMPILATION_VAR_NOT_PROVIDED = "400-304";
  /** File '{$filename}' in variable '{$variable}' could not be found among submitted files */
  const E400_305__EXERCISE_COMPILATION_FILE_NOT_PROVIDED = "400-305";

  /** General unauthorized */
  const E401_000__UNAUTHORIZED = "401-000";
  /** Token was not provided in request */
  const E401_001__NO_TOKEN = "401-001";
  /** Token was provided, but was invalid */
  const E401_002__INVALID_TOKEN = "401-002";

  /** General payment required */
  const E402_000__PAYMENT_REQUIRED = "402-000";

  /** General forbidden */
  const E403_000__FORBIDDEN = "403-000";

  /** General not found */
  const E404_000__NOT_FOUND = "404-000";

  /** General method not allowed */
  const E405_000__METHOD_NOT_ALLOWED = "405-000";

  /** General conflict */
  const E409_000__CONFLICT = "409-000";

  /** General internal server error */
  const E500_000__INTERNAL_SERVER_ERROR = "500-000";
  /** Cannot receive uploaded file */
  const E500_001__CANNOT_RECEIVE_FILE = "500-001";
  /** General LDAP connection exception */
  const E500_002__LDAP_CONNECTION = "500-002";
  /** General job config error */
  const E500_100__JOB_CONFIG = "500-100";
  /** General exercise config error */
  const E500_200__EXERCISE_CONFIG = "500-200";
  /** General exercise compilation error */
  const E500_300__EXERCISE_COMPILATION = "500-300";

  /** General not implemented */
  const E501_000__NOT_IMPLEMENTED = "501-000";
}
