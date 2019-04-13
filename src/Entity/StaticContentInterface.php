<?php

namespace Drupal\sci\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface for defining Static content entities.
 *
 * @ingroup sci
 */
interface StaticContentInterface extends ContentEntityInterface, EntityChangedInterface {

  /**
   * Gets the Static content name.
   *
   * @return string
   *   Name of the Static content.
   */
  public function getName();

  /**
   * Sets the Static content name.
   *
   * @param string $name
   *   The Static content name.
   *
   * @return \Drupal\sci\Entity\StaticContentInterface
   *   The called Static content entity.
   */
  public function setName($name);

  /**
   * Gets the Static content creation timestamp.
   *
   * @return int
   *   Creation timestamp of the Static content.
   */
  public function getCreatedTime();

  /**
   * Sets the Static content creation timestamp.
   *
   * @param int $timestamp
   *   The Static content creation timestamp.
   *
   * @return \Drupal\sci\Entity\StaticContentInterface
   *   The called Static content entity.
   */
  public function setCreatedTime($timestamp);

}
