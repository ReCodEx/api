<?php

use Tester\Assert;

use Nette\Utils\Json;
use Nette\Utils\JsonException;

use App\Model\Entity\Login;

class RestApiResponse extends Nette\Object {

  public function __construct(GuzzleHttp\Psr7\Response $response) {
    $this->response = $response;
    $this->statusCode = $response->getStatusCode();
    $json = $response->getBody()->getContents();
    try {
      $this->body = Json::decode($json);
    } catch (JsonException $e) {
      $this->body = $json;
    }
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

  private $defaultParams = [
    'http_errors' => FALSE
  ];
  private $client = null;

  private $user = null;

  private function getClient() {
    if ($this->client === null) {
      $this->client = new GuzzleHttp\Client;
    }
    return $this->client;
  }

  private function request($endpoint, $method = 'GET', $parameters = []) {
    $client = $this->getClient();
    $url = self::URL . $endpoint;
    $parameters = array_merge($this->defaultParams, $parameters);

    if ($this->isLoggedIn()) {
      if (!isset($parameters['query'])) {
        $parameters['query'] = [];
      }
      $parameters['query']['access_token'] = $this->user['accessToken'];
    }

    $response = null;
    echo $method;
    echo $url;
    print_r($parameters);
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

  protected function isLoggedIn() {
    return $this->user !== null;
  }

  /**
   * Log in a user. Every next request will have the proper access token.
   * @param  array  $user All important info for the user, at least fields
   *                      'username' and 'password'
   * @return RestApiResponse Response of the API server for assertions
   */
  protected function login(array $user) {
    $response = $this->get("/login", [ 'query' => [
      "username" => $user['email'],
      "password" => $user['password']
    ]]);

    if ($response->statusCode == 200) {
      $this->user = $user;
      $this->user['accessToken'] = $response->body->payload->accessToken;
    }

    return $response;
  }

  protected function logout() {
    $this->user = null;
  }
}
