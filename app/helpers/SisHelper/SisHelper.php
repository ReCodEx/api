<?php
namespace App\Helpers;
use App\Exceptions\InvalidArgumentException;
use Generator;
use Nette;
use GuzzleHttp;
use Nette\Utils\Json;
use Nette\Utils\Strings;

class SisHelper extends Nette\Object {
  private $apiBase;

  private $faculty;

  private $secret;

  private $client;

  /**
   * @param $apiBase
   * @param $faculty
   * @param $secret
   */
  public function __construct($apiBase, $faculty, $secret) {
    $this->apiBase = $apiBase;
    $this->faculty = $faculty;
    $this->secret = $secret;

    if (!Strings::endsWith($this->apiBase, '/')) {
      $this->apiBase .= '/';
    }

    $this->client = new GuzzleHttp\Client([
      'base_uri' => $this->apiBase . 'rozvrhng/rest.php'
    ]);
  }

  /**
   * @param $sisUserId
   * @param $year
   * @param int $term
   * @return SisCourseRecord[]|Generator
   * @throws InvalidArgumentException
   */
  public function getCourses($sisUserId, $year = null, $term = 1) {
    $salt = join(',', [ time(), $this->faculty, $sisUserId ]);
    $hash = hash('sha256', "$salt,$this->secret");

    $params = [
      'endpoint' => 'muj_rozvrh',
      'ukco' => $sisUserId,
      'auth_token' => "$salt\$$hash",
      'fak' => $this->faculty,
      'extras' => ['annotations']
    ];

    if ($year !== null) {
      $params['semesters'] = [sprintf("%s-%s", $year, $term)];
    }

    try {
      $response = $this->client->get('', ['query' => $params]);
    } catch (GuzzleHttp\Exception\ClientException $e) {
      throw new InvalidArgumentException("Invalid year or semester number");
    }

    $data = Json::decode($response->getBody()->getContents());

    foreach ($data["events"] as $course) {
      yield SisCourseRecord::fromArray($sisUserId, $course);
    }
  }
}