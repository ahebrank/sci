<?php

namespace Drupal\sci\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\Core\Archiver\Zip;
use Drupal\sci\Entity\StaticContent;

/**
 * Form controller for Static content edit forms.
 *
 * @ingroup sci
 */
class StaticContentForm extends ContentEntityForm {

  /**
   * How many files to extract from the zip per set.
   *
   * @var int
   */
  protected $filesPerSet = 10;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var $entity \Drupal\sci\Entity\StaticContent */
    $form = parent::buildForm($form, $form_state);

    $form['static_archive'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Static content archive'),
      '#title' => $this->t('Upload a zip file of your static content. Should contain an index.html entrypoint.'),
      '#upload_location' => 'temporary://',
      '#upload_validators' => [
        'file_validate_extensions' => ['zip'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $entity = $this->entity;

    $archive_fid = $form_state->getValue(['static_archive', '0']);
    // @TODO: inject this service.
    if (!empty($archive_fid) && $file = \Drupal::entityTypeManager()->getStorage('file')->load($archive_fid)) {
      // Remove any file already there.
      StaticContent::cleanOldContent($entity->getBaseUri());

      if ($ops = $this->getZipOps($entity, $file)) {
        $entity->save();
        $form_state->setRedirect('entity.static_content.canonical', ['static_content' => $entity->id()]);

        batch_set([
          'title' => $this->t('Extracting zip files'),
          'operations' => $ops,
          'finished' => '\Drupal\sci\Form\StaticContentForm::batchFinished',
        ]);
      }
    }
  }

  /**
   * Create the static zip.
   */
  protected function getZipOps($entity, File $file) {
    // @TODO: inject
    $real_path = \Drupal::service('file_system')->realpath($file->getFileUri());
    $zip = new Zip($real_path);
    if ($zip) {
      $zip_files = $zip->listContents();

      // Attempt to find an index.html file.
      $index_path = "";
      foreach ($zip_files as $file) {
        $basename = basename($file);
        if ($basename === 'index.html') {
          $index_path = $file;
          break;
        }
      }

      // Base URI is a hash of all the files in the archive.
      $base_uri = "public://static/" . md5($entity->id() . '|' . implode('|', $zip_files));
      if (!file_prepare_directory($base_uri, FILE_CREATE_DIRECTORY)) {
        $message = $this->t('Unable to create directory: @dir', [
          '@dir' => $base_uri,
        ]);
        \Drupal::messenger()
          ->addError($message);
        return FALSE;
      }

      $ops = [];
      $sets = $this->splitFiles($zip_files);
      $set_count = count($sets);
      foreach ($sets as $set) {
        $ops[] = [
          '\Drupal\sci\Form\StaticContentForm::extractSet',
          [$real_path, $base_uri, $set, $set_count],
        ];
      }
      // $ops[] = [
      //   '\Drupal\sci\Form\StaticContentForm::extractSet',
      //   [$real_path, $base_uri, $zip_files, 1],
      // ];

      // Set new path info.
      $entity->set('base_uri', $base_uri);
      $url = $base_uri . '/' . $index_path;
      $entity->set('url', $url);

      return $ops;
    }
    return FALSE;
  }

  /**
   * Divide up the files into sets.
   */
  protected function splitFiles($files) {
    $sets = [];
    $i = 0;
    for ($j = 0; $i < count($files); $j += $this->filesPerSet) {
      if ($j == 0) {
        continue;
      }
      $sets[] = array_slice($files, $i, $this->filesPerSet);
      $i = $j;
    }
    return $sets;
  }

  /**
   * Extraction callback.
   */
  public static function extractSet($zip_path, $path, $files, $set_count, &$context) {
    $zip = new Zip($zip_path);
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['results'] = [];
    }

    // Option to run in a single op.
    if ($set_count == 1) {
      $result = $zip->extract($path);
    }
    else {
      $result = $zip->extract($path, $files);
    }

    if ($result) {
      $context['results'] = array_merge($context['results'], array_values($files));
      $context['message'] = 'Extracted ' . count($files) . ' files to ' . $path . ': ' . implode(', ', $files);
    }
    else {
      $context['message'] = 'Unable to extract set.';
    }
    $context['sandbox']['progress']++;
    // Inform the batch engine that we are not finished,
    // and provide an estimation of the completion level we reached.
    if ($context['sandbox']['progress'] != $set_count) {
      $context['finished'] = $context['sandbox']['progress'] / $set_count;
    }
  }

  /**
   * Batch finished callback.
   */
  public static function batchFinished($success, $results, $operations) {
    if ($success) {
      $message = t("Zip extraction complete.");
      \Drupal::messenger()
        ->addStatus($message);
    }
    else {
      $error_operation = reset($operations);
      $message = t('An error occurred while processing %error_operation with arguments: @arguments', [
        '%error_operation' => $error_operation[0],
        '@arguments' => print_r($error_operation[1], TRUE),
      ]);
      \Drupal::messenger()
        ->addError($message);
    }
  }

}
