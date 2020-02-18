<?php


namespace Fagforbundet\NotificationApiMailer\Transport;


use Fagforbundet\NotificationApiMailer\Factory\AccessTokenFactory;
use Fagforbundet\NotificationApiMailer\Interfaces\AccessTokenFactoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class NotificationApiTransportFactory extends AbstractTransportFactory {
  const TOKEN_ENDPOINT = 'https://api.id.fagforbundet.no/v1/oauth/token';

  /**
   * @var AccessTokenFactoryInterface
   */
  private $accessTokenFactory;

  /**
   * NotificationApiTransportFactory constructor.
   *
   * @param EventDispatcherInterface|null    $dispatcher
   * @param HttpClientInterface|null         $client
   * @param LoggerInterface|null             $logger
   * @param AccessTokenFactoryInterface|null $accessTokenFactory
   */
  public function __construct(EventDispatcherInterface $dispatcher = null, HttpClientInterface $client = null, LoggerInterface $logger = null, ?AccessTokenFactoryInterface $accessTokenFactory = null) {
    parent::__construct($dispatcher, $client, $logger);
    $this->accessTokenFactory = $accessTokenFactory ?: new AccessTokenFactory(self::TOKEN_ENDPOINT, $client);
  }

  /**
   * @return array
   */
  protected function getSupportedSchemes(): array {
    return ['notification-api', 'notification-api+api'];
  }

  /**
   * @inheritDoc
   */
  public function create(Dsn $dsn): TransportInterface {
    $scheme = $dsn->getScheme();

    if ('notification-api+api' !== $scheme && 'notification-api' !== $scheme) {
      throw new UnsupportedSchemeException($dsn, 'notification-api', $this->getSupportedSchemes());
    }

    return (new NotificationApiTransport($this->accessTokenFactory, $dsn->getUser(), $dsn->getPassword(), $this->client, $this->dispatcher, $this->logger))
      ->setHost('default' === $dsn->getHost() ? null : $dsn->getHost())
      ->setPort($dsn->getPort());
  }
}
