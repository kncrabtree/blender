<?php

namespace Drupal\blender\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\Entity;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\blender\JournalArticleInterface;
use Drupal\Blender\Entity\BlenderRecommendation;

/**
 * Defines the journal article comment entity.
 *
 * @ingroup blender
 *
 * @ContentEntityType(
 *   id = "blender_comment",
 *   label = @Translation("Blender Comment"),
 *   base_table = "blender_comments",
 *   admin_permission = "administer journals",
 *   fieldable = false,
 *   entity_keys = {
 *     "id" = "id",
 *     "user_id" = "user_id",
 *     "article_id" = "article_id",
 *     "text" = "text",
 *     "timestamp" = "timestamp",
 *   },
 * )
 */

class BlenderComment extends ContentEntityBase implements JournalArticleInterface {


  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    // Standard field, used as unique if primary index.
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the comment.'))
      ->setReadOnly(true);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User ID'))
      ->setDescription(t('The user who created the comment.'))
      ->setSetting('target_type', 'user')
      ->setReadOnly(true);

    $fields['article_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Article ID'))
      ->setDescription(t('The article associated with comment.'))
      ->setSetting('target_type', 'blender_article')
      ->setReadOnly(true);

    $fields['text'] = BaseFieldDefinition::create('text')
      ->setLabel(t('Comment text'))
      ->setDescription(t("Text of comment"))
      ->setRequired(true);

    $fields['timestamp'] = BaseFieldDefinition::create('created')
      ->setLabel(t("Timestamp"))
      ->setDescription(t("When the comment was created."))
      ->setRequired(true);

    $fields['edited_time'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Last edited'))
      ->setDescription(t('Time when comment was edited.'))
      ->setRequired(false);

    return $fields;
  }

  public function get_comment_details() {
    $out['id'] = $this->get('id')->value;
    $out['user_id'] = $this->get('user_id')->target_id;
    $out['author'] = $this->get('user_id')->entity->getDisplayName();
    $out['timestamp'] = DrupalDateTime::createFromTimestamp($this->get('timestamp')->value)->format('Y-m-d g:i:s A');
    $out['edited'] = isset($this->get('edited_time')->value) ? DrupalDateTime::createFromTimestamp($this->get('edited_time')->value)->format('Y-m-d g:i:s A') : NULL;
    $out['text'] = $this->get('text')->value;
  }

}
?>
