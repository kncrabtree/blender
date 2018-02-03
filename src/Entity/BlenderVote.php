<?php

namespace Drupal\blender\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines the vote entity.
 *
 * @ingroup blender
 *
 * @ContentEntityType(
 *   id = "blender_vote",
 *   label = @Translation("Vote"),
 *   base_table = "blender_votes",
 *   admin_permission = "administer journals",
 *   fieldable = FALSE,
 *   entity_keys = {
 *     "id" = "id",
 *     "user_id" = "user_id",
 *     "article_id" = "article_id",
 *   },
 * )
 */

class BlenderVote extends ContentEntityBase implements ContentEntityInterface {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    // Standard field, used as unique if primary index.
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the vote.'))
      ->setReadOnly(TRUE);


    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User ID'))
      ->setDescription(t('User who voted.'))
      ->setSetting('target_type','user');

    $fields['article_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Article ID'))
      ->setDescription(t('Article voted on.'))
      ->setSetting('target_type','blender_article');

    $fields['slack_ts'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Slack TS'))
      ->setDescription(t('Slack post timestamp ID for this vote.'))
      ->setRequired(false);

    return $fields;
  }

}
?>
