<?php

namespace Drupal\blender\Entity\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Url;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Provides a list controller for journals.
 *
 * @ingroup blender
 */
class JournalListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   *
   * We override ::render() so that we can add our own content above the table.
   * parent::render() is where EntityListBuilder creates the table using our
   * buildHeader() and buildRow() implementations.
   */
  public function render() {
    $build['description'] = [
      '#markup' => $this->t('These journals are known to the system.')//, array(
//         '@addlink' => \Drupal::urlGenerator()
//           ->generateFromRoute('entity.blender_journal.journal_add'),
//        ),
    ];

    $build += parent::render();
    return $build;
  }

  /**
   * {@inheritdoc}
   *
   * Building the header and content lines for the journal list.
   *
   * Calling the parent::buildHeader() adds a column for the possible actions
   * and inserts the 'edit' and 'delete' links as defined for the entity type.
   */
  public function buildHeader() {
    $header['id'] = $this->t('Journal ID');
    $header['uuid'] = $this->t('Journal UUID');
    $header['title'] = $this->t('Title');
    $header['abbr'] = $this->t('Abbreviation');
    $header['issn'] = $this->t('ISSN');
    $header['active'] = $this->t('Active?');
    $header['last_update'] = $this->t('Last Update');
    $header['last_num_articles'] = $this->t('Recent Articles');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var $entity \Drupal\blender\Entity\Journal */
    $row['id'] = $entity->id();
    $row['uuid'] = $entity->uuid();
    $row['title'] = $entity->title->value;
    $row['abbr'] = $entity->abbr->value;
    $row['issn'] = $entity->issn->value;
    $row['active'] = $entity->active->value;
    $row['last_update'] = DrupalDateTime::createFromTimestamp($entity->last_update->value)->format('m/d/Y g:ia');
    $row['last_num_articles'] = $entity->last_num_articles->value;

    return $row + parent::buildRow($entity);
  }

}
?>
