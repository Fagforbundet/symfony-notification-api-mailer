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
  const SCOPE = 'notifications.send notifications.emails.send_raw';

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
   * @param string              $tokenEndpoint
   * @param HttpClientInterface $client
   * @param string|null         $scope
   */
  public function __construct(string $tokenEndpoint, HttpClientInterface $client = null, string $scope = null) {
    $this->tokenEndpoint = $tokenEndpoint;
    $this->client = $client ?: HttpClient::create();
    $this->scope = $scope ?: self::SCOPE;
  }

  /**
   * @inheritDoc
   */
  public function create(?string $clientId = null, ?string $clientSecret = null): string {
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
