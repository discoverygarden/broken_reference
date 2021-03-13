<?php

namespace Drupal\broken_reference\Form;

use Drupal\broken_reference\Controller\BrokenReferenceStoreController;
use Drupal\broken_reference\Utility\BrokenReferenceFinder;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Report of broken entity references.
 *
 * @package Drupal\broken_reference\Form
 */
class BrokenReferenceForm extends FormBase {

  /**
   * Broken reference store controller.
   *
   * @var \Drupal\broken_reference\Controller\BrokenReferenceStoreController
   */
  protected $storeController;

  /**
   * Broken reference finder service.
   *
   * @var \Drupal\broken_reference\Utility\BrokenReferenceFinder
   */
  protected $finder;

  /**
   * Number of total broken references.
   *
   * @var int
   */
  private $totalBroken = 0;

  /**
   * Number of total different types entity references are broken.
   *
   * @var int
   */
  private $totalBrokenTypes = 0;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->finder = $container->get('broken_reference.finder');
    $instance->storeController = $container->get('broken_reference.store_controller');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'broken_entity_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $rows = $this->getRows();
    $brokenTypes = $this->finder->getBrokenReferenceTypes();
    if ($rows) {
      $form['table'] = [
        '#type' => 'table',
        '#caption' => $this->t('Broken entity references'),
        '#header' => [
          '#',
          $this->t('Entity type'),
          $this->t('Bundle'),
          $this->t('Field'),
          $this->t('Source amount'),
          $this->t('Target amount'),
        ],
        '#prefix' => $this->t('Total @amount of broken references between @types different types.', [
          '@amount' => $this->totalBroken,
          '@types' => $this->totalBrokenTypes,
        ]),
        '#sticky' => TRUE,
        '#rows' => $rows,
        '#weight' => 10,
      ];
    }
    elseif ($brokenTypes) {
      $form['info'] = [
        '#markup' => $this->t('At least @types different types of broken references found. Build report to get full details.', [
          '@types' => $brokenTypes,
        ]),
        '#weight' => 0,
      ];
    }
    else {
      $form['info'] = [
        '#markup' => $this->t('No broken entity references were found, good work! Note: quick check might not find everything, running report build is advised.'),
        '#weight' => 0,
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Build report',
      '#weight' => 5,
    ];

    return $form;
  }

  /**
   * Get report rows for form table.
   *
   * @return array
   *   Array of rows.
   */
  private function getRows() {
    $rows = [];
    $i = 0;
    foreach ($this->storeController->getBroken() as $entityType => $bundles) {
      foreach ($bundles as $bundle => $fields) {
        foreach ($fields as $field => $brokenReferences) {
          $targetCount = 0;
          foreach ($brokenReferences as $target) {
            $targetCount += count($target);
          }
          $count = count($brokenReferences);
          $this->totalBroken += $count;
          $this->totalBrokenTypes++;
          $rows[] = [
            'data' => [
              'a' => ++$i,
              'entity_type' => $entityType,
              'bundle' => $bundle,
              'field' => $field,
              'source_amount' => $count,
              'target_amount' => $targetCount,
            ],
          ];
        }
      }
    }
    return $rows;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->storeController->clearBroken();
    $operations = [];
    foreach ($this->finder->getReferenceFields() as $entityType => $config) {
      $operations[] = [
        '\Drupal\broken_reference\Batch\BrokenReferenceBatch::batchRun',
        [$entityType, $config],
      ];
    }
    $batch = [
      'title' => $this->t('Finding broken entity references...'),
      'operations' => $operations,
      'init_message' => $this->t('Commencing'),
      'progress_message' => $this->t('Processed @current out of @total entity types.'),
      'error_message' => $this->t('An error occurred during processing'),
      'finished' => '\Drupal\broken_reference\Batch\BrokenReferenceBatch::batchFinished',
    ];

    batch_set($batch);
  }

}
