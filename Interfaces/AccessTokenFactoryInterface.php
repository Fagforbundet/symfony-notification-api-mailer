<?php


namespace Fagforbundet\NotificationApiMailer\Interfaces;


interface AccessTokenFactoryInterface {

  /**
   * @param string|null $clientId
   * @param string|null $clientSecret
   *
   * @return string
   */
  public function create(?string $clientId = null, ?string $clientSecret = null): string;
}
