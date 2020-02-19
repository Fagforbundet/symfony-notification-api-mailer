<?php


namespace Fagforbundet\NotificationApiMailer\Transport;


use Fagforbundet\NotificationApiMailer\Interfaces\AccessTokenFactoryInterface;
use Symfony\Component\Mailer\Exception\UnsupportedSchemeException;
use Symfony\Component\Mailer\Transport\AbstractTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportInterface;

class NotificationApiTransportFactory extends AbstractTransportFactory {

  /**
   * @var AccessTokenFactoryInterface|null
   */
  private $accessTokenFactory = null;

  /**
   * @param AccessTokenFactoryInterface|null $accessTokenFactory
   *
   * @return NotificationApiTransportFactory
   */
  public function setAccessTokenFactory(?AccessTokenFactoryInterface $accessTokenFactory): self {
    $this->accessTokenFactory = $accessTokenFactory;
    return $this;
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

    return (new NotificationApiTransport($this->client, $this->dispatcher, $this->logger))
      ->setHost('default' === $dsn->getHost() ? null : $dsn->getHost())
      ->setPort($dsn->getPort())
      ->setAccessTokenFactory($this->accessTokenFactory)
      ->setClientId($dsn->getUser())
      ->setClientSecret($dsn->getPassword())
      ->setVerifyHost(filter_var($dsn->getOption('verifyHost', true), FILTER_VALIDATE_BOOLEAN));
  }
}
