<?php

namespace Drupal\commerce_order_api\Plugin\rest\resource;

use Drupal\commerce_order\Entity\Order;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "commerce_order_api_apply_order_transition",
 *   label = @Translation("Apply Order Transition"),
 *   uri_paths = {
 *     "create" = "/api/rest/commerce-order/apply-order-transition"
 *   }
 * )
 */
class ApplyOrderTransition extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new ApplyOrderTransition object.
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

    if (!isset($data['order_id']) || !isset($data['from_state']) || !isset($data['transition'])) {
      throw new BadRequestHttpException('缺少参数');
    }

    $order = Order::load($data['order_id']);

    if ($order) {
      if (true/*$order->getCustomerId() === $this->currentUser->id()*/) {
        if ($order->getState()->value === $data['from_state']) {
          /** @var \Drupal\state_machine\Plugin\Workflow\WorkflowTransition $transition */
          $transition = $order->getState()->getTransitions()[$data['transition']];
          if ($transition) {
            $order->getState()->applyTransition($transition);
            $order->save();
          } else {
            throw new BadRequestHttpException('订单当前状态无法执行此操作');
          }
        } else {
          throw new BadRequestHttpException('订单不是预期状态');
        }
      } else {
        throw new BadRequestHttpException('只能操作自己的订单');
      }
    } else {
      throw new BadRequestHttpException('订单不存在');
    }

    return new ModifiedResourceResponse($order, 200);
  }

}
