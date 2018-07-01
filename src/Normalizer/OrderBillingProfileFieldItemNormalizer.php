<?php

namespace Drupal\commerce_order_api\Normalizer;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\serialization\Normalizer\EntityReferenceFieldItemNormalizer;

/**
 * 展开订单收货信息字段
 */
class OrderBillingProfileFieldItemNormalizer extends EntityReferenceFieldItemNormalizer {

  public function supportsNormalization($data, $format = NULL) {
    if (parent::supportsNormalization($data, $format)) {
      if ($data instanceof EntityReferenceItem) {
        $entity = $data->get('entity')->getValue();
        if ($entity instanceof ProfileInterface) {
          if ($data->getParent() && $data->getParent()->getParent() && $data->getParent()->getParent()->getValue() instanceof OrderInterface) {
            return true;
          }
        }
      }
    }
    return false;
  }

  /**
     * {@inheritdoc}
     */
    public function normalize($field_item, $format = NULL, array $context = []) {
        $entity = $field_item->entity;
        $data = null;
        if ($entity instanceof ProfileInterface) {
          $data = $this->serializer->normalize($entity, $format, $context);
        } else {
          $data = parent::normalize($field_item, $format, $context);
        }
        return $data;
    }
}