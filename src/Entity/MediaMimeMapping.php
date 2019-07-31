<?php

namespace Drupal\media_entity_embed\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\media_entity_embed\MediaMimeMappingInterface;

/**
 * Defines the MediaMimeMapping entity.
 *
 * @ConfigEntityType(
 *   id = "media_mime_mapping",
 *   label = @Translation("Media Mime Mapping"),
 *   handlers = {
 *     "list_builder" = "Drupal\media_entity_embed\Controller\MediaMimeMappingListBuilder",
 *     "form" = {
 *       "add" = "Drupal\media_entity_embed\Form\MediaMimeMappingForm",
 *       "edit" = "Drupal\media_entity_embed\Form\MediaMimeMappingForm",
 *       "delete" = "Drupal\media_entity_embed\Form\MediaMimeMappingDeleteForm",
 *     }
 *   },
 *   config_prefix = "media_entity_embed",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *   },
 *   config_export = {
 *     "id",
 *     "mime_types"
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/media/mime-mapping/{media_mime_mapping}",
 *     "delete-form" = "/admin/config/media/mime-mapping/{media_mime_mapping}/delete",
 *   }
 * )
 */
class MediaMimeMapping extends ConfigEntityBase implements MediaMimeMappingInterface {

  /**
   * The Example ID.
   *
   * @var string
   */
  public $id;

  /**
   * Mime types.
   *
   * @var array
   */
  public $mime_types = [];

  public function getMimeTypes() {
    return $this->mime_types;
  }

  public function setMimeTypes(array $mime_types) {
    $this->mime_types = $mime_types;
    return $this;
  }
}

