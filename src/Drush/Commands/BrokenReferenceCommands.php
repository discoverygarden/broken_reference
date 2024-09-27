<?php

namespace Drupal\broken_reference\Drush\Commands;

use Drupal\broken_reference\Controller\BrokenReferenceStoreController;
use Drupal\broken_reference\Utility\BrokenReferenceFinder;
use Drupal\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Drush\Attributes as CLI;
use Drush\Attributes\HookSelector;
use Drush\Commands\DrushCommands;

class BrokenReferenceCommands extends DrushCommands {

  /**
   * The broken reference finder.
   *
   * @var \Drupal\broken_reference\Utility\BrokenReferenceFinder
   */
  protected BrokenReferenceFinder $finder;

  /**
   * The broken reference store controller.
   *
   * @var \Drupal\broken_reference\Controller\BrokenReferenceStoreController
   */
  protected BrokenReferenceStoreController $controller;

  /**
   * Constructs a new BrokenReferenceCommands object.
   *
   * @param \Drupal\broken_reference\Utility\BrokenReferenceFinder $finder
   *   The broken reference finder.
   * @param \Drupal\broken_reference\Controller\BrokenReferenceStoreController $controller
   *   The broken reference store controller.
   */
  public function __construct(BrokenReferenceFinder $finder, BrokenReferenceStoreController $controller) {
    $this->finder = $finder;
    $this->controller = $controller;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('broken_reference.finder'),
      $container->get('broken_reference.store_controller'),
    );
  }

  /**
   * Finds broken references via CLI.
   */
  #[CLI\Command(name: 'broken_reference:findBroken', aliases: ['br:fb'])]
  #[CLI\Usage(name: 'broken_reference:findBroken', description: 'Finds broken references via CLI.')]
  #[HookSelector(name: 'islandora-drush-utils-user-wrap')]
  public function findBroken(): void {
    $this->controller->clearBroken();
    foreach ($this->finder->getReferenceFields() as $entityType => $config) {
      $operations[] = [
        '\Drupal\broken_reference\Batch\BrokenReferenceBatch::batchRun',
        [$entityType, $config],
      ];
    }
    $batch = [
      'title' => 'Finding broken entity references...',
      'operations' => $operations,
      'init_message' => 'Commencing',
      'progress_message' => 'Processed @current out of @total entity types.',
      'error_message' => 'An error occurred during processing',
      'finished' => '\Drupal\broken_reference\Batch\BrokenReferenceBatch::batchFinished',
    ];
    drush_op('batch_set', $batch);
    drush_op('drush_backend_batch_process');

    file_put_contents('/tmp/broken.log', json_encode($this->controller->getBroken()));
  }

}
