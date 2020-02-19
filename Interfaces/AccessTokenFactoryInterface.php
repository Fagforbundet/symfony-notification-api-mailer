<?php


namespace Fagforbundet\NotificationApiMailer\Interfaces;


interface AccessTokenFactoryInterface {

  /**
   * @param string $clientId
   * @param string $clientSecret
   *
   * @return string
   */
  public function create(string $clientId, string $clientSecret): string;
}
