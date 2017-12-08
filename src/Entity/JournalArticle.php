<?php

namespace Drupal\blender\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\Entity;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Defines the journal article entity.
 *
 * @ingroup blender
 *
 * @ContentEntityType(
 *   id = "blender_article",
 *   label = @Translation("Journal Article"),
 *   base_table = "journal_articles",
 *   admin_permission = "administer journals",
 *   fieldable = FALSE,
 *   handlers = {
 *     "view_builder" = "Drupal\blender\Entity\Controller\JournalArticleViewBuilder",
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "user_id" = "user_id",
 *     "inbox" = "inbox",
 *     "new" = "new",
 *     "journal_id" = "journal_id",
 *     "authors" = "authors",
 *     "title" = "title",
 *     "abstract" = "abstract",
 *     "volume" = "volume",
 *     "pages" = "pages",
 *     "doi" = "doi",
 *     "url" = "url",
 *     "date_added" = "date_added"
 *   },
 *   links = {
 *     "canonical" = "/journals/article/{blender_article}",
 *   },
 * )
 */

class JournalArticle extends ContentEntityBase implements ContentEntityInterface {

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    // Standard field, used as unique if primary index.
    $fields['id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('ID'))
      ->setDescription(t('The ID of the article.'))
      ->setReadOnly(TRUE);

    // Standard field, unique outside of the scope of the current project.
    $fields['uuid'] = BaseFieldDefinition::create('uuid')
      ->setLabel(t('UUID'))
      ->setDescription(t('The UUID of the article.'))
      ->setReadOnly(TRUE);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User ID'))
      ->setDescription(t('The ID of the user assigned to this article.'))
      ->setSetting('target_type', 'user')
      ->setReadOnly(TRUE);

    $fields['inbox'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('In Inbox?'))
      ->setDescription(t("Is the article in the user's inbox?"))
      ->setRequired(TRUE)
      ->setSetting('default_value', TRUE);

    $fields['new'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('New?'))
      ->setDescription(t("Has the user seen the article?"))
      ->setRequired(TRUE)
      ->setSetting('default_value', TRUE);

    $fields['journal_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Journal ID'))
      ->setDescription(t('The ID of the journal for this article.'))
      ->setSetting('target_type', 'blender_journal')
      ->setReadOnly(TRUE);

    $fields['authors'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Authors'))
      ->setDescription(t('The authors of the article.'))
      ->setRequired(TRUE);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('The title of the article.'))
      ->setRequired(TRUE);

    $fields['abstract'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Abstract'))
      ->setDescription(t('The abstract of the article.'))
      ->setRequired(FALSE);

    $fields['volume'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Volume'))
      ->setDescription(t('The volume of the article\'s journal.'))
      ->setRequired(FALSE);

    $fields['pages'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Pages'))
      ->setDescription(t('The pages or number of the article in its journal.'))
      ->setRequired(FALSE);

    $fields['doi'] = BaseFieldDefinition::create('string')
      ->setLabel(t('DOI'))
      ->setDescription(t('The digital object identifier of the article.'))
      ->setRequired(FALSE);

    $fields['url'] = BaseFieldDefinition::create('uri')
      ->setLabel(t('URL'))
      ->setDescription(t("The URL for the article."))
      ->setRequired(TRUE)
      ->addConstraint('UniqueField');

    $fields['date_added'] = BaseFieldDefinition::create('created')
      ->setLabel("Date added")
      ->setDescription("When the article was added to the system.")
      ->setRequired(TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function article_details() {

    $owner = $this->get('user_id')->entity;
    $name = $owner->getDisplayName();

    $out = array (
        'user_id' => $this->get('user_id')->target_id,
        'inbox' => $this->get('inbox')->value,
        'new' => $this->get('new')->value,
        'authors' => $this->get('authors')->value,
        'title' => $this->get('title')->value,
        'owner' => $name,
        'journal' => $this->get('journal_id')->entity->getAbbreviation(),
        'abstract' => $this->get('abstract')->value,
        'volume' => $this->get('volume')->value,
        'pages' => $this->get('pages')->value,
        'doi' => $this->get('doi')->value,
        'url' => $this->get('url')->value,
//         'date_added' => DrupalDateTime::createFromTimestamp($this->get('date_added')),
    );

    return $out;
  }
}
?>
