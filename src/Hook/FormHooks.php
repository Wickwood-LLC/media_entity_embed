<?php

namespace Drupal\media_entity_embed\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for forms.
 */
class FormHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_entity_embed_dialog_alter')]
  public function alterEntityEmbedDialog(array &$form, FormStateInterface $form_state, string $form_id) : void {
    $storage = $form_state->getStorage();
    if (isset($storage['step']) && $storage['step'] === 'embed') {
      // We will replace caption field brought by the entity_embed module with
      // with text_format field to get CKEditor.
      // Then in the validation handler will copy value from that replacement field back to the data-caption attributes.
      // @see self::validateEntityEmbedD()
      if (isset($form['attributes']['data-caption'])) {
        $caption = $storage['entity_element']['data-caption'] ?? $storage['entity_element']['data-caption-editor'];
        if ($form['attributes']['data-caption']['#type'] != 'value') {
          $form['attributes']['data-caption']['#type'] = 'value';
        }
        if (empty($caption) && empty($storage['entity_element']['data-entity-embed-display-settings'])) {
          // Display settings is empty means new embedding, not editing existing one.
          // Copy value from Long Caption field as default caption.
          $caption = $storage['entity']->get('field_long_caption')->value;
        }
        $form['attributes']['data-caption-editor'] = [
          '#title' => $this->t('Caption'),
          '#type' => 'text_format',
          '#rows' => 3,
          '#default_value' => $caption,
          // Text format used here should only allow hardcoded tags in
          // \Drupal\filter\Plugin\Filter\FilterCaption::process().
          '#format' => 'caption_html',
          '#allowed_formats' => ['caption_html'],
          '#attached' => [
            'library' => [
              // Remove this once https://www.drupal.org/project/drupal/issues/3351603 is solved
              'media_entity_embed/ckeditor-fix',
            ]
          ]
        ];

        // Make sure to initialize array if not.
        if (!isset($form['#validate'])) {
          $form['#validate'] = [];
        }
        // To run before any other validation handler.
        array_unshift($form['#validate'], [static::class, 'validateEntityEmbedDialog']);
      }
    }
  }

  public static function validateEntityEmbedDialog(array &$form, FormStateInterface $form_state) {
    if (isset($form['attributes']['data-caption-editor'])) {
      $caption_value = $form_state->getValue(['attributes', 'data-caption-editor', 'value']);
      // Copy as original data-caption attribute value.
      $form_state->setValue(['attributes', 'data-caption'], $caption_value);
    }
  }

}
