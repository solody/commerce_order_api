<?php

namespace Drupal\commerce_order_api\Normalizer;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\serialization\Normalizer\EntityReferenceFieldItemNormalizer;

/**
 * 展开订单项字段
 */
class OrderItemFieldItemNormalizer extends EntityReferenceFieldItemNormalizer {

  public function supportsNormalization($data, $format = NULL) {
    if (parent::supportsNormalization($data, $format)) {
      if ($data instanceof EntityReferenceItem) {
        $entity = $data->get('entity')->getValue();
        if ($entity instanceof OrderItemInterface) {
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
        if ($entity instanceof OrderItemInterface) {
          $data = $this->serializer->normalize($entity, $format, $context);
          if (method_exists($entity->getPurchasedEntity(), 'getProduct')) {
            $product = $entity->getPurchasedEntity()->getProduct();
            if ($product instanceof ProductInterface) {
              $product_data = [
                'id' => $product->id(),
                'name' => $product->getTitle(),
                'type' => $product->bundle()
              ];
              if ($product->hasField('image')) {
                if ($product->get('image')->entity)
                  $product_data['image'] = file_create_url($product->get('image')->entity->getFileUri());
                else
                  $product_data['image'] = '';
              }
              $data['_product'] = $product_data;
            }
          }
        } else {
          $data = parent::normalize($field_item, $format, $context);
        }
        return $data;
    }
}