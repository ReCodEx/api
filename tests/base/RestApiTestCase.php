<?php

use Nette\Utils\Json;

class RestApiResponse extends Nette\Object {

  public function __construct(GuzzleHttp\Psr7\Response $response) {
    $this->response = $response;
    $this->statusCode = $response->getStatusCode();
    $json = $response->getBody()->getContents();
    $this->body = Json::decode($json);
  }

  private $response;

  public function getResponse() {
    return $this->response;
  }

  private $statusCode;

  public function getStatusCode() {
    return $this->statusCode;
  }

  private $body;

  public function getBody() {
    return $this->body;
  }
}

class RestApiTestCase extends Tester\TestCase {

  const URL = 'http://127.0.0.1:4000/v1';

  private $client = null;

  private function getClient() {
    if ($this->client === null) {
      $this->client = new GuzzleHttp\Client;
    }
    return $this->client;
  }

  private function request($endpoint, $method = 'GET', $parameters = []) {
    $client = $this->getClient();
    $url = self::URL . $endpoint;
    $response = null;
    switch ($method) {
      case 'GET':
        $response = $client->get($url, $parameters);
        break;
      case 'POST':
        $response = $client->post($url, $parameters);
        break;
      case 'PUT':
        $response = $client->put($url, $parameters);
        break;
      case 'DELETE':
        $response = $client->delete($url, $parameters);
        break;
    }
    return new RestApiResponse($response);
  }

  protected function get($endpoint, $parameters = []) {
    return $this->request($endpoint, 'GET', $parameters);
  }

  protected function post($endpoint, $parameters = []) {
    return $this->request($endpoint, 'POST', $parameters);
  }

  protected function put($endpoint, $parameters = []) {
    return $this->request($endpoint, 'PUT', $parameters);
  }

  protected function delete($endpoint, $parameters = []) {
    return $this->request($endpoint, 'DELETE', $parameters);
  }

}
