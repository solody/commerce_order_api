<?php

namespace Drupal\commerce_order_api\Plugin\rest\resource;

use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Resolver\ChainOrderTypeResolverInterface;
use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Symfony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "commerce_order_api_none_cart_order",
 *   label = @Translation("None cart order"),
 *   uri_paths = {
 *     "create" = "/api/rest/commerce-order/none-cart-order"
 *   }
 * )
 */
class NoneCartOrder extends ResourceBase {

  /**
   * A current user instance.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The cart provider.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;

  /**
   * The cart manager.
   *
   * @var \Drupal\commerce_cart\CartManagerInterface
   */
  protected $cartManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The order item store.
   *
   * @var \Drupal\commerce_order\OrderItemStorageInterface
   */
  protected $orderItemStorage;

  /**
   * The chain order type resolver.
   *
   * @var \Drupal\commerce_order\Resolver\ChainOrderTypeResolverInterface
   */
  protected $chainOrderTypeResolver;

  /**
   * The current store.
   *
   * @var \Drupal\commerce_store\CurrentStoreInterface
   */
  protected $currentStore;

  /**
   * Constructs a new NoneCartOrder object.
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
   * @param CartProviderInterface $cart_provider
   * @param CartManagerInterface $cart_manager
   * @param EntityTypeManagerInterface $entity_type_manager
   * @param ChainOrderTypeResolverInterface $chain_order_type_resolver
   * @param CurrentStoreInterface $current_store
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    AccountProxyInterface $current_user, CartProviderInterface $cart_provider, CartManagerInterface $cart_manager, EntityTypeManagerInterface $entity_type_manager, ChainOrderTypeResolverInterface $chain_order_type_resolver, CurrentStoreInterface $current_store) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);

    $this->currentUser = $current_user;
    $this->cartProvider = $cart_provider;
    $this->cartManager = $cart_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->orderItemStorage = $entity_type_manager->getStorage('commerce_order_item');
    $this->chainOrderTypeResolver = $chain_order_type_resolver;
    $this->currentStore = $current_store;
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
      $container->get('current_user'),
      $container->get('commerce_cart.cart_provider'),
      $container->get('commerce_cart.cart_manager'),
      $container->get('entity_type.manager'),
      $container->get('commerce_order.chain_order_type_resolver'),
      $container->get('commerce_store.current_store')
    );
  }

  /**
   * Responds to POST requests.
   *
   * 创建一个非购物车的订单，并考虑并发因素，使用 lock服务来控制操作的唯一性
   *
   * @param array $body
   * @param Request $request
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function post(array $body, Request $request) {

    // You must to implement the logic of your REST Resource here.
    // Use current user after pass authentication to validate access.
    if (!$this->currentUser->hasPermission('access content')) {
      throw new AccessDeniedHttpException();
    }

    // Do an initial validation of the payload before any processing.
    if (!isset($body['purchased_entity_type'])) {
      throw new UnprocessableEntityHttpException(sprintf('You must specify a purchasable entity type for purchased_entity_type'));
    }
    if (!isset($body['purchased_items']) || !is_array($body['purchased_items']) || empty($body['purchased_items'])) {
      throw new UnprocessableEntityHttpException(sprintf('You must specify a array for purchased_items'));
    }
    if (!$this->entityTypeManager->hasDefinition($body['purchased_entity_type'])) {
      throw new UnprocessableEntityHttpException(sprintf('You must specify a valid purchasable entity type for purchased_entity_type'));
    }

    $lock = \Drupal::lock();
    $operationID = "none_cart_order__create";
    $is_get_lock = $lock->acquire($operationID);
    if (!$is_get_lock) {
      if (!$lock->wait($operationID, 30)) {
        $is_get_lock = $lock->acquire($operationID);
      }
    }


    if ($is_get_lock) {
      // 获得锁，生成订单
      $storage = $this->entityTypeManager->getStorage($body['purchased_entity_type']);
      $order = null;

      try {

        foreach ($body['purchased_items'] as $purchased_item) {
          $purchased_entity = $storage->load($purchased_item['purchased_entity_id']);
          if (!$purchased_entity || !$purchased_entity instanceof PurchasableEntityInterface) {
            continue;
          }
          $order_item = $this->orderItemStorage->createFromPurchasableEntity($purchased_entity, [
            'quantity' => (!empty($purchased_item['quantity'])) ? $purchased_item['quantity'] : 1,
          ]);

          if (!$order) {
            $order_type_id = $this->chainOrderTypeResolver->resolve($order_item);
            $store = $this->selectStore($purchased_entity);
            $order = $this->cartProvider->createCart($order_type_id, $store);
          }
          $this->cartManager->addOrderItem($order, $order_item, TRUE);
        }

        // 把订单切为非购物车状态
        $this->cartProvider->finalizeCart($order);

        // 解锁
        $lock->release($operationID);

        return new ModifiedResourceResponse($order, 200);

      } catch (\Exception $exception) {
        // 如果发生异常，把已创建的订单项删除，并删除订单
        if ($order instanceof OrderInterface) {
          $this->cartManager->emptyCart($order);
          $order->delete();
        }

        // 解锁
        $lock->release($operationID);

        throw new BadRequestHttpException($exception->getMessage());
      }

    } else {
      throw new BadRequestHttpException('有规定时间内未能取得操作权，lock id: '.$operationID);
    }
  }

  public function permissions() {
    return [];
  }

  /**
   * Selects the store for the given purchasable entity.
   *
   * If the entity is sold from one store, then that store is selected.
   * If the entity is sold from multiple stores, and the current store is
   * one of them, then that store is selected.
   *
   * @param \Drupal\commerce\PurchasableEntityInterface $entity
   *   The entity being added to cart.
   *
   * @throws \Exception
   *   When the entity can't be purchased from the current store.
   *
   * @return \Drupal\commerce_store\Entity\StoreInterface
   *   The selected store.
   */
  protected function selectStore(PurchasableEntityInterface $entity) {
    $stores = $entity->getStores();
    if (count($stores) === 1) {
      $store = reset($stores);
    }
    elseif (count($stores) === 0) {
      // Malformed entity.
      throw new \Exception('The given entity is not assigned to any store.');
    }
    else {
      $store = $this->currentStore->getStore();
      if (!in_array($store, $stores)) {
        // Indicates that the site listings are not filtered properly.
        throw new \Exception("The given entity can't be purchased from the current store.");
      }
    }

    return $store;
  }
}
