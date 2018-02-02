<?php

namespace Drupal\blender\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines the journal entity.
 *
 * @ingroup blender
 *
 * @ContentEntityType(
 *   id = "blender_journal",
 *   label = @Translation("Journal"),
 *   base_table = "blender_journals",
 *   admin_permission = "administer journals",
 *   fieldable = FALSE,
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\blender\Entity\Controller\JournalListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\blender\Form\JournalForm",
 *       "edit" = "Drupal\blender\Form\JournalForm",
 *     },
 *     "access" = "Drupal\blender\JournalAccessControlHandler",
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "title" = "title",
 *     "abbr" = "abbr",
 *     "issn" = "issn",
 *     "active" = "active",
 *     "last_update" = "last_update",
 *     "queued_time" = "queued_time",
 *     "last_num_articles" = "last_num_articles",
 *   },
 *   links = {
 *     "canonical" = "/journals/journal/{blender_journal}",
 *     "edit-form" = "/journals/edit_journal/{blender_journal}",
 *     "collection" = "/journals/list"
 *   },
 * )
 */

class Journal extends ContentEntityBase implements ContentEntityInterface {

  public function getName() {
    return $this->get('title')->value;
  }

  public function getAbbreviation() {
    return $this->get('abbr')->value;
  }

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    // Standard field, used as unique if primary index.
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the journal.'))
      ->setReadOnly(TRUE);

    // Standard field, unique outside of the scope of the current project.
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the journal.'))
      ->setReadOnly(TRUE);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('The full title of the journal.'))
      ->setRequired(TRUE)
      ->addConstraint('UniqueField')
      ->setSettings(array(
        'default_value' => '',
        'max_length' => 255,
        'text_processing' => 0,
      ))
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'string',
        'weight' => -6,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'string_textfield',
        'weight' => -6,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['abbr'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Abbreviation'))
      ->setDescription(t('The journal abbreviation.'))
      ->setRequired(TRUE)
      ->addConstraint('UniqueField')
      ->setSettings(array(
        'default_value' => '',
        'max_length' => 50,
        'text_processing' => 0,
      ))
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'string',
        'weight' => -6,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'string_textfield',
        'weight' => -6,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['issn'] = BaseFieldDefinition::create('string')
      ->setLabel(t('ISSN'))
      ->setDescription(t("The journal's ISSN for CrossRef lookup."))
      ->setRequired(TRUE)
      ->addConstraint('UniqueField')
      ->addConstraint('active_issn')
      ->setSettings(array(
        'default_value' => '',
        'max_length' => 50,
        'text_processing' => 0,
      ))
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'type' => 'string',
        'weight' => -6,
      ))
      ->setDisplayOptions('form', array(
        'type' => 'string_textfield',
        'weight' => -6,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);


    $fields['active'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Active?'))
      ->setDescription(t('If active, the feed will be checked daily.'))
      ->setSettings(array(
        'default_value' => TRUE,
      ))
      ->setDisplayOptions('view', array(
        'label' => 'above',
        'weight' => -6,
      ))
      ->setDisplayOptions('form', array(
        'weight' => -6,
      ))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['last_update'] = BaseFieldDefinition::create('timestamp')
      ->setLabel("Last Update")
      ->setDescription("Last time this journal was checked for new articles.")
      ->setRequired(FALSE)
      ->setSettings(array('default_value' => 0));

    $fields['queued_time'] = BaseFieldDefinition::create('timestamp')
      ->setLabel("Queued Time")
      ->setDescription("Time this journal was queue for fetching (or 0 if not queued)")
      ->setRequired(TRUE)
      ->setSetting('default_value',0);

    $fields['last_num_articles'] = BaseFieldDefinition::create('integer')
      ->setLabel("Articles Retrieved")
      ->setDescription("Number of articles retrieved at last check.")
      ->setRequired(FALSE);

    return $fields;
  }

}
?>
