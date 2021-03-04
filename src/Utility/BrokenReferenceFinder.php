<?php

namespace Drupal\broken_reference\Utility;

use Drupal\Core\Entity\FieldableEntityInterface;

class BrokenReferenceFinder {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityBundleInfo;

  public function __construct() {
    $this->entityTypeManager = \Drupal::entityTypeManager();
    $this->entityFieldManager = \Drupal::service('entity_field.manager');
    $this->entityBundleInfo = \Drupal::service('entity_type.bundle.info');
  }

  public function getReferenceFields() {
    $build = [];
    foreach ($this->entityFieldManager->getFieldMapByFieldType('entity_reference') as $entityType => $references) {
      $entityDefinition = $this->entityTypeManager->getDefinition($entityType);
      $bundleKey = $entityDefinition->getKey('bundle');
      $build[$entityType]['bundle_key'] = $bundleKey;
      foreach ($references as $field => $fieldInfo) {
        if ($field == $bundleKey) {
          continue;
        }

        // Core bug?
        if ($entityType == 'comment' && $field == 'entity_id') {
          continue;
        }

        $bundles = array_keys($fieldInfo['bundles']);
        foreach ($bundles as $bundle) {
          $fieldDefinition = $this->entityFieldManager->getFieldDefinitions($entityType, $bundle)[$field];
          if ($fieldDefinition->isComputed()) {
            continue;
          }
          // Because of course getTargetEntityTypeId() returns wrong.
          $targetEntityTypeId = $fieldDefinition->getItemDefinition()->getSetting('target_type');
          $targetEntityTypeDefinition = $this->entityTypeManager->getDefinition($targetEntityTypeId);
          if (!$targetEntityTypeDefinition->entityClassImplements(FieldableEntityInterface::class)) {
            continue;
          }
          $targetIdKey = $targetEntityTypeDefinition->getKey('uuid');

          $build[$entityType]['bundles'][$bundle][$field] = $targetIdKey;
        }
      }
    }
    return $build;
  }

  public function getQueryResults($entityType, $config, $limit = FALSE) {
    $storage = $this->entityTypeManager->getStorage($entityType);
    $bundleKey = $config['bundle_key'];
    $results = [];
    foreach ($config['bundles'] as $bundle => $fieldSet) {
      foreach ($fieldSet as $field => $targetIdKey) {
        $query = $storage->getQuery()
          ->accessCheck(FALSE);
        if ($bundleKey) {
          $query->condition($bundleKey, $bundle);
        }
        $query
          ->condition("{$field}", 0, '>')
          ->notExists("{$field}.entity.{$targetIdKey}");
        if ($limit) {
          $query->range(0, 1);
        }
        $results += $query->execute();
      }
    }
    return $results;
  }

  public function brokenReferencesExists() {
    foreach ($this->getReferenceFields() as $entityType => $config) {
      if ($this->getQueryResults($entityType, $config, TRUE)) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
