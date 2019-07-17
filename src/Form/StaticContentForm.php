<?php

namespace Drupal\sci\Form;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form controller for Static content edit forms.
 *
 * @ingroup sci
 */
class StaticContentForm extends ContentEntityForm {

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
  public function save(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;

    parent::save($form, $form_state);

    $archive_fid = $form_state->getValue(['static_archive', '0']);
    // @TODO: inject this service.
    if (!empty($archive_fid) && $file = \Drupal::entityTypeManager()->getStorage('file')->load($archive_fid)) {
      if ($entity->saveZip($file)) {
        $form_state->setRedirect('entity.static_content.canonical', ['static_content' => $entity->id()]);
      }
    }
  }

}
