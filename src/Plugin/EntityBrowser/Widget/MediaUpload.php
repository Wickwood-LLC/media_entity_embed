<?php

namespace Drupal\media_entity_embed\Plugin\EntityBrowser\Widget;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\entity_browser\WidgetBase;
use Drupal\media\MediaInterface;
use Drupal\inline_entity_form\Element\InlineEntityForm;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Utility\Token;
use Drupal\entity_browser\WidgetValidationManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Uses upload to create media entities.
 *
 * @EntityBrowserWidget(
 *   id = "media_entity_embed_media_upload",
 *   label = @Translation("Upload media files (MEE)"),
 *   description = @Translation("Upload widget that will create media entities automatically by file extensions of the uploaded files."),
 *   auto_select = FALSE
 * )
 */
class MediaUpload extends WidgetBase {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $token;

  /**
   * Upload constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   Event dispatcher service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\entity_browser\WidgetValidationManager $validation_manager
   *   The Widget Validation Manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Utility\Token $token
   *   The token service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EventDispatcherInterface $event_dispatcher, EntityTypeManagerInterface $entity_type_manager, WidgetValidationManager $validation_manager, ModuleHandlerInterface $module_handler, Token $token) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $event_dispatcher, $entity_type_manager, $validation_manager);
    $this->moduleHandler = $module_handler;
    $this->token = $token;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('event_dispatcher'),
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.entity_browser.widget_validation'),
      $container->get('module_handler'),
      $container->get('token')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'upload_location' => 'public://',
      'first_step_button_text' => $this->t('Create media items'),
      'submit_text' => $this->t('Save and continue'),
      'media_types' => array(),
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function getForm(array &$original_form, FormStateInterface $form_state, array $additional_widget_parameters) {
    $form = parent::getForm($original_form, $form_state, $additional_widget_parameters);

    $form['#prefix'] = '<div id="mee-upload-widget">';
    $form['#suffix'] = '</div>';

    if ($entities = $form_state->get('media_items')) {
      foreach ($entities as $entity) {
        /** @var \Drupal\Core\Entity\EntityInterface $entity */
        $form['entities'][$entity->uuid()] = [
          '#type' => 'inline_entity_form',
          '#entity_type' => $entity->getEntityTypeId(),
          '#bundle' => $entity->bundle(),
          '#default_value' => $entity,
          '#form_mode' => 'default',
        ];
      }

    }
    else {
      $field_cardinality = $form_state->get(['entity_browser', 'validators', 'cardinality', 'cardinality']);
      $upload_validators = $form_state->has(['entity_browser', 'widget_context', 'upload_validators']) ? $form_state->get(['entity_browser', 'widget_context', 'upload_validators']) : [];
      $form['upload'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('Choose a file'),
        '#title_display' => 'invisible',
        '#upload_location' => $this->token->replace($this->configuration['upload_location']),
        '#multiple' => FALSE,
        '#upload_validators' => array_merge([
          'file_validate_extensions' => [implode(' ', $this->getAllowedFileExtensions())],
        ], $upload_validators),
      ];

      unset($form['actions']['submit']);
      $form['actions']['submit_upload'] = [
        '#type' => 'submit',
        '#value' => $this->configuration['first_step_button_text'],
        '#button_type' => 'primary',
        '#ajax' => [
          'wrapper' => 'mee-upload-widget',
          'callback' => [$this, 'onEdit'],
          'effect' => 'fade',
        ],
        '#submit' => [
          [$this, 'submitUpload'],
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareEntities(array $form, FormStateInterface $form_state) {
    $files = [];
    foreach ($form_state->getValue(['upload'], []) as $fid) {
      $files[] = $this->entityTypeManager->getStorage('file')->load($fid);
    }

    $media_items = [];
    foreach ($files as $file) {
      /** @var \Drupal\media\MediaTypeInterface $media_type */
      $matched_media_type = NULL;

      $media_types = \Drupal::entityTypeManager()->getStorage('media_type')->loadMultiple(array_keys(array_filter($this->configuration['media_types'])));
      foreach ($media_types as $media_type) {
        $source_field_definition = $this->getSourceFieldDefinitionForMediaType($media_type);
        if ($source_field_definition) {
          $file_extensions = $source_field_definition->getSetting('file_extensions');
          // file_validate_extensions() will return empty array if file extension matches
          if (empty(file_validate_extensions($file, $file_extensions))) {
            $matched_media_type = $media_type;
            break;
          }
        }
      }

      if (!empty($matched_media_type)) {
        /** @var \Drupal\media\MediaInterface $image */
        $media = $this->entityTypeManager->getStorage('media')->create([
          'bundle' => $matched_media_type->id(),
          $matched_media_type->getSource()->getConfiguration()['source_field'] => $file,
        ]);
        $media_items[] = $media;
      }
    }

    return $media_items;
  }

  /**
   * Submit callback for first step.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form object.
   */
  public function submitUpload(array $form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
    $media_items = $this->prepareEntities($form, $form_state);
    array_walk(
      $media_items,
      function (MediaInterface $media) {
        $media->save();
      }
    );
    $form_state->set('media_items', $media_items);
  }

  /**
   * Ajax callback triggered when hitting the edit button.
   *
   * @param array $form
   *   The form.
   *
   * @return array
   *   Returns the entire form.
   */
  public function onEdit(array $form) {
    return $form['widget'];
  }

  /**
   * {@inheritdoc}
   */
  public function submit(array &$element, array &$form, FormStateInterface $form_state) {
    $media_entities = $this->prepareEntitiesFromForm($form, $form_state);
    foreach ($media_entities as $id => $media_entity) {
      $source_field = $media_entity->getSource()->getConfiguration()['source_field'];
      $file = $media_entity->{$source_field}->entity;
      $media_entity->save();
      $media_entities[$id] = $media_entity;
    }

    if (!empty(array_filter($media_entities))) {
      $this->selectEntities($media_entities, $form_state);
      //$this->clearFormValues($element, $form_state);
    }
  }

  /**
   * Prepares entities from the form.
   *
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\media\MediaInterface[]
   *   The prepared media entities.
   */
  protected function prepareEntitiesFromForm(array $form, FormStateInterface $form_state) {
    $media_entities = [];
    foreach (Element::children($form['widget']['entities']) as $key) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = $form['widget']['entities'][$key]['#entity'];
      $inline_entity_form_handler = InlineEntityForm::getInlineFormHandler($entity->getEntityTypeId());
      $inline_entity_form_handler->entityFormSubmit($form['widget']['entities'][$key], $form_state);
      $media_entities[] = $entity;
    }
    return $media_entities;
  }

  /**
   * Clear values from upload form element.
   *
   * @param array $element
   *   Upload form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   */
  protected function clearFormValues(array &$element, FormStateInterface $form_state) {
    // We propagated entities to the other parts of the system. We can now remove
    // them from our values.
    $form_state->setValueForElement($element['upload']['fids'], '');
    NestedArray::setValue($form_state->getUserInput(), $element['upload']['fids']['#parents'], '');
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {

    $form['upload_location'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Upload location'),
      '#default_value' => $this->configuration['upload_location'],
    ];

    if ($this->moduleHandler->moduleExists('token')) {
      $form['token_help'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => ['file'],
      ];
      $form['upload_location']['#description'] = $this->t('You can use tokens in the upload location.');
    }

    $media_type_options = $this->getMediaTypeOptions();

    $form['media_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Media types'),
      '#description' => $this->t('Select one or more media types. Only files with extensions configured in source fields of selected media types are allowed to upload.'),
      '#default_value' => !empty($this->configuration['media_types']) ? $this->configuration['media_types'] : array(),
      '#options' => $media_type_options,
      '#required' => TRUE,
    ];

    $form['first_step_button_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Upload step button text'),
      '#default_value' => $this->configuration['first_step_button_text'],
      '#required' => TRUE,
    ];

    $form += parent::buildConfigurationForm($form, $form_state);

    return $form;
  }

  /**
   * Get media type options to give for configuration.
   * @return array media_type_options
   *   Array of media types. Ids as keys and labels as values.
   */
  protected function getMediaTypeOptions() {
    $media_type_options = [];
    $media_types = $this
      ->entityTypeManager
      ->getStorage('media_type')
      ->loadMultiple();

    foreach ($media_types as $media_type) {
      $source_field_definition = $this->getSourceFieldDefinitionForMediaType($media_type);
      if ($source_field_definition) {
        $extensions = $source_field_definition->getSetting('file_extensions');
        if (!empty($extensions)) {
          $media_type_options[$media_type->id()] = $media_type->label();
        }
      }
    }
    return $media_type_options;
  }

  /**
   * Function helping to determine allowed file extension based on selected media types.
   * @return array $file_extensions
   *   Array of file extensions.
   */
  protected function getAllowedFileExtensions() {
    $file_extensions = [];
    $media_types = \Drupal::entityTypeManager()->getStorage('media_type')->loadMultiple(array_keys(array_filter($this->configuration['media_types'])));
    foreach ($media_types as $media_type) {
      $source_field_definition = $this->getSourceFieldDefinitionForMediaType($media_type);
      if ($source_field_definition) {
        $file_extensions = array_merge($file_extensions, explode(' ', $source_field_definition->getSetting('file_extensions')));
      }
    }
    return $file_extensions;
  }

  /**
   * Static helper function to load source field for give media type.
   * @param \Drupal\media\Entity\MediaType $media_type
   * @return \Drupal\field\Entity\FieldConfig
   */
  public static function getSourceFieldDefinitionForMediaType($media_type) {
    $source_field_name = $media_type->getSource()->getConfiguration()['source_field'];
    if ($source_field_name) {
      $bundle_fields = \Drupal::getContainer()->get('entity_field.manager')->getFieldDefinitions('media', $media_type->id());
      return $bundle_fields[$source_field_name];
    }
  }
}
