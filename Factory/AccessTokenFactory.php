<?php


namespace Fagforbundet\NotificationApiMailer\Factory;


use Fagforbundet\NotificationApiMailer\Interfaces\AccessTokenFactoryInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AccessTokenFactory implements AccessTokenFactoryInterface {
  const DEFAULT_SCOPE = 'notifications.send notifications.emails.send_raw';
  const DEFAULT_TOKEN_ENDPOINT = 'https://api.id.fagforbundet.no/v1/oauth/token';

  /**
   * @var string
   */
  private $tokenEndpoint;

  /**
   * @var HttpClientInterface
   */
  private $client;

  /**
   * @var string
   */
  private $scope;

  /**
   * AccessTokenFactory constructor.
   *
   * @param HttpClientInterface $client
   * @param string|null         $tokenEndpoint
   * @param string|null         $scope
   */
  public function __construct(?HttpClientInterface $client = null, ?string $tokenEndpoint = null, ?string $scope = null) {
    $this->client = $client ?: HttpClient::create();
    $this->tokenEndpoint = $tokenEndpoint ?: self::DEFAULT_TOKEN_ENDPOINT;
    $this->scope = $scope ?: self::DEFAULT_SCOPE;
  }

  /**
   * @inheritDoc
   */
  public function create(string $clientId, string $clientSecret): string {
    try {
      $response = $this->client->request('POST', $this->tokenEndpoint, [
        'body' => [
          'grant_type' => 'client_credentials',
          'scope' => $this->scope,
          'client_id' => $clientId,
          'client_secret' => $clientSecret,
        ]
      ]);

      $content = $response->toArray();
    } catch (HttpExceptionInterface $e) {
      throw new HttpTransportException(sprintf('Unable to contact id provider'), $e->getResponse(), 0, $e);
    } catch (DecodingExceptionInterface | TransportExceptionInterface $e) {
      throw new TransportException('Unable to contact id provider', 0, $e);
    }

    return $content['access_token'];
  }
}
