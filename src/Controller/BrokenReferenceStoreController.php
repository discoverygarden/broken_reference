<?php

namespace Drupal\broken_reference\Controller;

use Drupal\Core\TempStore\PrivateTempStoreFactory;

/**
 * Control stored broken references.
 *
 * @package Drupal\broken_reference\Controller
 */
class BrokenReferenceStoreController {

  /**
   * The private temp store factory service.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $storeController;

  /**
   * BrokenReferenceStoreController constructor.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $storeFactory
   *   The private temp store factory service.
   */
  public function __construct(PrivateTempStoreFactory $storeFactory) {
    $this->storeController = $storeFactory->get('broken_reference');
  }

  /**
   * Clear current state of broken references.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function clearBroken() {
    $this->storeController->delete('broken');
  }

  /**
   * Store more broken references.
   *
   * @param array $new
   *   Broken references to add.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  public function addBroken(array $new) {
    $broken = $this->getBroken();
    foreach ($new as $entityType => $bundles) {
      foreach ($bundles as $bundle => $fields) {
        foreach ($fields as $field => $brokenReferences) {
          foreach ($brokenReferences as $source => $target) {
            $broken[$entityType][$bundle][$field][$source] = $target;
          }
        }
      }
    }
    $this->storeController->set('broken', $broken);
  }

  /**
   * Get all broken references.
   *
   * @return array
   *   Array of broken entity references.
   */
  public function getBroken() {
    return $this->storeController->get('broken') ?: [];
  }

}
