<?php

namespace Drupal\commerce_order_api\Plugin\rest\resource;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\profile\Entity\Profile;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "commerce_order_api_set_order_billing_profile",
 *   label = @Translation("Set order billing profile"),
 *   uri_paths = {
 *     "create" = "/api/rest/commerce-order/set-order-billing-profile"
 *   }
 * )
 */
class SetOrderBillingProfile extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new SetOrderBillingProfile object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   A current user instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('commerce_order_api'),
      $container->get('current_user')
    );
  }

  /**
   * Responds to POST requests.
   *
   * @param $data
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function post($data) {

    // You must to implement the logic of your REST Resource here.
    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    $commerce_order = Order::load($data['order_id']);
    if ($commerce_order instanceof OrderInterface) {
      // 保存收货地址
      if (isset($data['billing_profile']) && !empty($data['billing_profile'])) {
        $billing_profile = Profile::load($data['billing_profile']);
        $commerce_order->set('billing_profile', $billing_profile);
      } else {
        // 尝试使用默认地址
        if ($this->currentUser->isAuthenticated()) {
          $default_profile = \Drupal::entityTypeManager()->getStorage('profile')->loadByProperties([
            'uid' => $this->currentUser->id(),
            'is_default' => true,
            'status' => true
          ]);

          if (count($default_profile)) {
            $default_profile = array_pop($default_profile);
          }

          if ($default_profile instanceof Profile) {
            $commerce_order->set('billing_profile', $default_profile);
          }
        }
      }

      $commerce_order->save();
    }

    return new ModifiedResourceResponse($commerce_order, 200);
  }

  public function permissions() {
    return [];
  }
}
