<?php

/**
 * @file
 * Definition of Drupal\domain\DomainResolver.
 */

namespace Drupal\domain;

use Drupal\domain\DomainResolverInterface;
use Drupal\domain\DomainInterface;
use Drupal\domain\DomainLoaderInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class DomainResolver implements DomainResolverInterface {

  public $httpHost;

  public $domain;

  public $domainLoader;

  /**
   * The request stack object.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;


  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a DomainResolver object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack
   *   The request stack object.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(RequestStack $requestStack, ModuleHandlerInterface $module_handler, DomainLoaderInterface $loader) {
    $this->httpHost = NULL;
    $this->requestStack = $requestStack;
    $this->moduleHandler = $module_handler;
    $this->domainLoader = $loader;
    $this->domain = NULL;
  }

  public function setRequestDomain($httpHost, $reset = FALSE) {
    static $loookup;
    if (isset($lookup[$httpHost]) && !$reset) {
      return $lookup[$httpHost];
    }
    $this->setHttpHost($httpHost);
    $domain = $this->domainLoader->loadByHostname($httpHost);
    // If a straight load fails, check with modules (like Domain Alias) that
    // register alternate paths with the main module.
    if (empty($domain)) {
      $domain = entity_create('domain', array('hostname' => $httpHost));
      // @TODO: Should this be an event instead?
      // @TODO: Should this be hook_domain_bootstrap_lookup?
      $info['domain'] = $domain;
      $this->moduleHandler->alter('domain_request', $info);
      $domain = $info['domain'];
    }
    if (!empty($domain->id)) {
      $this->setActiveDomain($domain);
    }
    $lookup[$httpHost] = $domain;
  }

  public function setActiveDomain(DomainInterface $domain) {
    // @TODO: caching
    $this->domain = $domain;
  }

  public function resolveActiveDomain() {
    $httpHost = $this->resolveActiveHostname();
    $this->setRequestDomain($httpHost);
    return $this->domain;
  }

  /**
   * Gets the active domain.
   */
  public function getActiveDomain($reset = FALSE) {
    if (is_null($this->domain) || $reset) {
      $this->resolveActiveDomain();
    }
    return $this->domain;
  }

  /**
   * Gets the id of the active domain.
   */
  public function getActiveId() {
    return $this->domain->id();
  }

  /**
   * Gets the hostname of the active request.
   */
  public function resolveActiveHostname() {
    if ($request = $this->requestStack->getCurrentRequest()) {
      $httpHost = $request->getHttpHost();
    }
    else {
      $httpHost = $_SERVER['HTTP_HOST'];
    }
    return !empty($httpHost) ? $httpHost : 'localhost';
  }

  public function setHttpHost($httpHost) {
    $this->httpHost = $httpHost;
  }

  public function getHttpHost() {
    return $this->httpHost;
  }

}