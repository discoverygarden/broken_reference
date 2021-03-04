<?php

namespace Drupal\broken_reference\Controller;

class BrokenReferenceStoreController {

  /**
   * @var \Drupal\Core\TempStore\PrivateTempStore
   */
  protected $store;

  public function __construct() {
    /** @var \Drupal\Core\TempStore\PrivateTempStoreFactory $store */
    $store = \Drupal::service('tempstore.private');
    $this->store = $store->get('broken_reference');
  }

  public function clearBroken() {
    $this->store->delete('broken');
  }

  public function addBroken(array $new) {
    $broken = $this->getBroken();
    foreach ($new as $entityType => $bundles) {
      foreach ($bundles as $bundle => $fields) {
        foreach ($fields as $field => $brokenReferences) {
          foreach ($brokenReferences as $source => $target) {
            $broken[$entityType][$bundle][$field][$source][] = $target;
          }
        }
      }
    }
    $this->store->set('broken', $broken);
  }

  public function getBroken() {
    return $this->store->get('broken') ?: [];
  }

}

