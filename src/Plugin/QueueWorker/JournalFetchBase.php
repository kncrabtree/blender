<?php

namespace Drupal\blender\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\blender\JournalInterface;
use Drupal\blender\JournalArticleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Datetime\DrupalDateTime;


abstract class JournalFetchBase extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
  * The journal storage
  *
  * @var \Drupal\Core\Entity\EntityStorageInterface
  */
  protected $journal_storage;
  protected $article_storage;


  /**
   * Creates a new JournalFetchBase object.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $j_storage
   *   The node storage.
   */
  public function __construct(EntityStorageInterface $j_storage, EntityStorageInterface $a_storage) {
    $this->journal_storage = $j_storage;
    $this->article_storage = $a_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('entity.manager')->getStorage('blender_journal'),
      $container->get('entity.manager')->getStorage('blender_article')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    /** @var JournalInterface $journal */
    $journal = $this->journal_storage->load($data->id->value);

    //1.) Generate date range to fetch (last update to yesterday; never today))
    $now = DrupalDateTime::createFromTimestamp(REQUEST_TIME);
    $yesterday = $now->modify("-1 day");
    $last_update = DrupalDateTime::createFromTimestamp($journal->last_update->value);

    $start = $last_update->format('Y-m-d');
    $end = $yesterday->format('Y-m-d');


    //2.) Get ISSN
    $issn = $journal->issn->value;

    $url = "https://api.crossref.org/journals/".$issn."/works?filter=from-created-date:".$start.",until-created-date:".$end.",type:journal-article";

    //3.) Send request to CrossRef

    $client = \Drupal::httpClient();

    try {
      $response = $client->get($url, [
        'headers' => [
          "User-Agent" => "Crabtree Lab (http://crabtreelab.ucdavis.edu; mailto:kncrabtree@ucdavis.edu)"
        ],
        'http_errors' => false,
      ]);
      if($response->getStatusCode() != 200)
      {
        \Drupal::logger('blender')->warning("HTTP status ".$response->getStatusCode()." when requesting ".$journal->abbr->value.". URL: ".$url);
      }
      else
      {
        $data = json_decode($response->getBody(),true);

        $article_count = $data['message']['total-results'];

        \Drupal::logger('blender')->notice("Found ".$article_count." articles in ".$journal->abbr->value);

        $journal->set('queued_time',0);
        $journal->set('last_num_articles',$article_count);
         $journal->set('last_update',REQUEST_TIME);
        $journal->save();


        $articles = $data['message']['items'];
        foreach($articles as $a)
        {
          $article = $this->article_storage->create();

          $article->set('user_id',4);
          $article->set('inbox',true);
          $article->set('new',true);
          $article->set('journal_id',$journal->id->value);

          $author_string = "";
          $author_count = 0;
          foreach($a['author'] as $author)
          {
            $author_count++;
            $author_name = $author['given'].' '.$author['family'];
            if($author_string === "")
              $author_string .= $author_name;
            else
              $author_string .= ', '.$author_name;

            if($author_count >= 10)
            {
              $author_string .= ', et al.';
              break;
            }
          }
          $article->set('authors',$author_string);
          $article->set('title',$a['title']);
          if(isset($a['abstract']))
            $article->set('abstract',$a['abstract']);
          if(isset($a['volume']))
            $article->set('volume',$a['volume']);
          if(isset($a['page']))
            $article->set('pages',$a['page']);
          $article->set('year',$a['issued']['date-parts'][0][0]);
          $article->set('doi',$a['DOI']);
          $article->save();
        }
      }

    }
    catch (RequestException $e) {
      \Drupal::logger('blender')->warning("Exception occurred when requesting ".$journal->abbr->value.". URL: ".$url);
    }

    //5.) If successful, update journal's last_articles field
    //6.) Create new article entities for each returned URL


  }
}


?>
