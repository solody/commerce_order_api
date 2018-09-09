<?php
namespace Drupal\commerce_order_api\Event;

use Drupal\commerce_order\Entity\Order;
use Symfony\Component\EventDispatcher\Event;

/**
 * Defines the TaskEvent.
 */
class OrderAutoCompleteEvent extends Event
{
    const ORDER_AUTO_COMPLETE = 'commerce_order_api.order_auto_complete';

    /**
     * @var Order
     */
    protected $commerceOrder;

    /**
     * TaskEvent constructor.
     * @param Order $commerce_order
     */
    public function __construct(Order $commerce_order)
    {
        $this->commerceOrder = $commerce_order;
    }

    public function getOrder()
    {
        return $this->commerceOrder;
    }
}
