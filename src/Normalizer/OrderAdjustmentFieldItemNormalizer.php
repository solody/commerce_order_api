<?php

namespace Drupal\commerce_order_api\Normalizer;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Plugin\Field\FieldType\AdjustmentItem;
use Drupal\serialization\Normalizer\FieldItemNormalizer;

/**
 * 展开OrderAdjustment字段.
 */
class OrderAdjustmentFieldItemNormalizer extends FieldItemNormalizer {

    /**
     * The interface or class that this Normalizer supports.
     *
     * @var string
     */
    protected $supportedInterfaceOrClass = AdjustmentItem::class;

    /**
     * {@inheritdoc}
     */
    public function normalize($field_item, $format = NULL, array $context = []) {
        /** @var AdjustmentItem $field_item */
        /** @var Adjustment $adjustment */
        $adjustment = $field_item->value;
        $data = $adjustment->toArray();
        $data['amount'] = $data['amount']->toArray();
        return $data;
    }
}