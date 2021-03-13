<?php

namespace Drupal\broken_reference\Utility;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;

/**
 * Utility to find all broken entity references.
 *
 * @package Drupal\broken_reference\Utility
 */
class BrokenReferenceFinder {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityBundleInfo;

  /**
   * BrokenReferenceFinder constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entityBundleInfo
   *   The entity bundle info service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, EntityFieldManagerInterface $entityFieldManager, EntityTypeBundleInfoInterface $entityBundleInfo) {
    $this->entityTypeManager = $entityTypeManager;
    $this->entityFieldManager = $entityFieldManager;
    $this->entityBundleInfo = $entityBundleInfo;
  }

  /**
   * Get all possible content fields where entity references are used.
   *
   * @return array
   *   Array containing fields where references are used.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getReferenceFields(): array {
    $build = [];
    foreach ($this->entityFieldManager->getFieldMapByFieldType('entity_reference') as $entityType => $references) {
      $entityDefinition = $this->entityTypeManager->getDefinition($entityType);
      $bundleKey = $entityDefinition->getKey('bundle');
      $validReferences = FALSE;
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
          $validReferences = TRUE;
        }
      }

      if (!$validReferences) {
        unset($build[$entityType]);
      }
    }
    return $build;
  }

  /**
   * Get entities with broken references.
   *
   * @param string $entityType
   *   Entity type to look up.
   * @param array $config
   *   Bundle, bundle key, fields and target entity field to look up.
   * @param bool $limit
   *   Use limit if just a quick lookup is wanted.
   *
   * @return int[]
   *   Array of entity IDs.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getQueryResults(string $entityType, array $config, $limit = FALSE): array {
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

  /**
   * Get rough estimate of possible different types of broken references.
   *
   * @return int
   *   Rough estimate of broken reference types.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getBrokenReferenceTypes(): int {
    $types = 0;
    foreach ($this->getReferenceFields() as $entityType => $config) {
      $types += count($this->getQueryResults($entityType, $config, TRUE));
    }
    return $types;
  }

}
