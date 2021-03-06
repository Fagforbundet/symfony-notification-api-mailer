<?php


namespace Fagforbundet\NotificationApiMailer\Transport;


use Fagforbundet\NotificationApiMailer\Factory\AccessTokenFactory;
use Fagforbundet\NotificationApiMailer\Interfaces\AccessTokenFactoryInterface;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\Headers;
use Symfony\Component\Mime\Header\ParameterizedHeader;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class NotificationApiTransport extends AbstractApiTransport {
  const DEFAULT_HOST = 'api.meldinger.fagforbundet.no';

  /**
   * @var string
   */
  private $clientId;

  /**
   * @var string
   */
  private $clientSecret;

  /**
   * @var AccessTokenFactoryInterface|null
   */
  private $accessTokenFactory = null;

  /**
   * @param string $clientId
   *
   * @return NotificationApiTransport
   */
  public function setClientId(string $clientId): self {
    $this->clientId = $clientId;
    return $this;
  }

  /**
   * @param string $clientSecret
   *
   * @return NotificationApiTransport
   */
  public function setClientSecret(string $clientSecret): self {
    $this->clientSecret = $clientSecret;
    return $this;
  }

  /**
   * @param AccessTokenFactoryInterface|null $accessTokenFactory
   *
   * @return NotificationApiTransport
   */
  public function setAccessTokenFactory(?AccessTokenFactoryInterface $accessTokenFactory): self {
    $this->accessTokenFactory = $accessTokenFactory;
    return $this;
  }

  /**
   * @param SentMessage $sentMessage
   * @param Email       $email
   * @param Envelope    $envelope
   *
   * @return ResponseInterface
   */
  protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface {
    try {
      $response = $this->client->request('POST', 'https://' . $this->getEndpoint() . '/v1/notifications', [
        'json' => $this->getPayload($email, $envelope),
        'auth_bearer' => $this->getAccessToken(),
      ]);

      $content = $response->toArray();
    } catch (HttpExceptionInterface $e) {
      throw new HttpTransportException(sprintf('Unable to send email: %s', $this->getError($e)), $e->getResponse(), 0, $e);
    } catch (TransportExceptionInterface | DecodingExceptionInterface $e) {
      throw new TransportException('Unable to send email', 0, $e);
    }

    $this->getLogger()->debug('Mail sent to notification-api', ['response' => $content]);

    return $response;
  }

  /**
   * @param HttpExceptionInterface $exception
   *
   * @return string
   * @noinspection PhpDocMissingThrowsInspection
   */
  private function getError(HttpExceptionInterface $exception): string {
    try {
      /** @noinspection PhpUnhandledExceptionInspection */
      return $exception->getResponse()->toArray(false)['error'] ?? $exception->getMessage();
    } /** @noinspection PhpRedundantCatchClauseInspection */ catch (JsonException $e) {
      return $exception->getMessage();
    }
  }

  /**
   * @return string
   */
  public function __toString(): string {
    return sprintf('notification-api+api//%s', $this->getEndpoint());
  }

  /**
   * @return string|null
   */
  private function getEndpoint(): ?string {
    return ($this->host ?: self::DEFAULT_HOST).($this->port ? ':'.$this->port : '');
  }

  /**
   * @return string
   */
  private function getAccessToken(): string {
    if ($this->accessTokenFactory === null) {
      $this->accessTokenFactory = new AccessTokenFactory($this->client);
    }

    return $this->accessTokenFactory->create($this->clientId, $this->clientSecret);
  }

  /**
   * @param Email    $email
   * @param Envelope $envelope
   *
   * @return array
   */
  private function getPayload(Email $email, Envelope $envelope): array {
    $requestData = [
      'content' => $this->getContentPayload($email),
      'subject' => $email->getSubject(),
      'from' => $this->getRecipientPayload(\in_array($envelope->getSender(), $email->getFrom(), true) ? $email->getFrom() : [$envelope->getSender()]),
      'replyTo' => $this->getRecipientPayload($email->getReplyTo()),
      'recipients' => $this->getRecipientsPayload($email, $envelope)
    ];

    if ($attachments = $email->getAttachments()) {
      $requestData['attachments'] = $this->getAttachmentsPayload($attachments);
    }

    return ['email' => $requestData];
  }

  /**
   * @param Email $email
   *
   * @return array
   */
  private function getContentPayload(Email $email): array {
    $contentPayload = [];

    if ($text = $email->getTextBody()) {
      $contentPayload['text'] = is_resource($text) ? stream_get_contents($text) : $text;
    }

    if ($html = $email->getHtmlBody()) {
      $contentPayload['html'] = is_resource($html) ? stream_get_contents($html) : $html;
    }

    return $contentPayload;
  }

  /**
   * @param Email    $email
   * @param Envelope $envelope
   *
   * @return array
   */
  private function getRecipientsPayload(Email $email, Envelope $envelope): array {
    $recipientsPayload = [];

    foreach ($envelope->getRecipients() as $recipient) {
      $type = 'to';
      if (\in_array($recipient, $email->getBcc(), true)) {
        $type = 'bcc';
      } elseif (\in_array($recipient, $email->getCc(), true)) {
        $type = 'cc';
      }

      $recipientsPayload[$type][] = $this->getRecipient($recipient);
    }

    return $recipientsPayload;
  }

  /**
   * @param Address[] $recipients
   *
   * @return array
   */
  private function getRecipientPayload(array $recipients): array {
    $recipientPayload = [];

    foreach ($recipients as $recipient) {
      $recipientPayload[] = $this->getRecipient($recipient);
    }

    return $recipientPayload;
  }

  /**
   * @param Address $recipient
   *
   * @return array
   */
  private function getRecipient(Address $recipient): array {
    $recipientArray = [
      'email' => $recipient->getAddress()
    ];

    if ($name = $recipient->getName()) {
      $recipientArray['name'] = $name;
    }

    return $recipientArray;
  }

  /**
   * @param DataPart[] $dataParts
   *
   * @return array
   */
  private function getAttachmentsPayload(array $dataParts): array {
    $attachmentsPayload = [];

    foreach ($dataParts as $dataPart) {
      $attachmentsPayload[] = $this->getAttachmentPayload($dataPart);
    }

    return $attachmentsPayload;
  }

  /**
   * @param DataPart $dataPart
   *
   * @return array
   */
  private function getAttachmentPayload(DataPart $dataPart): array {
    return [
      'fileName' => $this->getFilenameFromHeaders($dataPart->getPreparedHeaders()),
      'contentType' => $dataPart->getMediaType() . '/' . $dataPart->getMediaSubtype(),
      'content' => base64_encode($dataPart->getBody())
    ];
  }

  /**
   * @param Headers $headers
   *
   * @return string
   */
  private function getFilenameFromHeaders(Headers $headers): string {
    if (!$headers->has('Content-Disposition')) {
      return '';
    }

    $header = $headers->get('Content-Disposition');

    if (!$header instanceof ParameterizedHeader) {
      return '';
    }

    return $header->getParameter('filename');
  }

}
