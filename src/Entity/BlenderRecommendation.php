<?php

namespace Drupal\blender\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines the recommendation entity.
 *
 * @ingroup blender
 *
 * @ContentEntityType(
 *   id = "blender_recommendation",
 *   label = @Translation("Recommendation"),
 *   base_table = "blender_recommendations",
 *   admin_permission = "administer journals",
 *   fieldable = FALSE,
 *   entity_keys = {
 *     "id" = "id",
 *     "article_id" = "article_id",
 *     "sender_id" = "sender_id",
 *     "user_id" = "user_id",
 *     "timestamp" = "timestamp",
 *     "new" = "new",
 *   },
 * )
 */

class BlenderRecommendation extends ContentEntityBase implements ContentEntityInterface {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    // Standard field, used as unique if primary index.
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the recommendation.'))
      ->setReadOnly(TRUE);


    $fields['article_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Article ID'))
      ->setDescription(t('Article recommended.'))
      ->setSetting('target_type','blender_article');


    $fields['sender_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Sender ID'))
      ->setDescription(t('User who recommended the article.'))
      ->setSetting('target_type','user');


    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Receiver ID'))
      ->setDescription(t('User receiving the recommendation.'))
      ->setSetting('target_type','user');

    $fields['timestamp'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Timestamp'))
      ->setDescription(t('Time the recommendation was made'));

    $fields['new'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('New'))
      ->setDescription(t('Whether receiver has seen the recommendation.'));

    return $fields;

  }

}
?>
