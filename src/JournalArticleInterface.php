<?php

namespace Drupal\blender;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface defining a JournalArticle entity.
 * @ingroup blender
 */
interface JournalArticleInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}

?>
