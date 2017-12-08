<?php

namespace Drupal\blender;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a Journal entity.
 * @ingroup blender
 */
interface JournalInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}

?>
