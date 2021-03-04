<?php

namespace Drupal\broken_reference\Batch;

/**
 * Batch functions for gathering broken entity reference information.
 *
 * @package Drupal\broken_reference\Batch
 */
class BrokenReferenceBatch {

  /**
   * Finished callback for reference batches.
   *
   * @param bool $success
   *   A boolean indicating whether the batch has completed successfully.
   * @param array $results
   *   The value set in $context['results'] by callback_batch_operation().
   * @param array $operations
   *   If $success is FALSE, contains the operations that remained unprocessed.
   */
  public static function batchFinished(bool $success, array $results, array $operations) {
    /** @var \Drupal\Core\Messenger\MessengerInterface $messenger */
    $messenger = \Drupal::service('messenger');
    if ($success) {
      $messenger->addMessage(t('All broken entity references has been processed.'));
    }
    else {
      $error_operation = reset($operations);
      $message = t('An error occurred while processing %error_operation with arguments: @arguments', [
        '%error_operation' => $error_operation[0],
        '@arguments' => print_r($error_operation[1], TRUE),
      ]);
      $messenger->addError($message);
    }
  }

  /**
   * Batch API callback; Find broken entity references.
   *
   * @param string $entityType
   *   Entity type to look up.
   * @param array $config
   *   Bundle, bundle key, fields and target entity field to look up.
   * @param mixed $context
   *   The batch current context.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function batchRun(string $entityType, array $config, &$context) {
    $entityTypeManager = \Drupal::entityTypeManager();
    $finder = \Drupal::service('broken_reference.finder');
    $storeController = \Drupal::service('broken_reference.store_controller');

    if (!isset($context['sandbox']['progress'])) {
      $results = $finder->getQueryResults($entityType, $config);
      $count = count($results);
      if (!$count) {
        $context['finished'] = 1;
        return;
      }
      $context['finished'] = 0;
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['entities'] = $results;
      $context['sandbox']['max'] = $count;
    }

    $limit = 30;
    $remaining = $context['sandbox']['entities'];
    $chunk = array_splice($remaining, 0, $limit);
    $context['sandbox']['entities'] = $remaining;

    $broken = [];

    $storage = $entityTypeManager->getStorage($entityType);
    $context['message'] = t("Validating @pointer of @total broken target references in entity type @entity_type", [
      '@pointer' => $context['sandbox']['progress'],
      '@total' => $context['sandbox']['max'],
      '@entity_type' => $entityType,
    ]);
    /** @var \Drupal\Core\Entity\ContentEntityInterface[] $entities */
    $entities = $storage->loadMultiple($chunk);
    foreach ($entities as $entity) {
      $context['sandbox']['progress']++;
      $bundle = $entity->bundle();
      $fieldsToCheck = array_keys($config['bundles'][$bundle]);
      foreach ($fieldsToCheck as $fieldToCheck) {
        foreach ($entity->get($fieldToCheck) as $field) {
          if ($field->target_id && !$field->entity) {
            $context['results'][] = $field->target_id;
            $broken[$entityType][$bundle][$fieldToCheck][$entity->id()][] = $field->target_id;
          }
        }
      }
    }

    if ($broken) {
      $storeController->addBroken($broken);
    }

    if ($context['sandbox']['progress'] < $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
    }
    else {
      $context['finished'] = 1;
    }

  }

}
