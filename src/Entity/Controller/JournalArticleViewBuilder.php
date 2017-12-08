<?php

namespace Drupal\blender\Entity\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Url;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Provides a view builder for journal articles.
 *
 * @ingroup blender
 */
class JournalArticleViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   *
   * Override ::view() to return a render array for the articles
   * This information will be used by twig to make the actual HTML
   */
  public function view(EntityInterface $entity, $view_mode = 'full', $langcode = NULL) {

    $build = array( '#articles' => array( $entity->article_details() ) );
    $build['#theme'] = 'blender';
    $build['#attached'] = array(
      'library' => array (
        'blender/blender'
      )
    );

    return $build;
  }

}
?>
