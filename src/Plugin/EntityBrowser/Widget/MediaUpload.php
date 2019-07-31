<?php

namespace Drupal\media_entity_embed\Plugin\EntityBrowser\Widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_browser\Plugin\EntityBrowser\Widget\Upload as FileUpload;
use Drupal\media\MediaInterface;

/**
 * Uses upload to create media entities.
 *
 * @EntityBrowserWidget(
 *   id = "media_entity_embed_media_upload",
 *   label = @Translation("Upload media files (MEE)"),
 *   description = @Translation("Upload widget that will create media entities automatically by mapping mime type of the uploaded files."),
 *   auto_select = FALSE
 * )
 */
class MediaUpload extends FileUpload {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'extensions' => 'jpg jpeg png gif',
      'media_type' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array &$original_form, FormStateInterface $form_state, array $aditional_widget_parameters) {

    $form = parent::getForm($original_form, $form_state, $aditional_widget_parameters);
    $form['upload']['#upload_validators']['file_validate_extensions'] = [$this->configuration['extensions']];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareEntities(array $form, FormStateInterface $form_state) {
    $files = parent::prepareEntities($form, $form_state);

    $media_items = [];
    foreach ($files as $file) {
      $media_type = NULL;
      $mime_type = $file->getMimeType();
      $media_mime_mappings = $this->entityTypeManager->getStorage('media_mime_mapping')->loadMultiple();

      foreach ($media_mime_mappings as $media_mime_mapping) {
        if ($media_mime_mapping->containsMimeType($mime_type)) {
          /** @var \Drupal\media\MediaTypeInterface $media_type */
          $media_type = $this->entityTypeManager
            ->getStorage('media_type')
            ->load($media_mime_mapping->id());
          break;
        }
      }
      if (!empty($media_type)) {
        /** @var \Drupal\media\MediaInterface $image */
        $media = $this->entityTypeManager->getStorage('media')->create([
          'bundle' => $media_type->id(),
          $media_type->getSource()->getConfiguration()['source_field'] => $file,
        ]);
        $media_items[] = $media;
      }
    }

    return $media_items;
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array &$element, array &$form, FormStateInterface $form_state) {
    if (!empty($form_state->getTriggeringElement()['#eb_widget_main_submit'])) {
      $media_items = $this->prepareEntities($form, $form_state);
      array_walk(
        $media_items,
        function (MediaInterface $media) {
          $media->save();
        }
      );

      $this->selectEntities($media_items, $form_state);
      $this->clearFormValues($element, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);
    return $form;
  }

}
