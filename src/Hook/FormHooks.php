<?php

namespace Drupal\media_entity_embed\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for forms.
 */
class FormHooks {

  // cspell:ignore widthx
  use StringTranslationTrait;

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_entity_embed_dialog_alter')]
  public function alterEntityEmbedDialog(array &$form, FormStateInterface $form_state, string $form_id) : void {
    $storage = $form_state->getStorage();
    // On step three of the modal form remove validation from the caption field.
    // This idea is from https://www.drupal.org/project/entity_embed/issues/3413647#comment-15434203
    // Refer that thread for more up to date solution.
    if (isset($storage['step']) && $storage['step'] === 'embed') {
      if (isset($storage['entity_element']['data-caption'])) {
        unset($form['attributes']['data-caption']['#element_validate']);
      }
    }
  }
}
