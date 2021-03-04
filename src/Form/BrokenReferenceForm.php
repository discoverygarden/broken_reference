<?php

namespace Drupal\broken_reference\Form;

use Drupal\broken_reference\Controller\BrokenReferenceStoreController;
use Drupal\broken_reference\Utility\BrokenReferenceFinder;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class BrokenReferenceForm extends FormBase {

  public function __construct() {
    $this->store = new BrokenReferenceStoreController();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
    );
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
    $rows = [];
    $totalBroken = 0;
    foreach ($this->store->getBroken() as $entityType => $bundles) {
      foreach ($bundles as $bundle => $fields) {
        foreach ($fields as $field => $brokenReferences) {
          $targetCount = 0;
          foreach ($brokenReferences as $source => $target) {
            $targetCount += count($target);
          }
          $count = count($brokenReferences);
          $totalBroken += $count;
          $rows[] = [
            'data' => [
              'entity_type' => $entityType,
              'bundle' => $bundle,
              'field' => $field,
              'source_amonut' => $count,
              'target_amount' => $targetCount,
            ]
          ];
        }
      }
    }


    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Run',
    ];

    $form['table'] = [
      '#type' => 'table',
      '#caption' => $this->t('Broken entity references'),
      '#header' => [
        $this->t('Entity type'),
        $this->t('Bundle'),
        $this->t('Field'),
        $this->t('Source amount'),
        $this->t('Target amount'),
      ],
      '#prefix' => $this->t('Total amount of broken target references: @amount', [
        '@amount' => $totalBroken,
      ]),
      '#sticky' => TRUE,
      '#rows' => $rows,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $finder = new BrokenReferenceFinder();
    $this->store->clearBroken();;
    $operations = [];
    foreach ($finder->getReferenceFields() as $entityType => $config) {
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
