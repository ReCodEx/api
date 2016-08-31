<?php

namespace App\Model\Helpers;

use ZMQ;
use ZMQSocket;
use ZMQContext;
use ZMQSocketException;

/**
 * @author  Šimon Rozsíval <simon@rozsival.com>
 */
class BrokerProxy {

  private $brokerAddress;
  private $expectedResponse;

  public function __construct($config) {
    $this->brokerAddress = $config['address'];
    $this->expectedResponse = $config['expectedResponse'];
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
      return $response === $this->expectedResponse;
    } catch (ZMQSocketException $e) {
      throw new SubmissionFailedException("Communication with backend broker failed.");
    }
  }

}
