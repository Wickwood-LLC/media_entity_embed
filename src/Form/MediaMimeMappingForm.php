<?php

namespace Drupal\media_entity_embed\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form controller for media mime mapping forms.
 *
 * @internal
 */
class MediaMimeMappingForm extends EntityForm {

  /**
   * Constructs an MediaMimeMappingForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entityTypeManager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Ajax callback triggered by the type provider select element.
   */
  public function ajaxHandlerData(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#source-dependent', $form['source_dependent']));
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\media_entity_embed\MediaMimeMappingInterface $media_mime_mapping */
    $media_mime_mapping = $this->entity;

    $media_types = \Drupal::entityTypeManager()
        ->getStorage('media_type')
        ->loadMultiple();

    $media_type_options = [];
    foreach ($media_types as $media_type) {
      $media_type_options[$media_type->id()] = $media_type->label();
    }

    $form['id'] = [
      '#type' => 'select',
      '#title' => $this->t('Media type'),
      '#default_value' => $media_mime_mapping->id(),
      '#options' => $media_type_options,
      '#description' => $this->t("Select media type for the mime mapping."),
      '#required' => TRUE,
    ];

    $form['mime_types'] = array(
      '#type' => 'textarea',
      '#title' => t('Mime types'),
      '#default_value' => implode("\n", $media_mime_mapping->getMimeTypes()),
      '#description' => t('Enter one or more mime types. In mime type in separate line.'),
      '#required' => TRUE,
    );

    // You will need additional form elements for your custom properties.
    return $form;
  }



  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $media_mime_mapping = $this->entity;

    if ( ($media_mime_mapping->getOriginalId() !== $form_state->getValue('id')) && $this->exists($form_state->getValue('id')) ) {
      $form_state->setErrorByName('id', $this->t('Mime mapping for %id already exists.', ['%id' => $form_state->getValue('id')]));
    }

    $mime_types = $array = preg_split ('/$\R?^/m', $form_state->getValue('mime_types'));
    foreach ($mime_types as $mime_type) {
      if (!preg_match('#^[-\w]+/[-\w+]+$#', trim($mime_type))) {
        $form_state->setErrorByName('mime_types', $this->t('One or more entered mime type value are incorrect.'));
        break;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $mime_types = $array = preg_split ('/$\R?^/m', $form_state->getValue('mime_types'));
    foreach ($mime_types as $index => $mime_type) {
      $mime_types[$index] = trim($mime_type);
    }
    $form_state->setValue('mime_types', $mime_types);
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\media_entity_embed\MediaMimeMappingInterface $media_mime_mapping */
    $media_mime_mapping = $this->entity;
    $status = $media_mime_mapping->save();

    if ($status) {
      $this->messenger()->addMessage($this->t('Saved the %id mime mapping.', [
        '%id' => $media_mime_mapping->id(),
      ]));
    }
    else {
      $this->messenger()->addMessage($this->t('The %id Mime Media Mapping was not saved.', [
        '%id' => $media_mime_mapping->id(),
      ]), MessengerInterface::TYPE_ERROR);
    }

    $form_state->setRedirect('entity.media_mime_mapping.collection');
  }

  /**
   * Helper function to check whether an Mime Media Mapping configuration entity exists.
   */
  public function exists($id) {
    $entity = $this->entityTypeManager->getStorage('media_mime_mapping')->getQuery()
      ->condition('id', $id)
      ->execute();
    return (bool) $entity;
  }

}
