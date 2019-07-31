<?php

namespace Drupal\media_entity_embed;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining an MediaMimeMapping entity.
 */
interface MediaMimeMappingInterface extends ConfigEntityInterface {
  /**
   * Returns the mime types.
   *
   * Mime types can be configured to associate with media type.
   *
   * @return array
   *   Mime type strings
   */
  public function getMimeTypes();

  /**
   * Sets the mime types.
   *
   * @param array $mime_types
   *   Mime type strings
   *
   * @return $this
   */
  public function setMimeTypes(array $mime_types);
}

