<?php

namespace App\Helpers;

use ZMQ;
use ZMQSocket;
use ZMQContext;
use ZMQSocketException;

/**
 * @author  Šimon Rozsíval <simon@rozsival.com>
 */
class BrokerProxy {

  const EXPECTED_RESPONSE = "accept";

  private $brokerAddress;

  public function __construct($config) {
    $this->brokerAddress = $config['address'];
  }

  /**
   * @param $submissionId
   * @param $archiveRemotePath
   * @param $resultRemotePath
   * @return bool Evaluation has been started on remote server when returns TRUE.
   * @throws SubmissionFailedException
   * @internal param $string
   * @internal param $string
   * @internal param $string
   * @internal param $string
   */
  public function startEvaluation(string $submissionId, string $hardwareGroup, string $archiveRemotePath, string $resultRemotePath) {
    try {
      $queue = new ZMQSocket(new ZMQContext, ZMQ::SOCKET_REQ, $submissionId);
      $queue->connect($this->brokerAddress);
      $queue->sendmulti([
        "eval",
        $submissionId,
        "hwgroup=$hardwareGroup",
        "",
        $archiveRemotePath,
        $resultRemotePath
      ]);

      $response = $queue->recv();
      return $response === self::EXPECTED_RESPONSE;
    } catch (ZMQSocketException $e) {
      throw new SubmissionFailedException("Communication with backend broker failed.");
    }
  }

}
