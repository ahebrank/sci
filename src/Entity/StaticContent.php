<?php

namespace Drupal\sci\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\file\Entity\File;
use Drupal\Core\Archiver\Zip;

/**
 * Defines the Static content entity.
 *
 * @ingroup sci
 *
 * @ContentEntityType(
 *   id = "static_content",
 *   label = @Translation("Static content"),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\sci\StaticContentListBuilder",
 *     "views_data" = "Drupal\sci\Entity\StaticContentViewsData",
 *     "form" = {
 *       "default" = "Drupal\sci\Form\StaticContentForm",
 *       "add" = "Drupal\sci\Form\StaticContentForm",
 *       "edit" = "Drupal\sci\Form\StaticContentForm",
 *       "delete" = "Drupal\sci\Form\StaticContentDeleteForm",
 *     },
 *     "access" = "Drupal\sci\StaticContentAccessControlHandler",
 *     "route_provider" = {
 *       "html" = "Drupal\sci\StaticContentHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "static_content",
 *   admin_permission = "administer static content entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "canonical" = "/admin/structure/static_content/{static_content}",
 *     "add-form" = "/admin/structure/static_content/add",
 *     "edit-form" = "/admin/structure/static_content/{static_content}/edit",
 *     "delete-form" = "/admin/structure/static_content/{static_content}/delete",
 *     "collection" = "/admin/structure/static_content",
 *   },
 * )
 */
class StaticContent extends ContentEntityBase implements StaticContentInterface {

  use EntityChangedTrait;

  /**
   * How many files to extract per set.
   *
   * @var int
   */
  protected $filesPerSet = 20;

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * Return the base URI.
   */
  public function getBaseUri() {
    return $this->get('base_uri')->value;
  }

  /**
   * Return the URL.
   */
  public function getUrl($real_url = FALSE) {
    $url = $this->get('url')->value;
    return $real_url ? \file_create_url($url) : $url;
  }

  /**
   * Return iframe width.
   */
  public function getWidth() {
    return $this->get('width')->value;
  }

  /**
   * Return iframe height.
   */
  public function getHeight() {
    return $this->get('height')->value;
  }

  /**
   * Return autoheight.
   */
  public function isAutoheight() {
    return $this->get('autoheight')->value;
  }

  /**
   * Create the static zip.
   *
   * @param \Drupal\file\Entity\File $file
   *   Uploaded (zip) file.
   */
  public function saveZip(File $file) {
    // @TODO: inject
    $real_path = \Drupal::service('file_system')->realpath($file->getFileUri());
    $zip = new Zip($real_path);
    if ($zip) {
      $zip_files = $zip->listContents();

      // Attempt to find an index.html file.
      $index_path = "";
      foreach ($zip_files as $file) {
        if (strpos($file, 'index.html')) {
          $index_path = $file;
          break;
        }
      }
      // Base URI is a hash of all the files in the archive.
      $base_uri = "public://static/" . md5(implode('|', $zip_files));

      // Set new path info.
      $this->set('base_uri', $base_uri);
      $url = $base_uri . '/' . $index_path;
      $this->set('url', $url);
      $this->save();

      // Remove any file already there.
      self::cleanOldContent($this->getBaseUri());

      $sets = $this->splitFiles($zip_files);
      $set_count = count($sets);
      foreach ($sets as $set) {
        $ops[] = [
          '\Drupal\sci\Entity\StaticContent::extractSet',
          [$zip, $base_uri, $set, $set_count],
        ];
      }
      batch_set([
        'title' => t('Extracting zip files'),
        'operations' => $ops,
        'finished' => '\Drupal\sci\Entity\StaticContent::batchFinished',
      ]);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the Static content entity.'))
      ->setSettings([
        'max_length' => 50,
        'text_processing' => 0,
      ])
      ->setDefaultValue('')
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -4,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['base_uri'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Base URI'))
      ->setDescription(t('The path of the static content directory (directory name is a hash of the archive contents).'));

    $fields['url'] = BaseFieldDefinition::create('string')
      ->setLabel(t('URL'))
      ->setDescription(t('The URL of the static content (directory or index).'));

    $fields['width'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Iframe width'))
      ->setDescription(t('Width of iframe element, in a valid attribute format (px or %).'))
      ->setDefaultValue('')
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ]);

    $fields['height'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Iframe height'))
      ->setDescription(t('Height of iframe element, in a valid attribute format (px or %).'))
      ->setDefaultValue('')
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -4,
      ]);

    $fields['autoheight'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Autoheight'))
      ->setDescription(t('Attempt to make height of iframe the height of the content when content loaded.'))
      ->setDefaultValue('')
      ->setDisplayOptions('form', [
        'type' => 'checkbox',
        'weight' => -4,
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public static function postDelete(EntityStorageInterface $storage, array $entities) {
    parent::postDelete($storage, $entities);

    foreach ($entities as $entity) {
      self::cleanOldContent($entity->getBaseUri());
    }
  }

  /**
   * Remove a directory on the filesystem.
   */
  public static function cleanOldContent($base_uri) {
    if ($base_uri && strpos($base_uri, 'public://static/') === 0) {
      $path = \Drupal::service('file_system')->realpath($base_uri);
      self::deleteDir($path);
    }
  }

  /**
   * Remove a directory and all its children.
   */
  protected static function deleteDir($path) {
    if (!is_dir($path)) {
      return FALSE;
    }
    if (substr($path, strlen($path) - 1, 1) != '/') {
      $path .= '/';
    }
    $files = glob($path . '{,.}[!.,!..]*', GLOB_MARK | GLOB_BRACE);
    foreach ($files as $file) {
      if (is_dir($file)) {
        self::deleteDir($file);
      }
      else {
        unlink($file);
      }
    }
    rmdir(rtrim($path, '/'));
  }

  /**
   * Divide up the files into sets.
   */
  protected function splitFiles($files) {
    $sets = [];
    for ($j = 0; $j < count($files); $j += $this->filesPerSet) {
      if ($j == 0) {
        $i = 0;
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
  public static function extractSet($zip, $path, $files, $set_count, &$context) {
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['results'] = [];
    }

    $zip->extract($path, $files);

    $context['results'] = array_merge($context['results'], $files);
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
      $message = count($results) . ' ' . t('files extracted.');
    }
    else {
      $message = t('There was an error during zip extraction.');
    }
    drupal_set_message($message);
  }

}
