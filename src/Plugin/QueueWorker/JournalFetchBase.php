<?php

namespace Drupal\blender\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\blender\JournalInterface;
use Drupal\blender\JournalArticleInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\user;


abstract class JournalFetchBase extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
  * The journal storage
  *
  * @var \Drupal\Core\Entity\EntityStorageInterface
  */
  protected $journal_storage;
  protected $article_storage;
  protected $user_storage;
  protected $query_service;


  /**
   * Creates a new JournalFetchBase object.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $j_storage
   *   The node storage.
   */
  public function __construct(EntityStorageInterface $j_storage, EntityStorageInterface $a_storage, EntityStorageInterface $u_storage, QueryFactory $qf) {
    $this->journal_storage = $j_storage;
    $this->article_storage = $a_storage;
    $this->user_storage = $u_storage;
    $this->query_service = $qf;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('entity.manager')->getStorage('blender_journal'),
      $container->get('entity.manager')->getStorage('blender_article'),
      $container->get('entity.manager')->getStorage('user'),
      $container->get('entity.query')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    /** @var JournalInterface $journal */
    $journal = $this->journal_storage->load($data->id->value);
    $user_list = $this->query_service->get('user')
      ->condition('roles','blender_active_user')
      ->execute();

    if(empty($user_list))
    {
      \Drupal::logger('blender')->warning('No active users; skipping '.$journal->abbr->value);
      $journal->set('queued_time',0);
      $journal->save();
      return;
    }

    shuffle($user_list);

    //1.) Generate date range to fetch (last update to yesterday; never today))
    $start = DrupalDateTime::createFromTimestamp($journal->last_update->value)->format('Y-m-d');
    if((REQUEST_TIME - $journal->last_update->value) > 24*60*60*7)
      $start = DrupalDateTime::createFromTimestamp(REQUEST_TIME-24*60*60*7)->format('Y-m-d');

    $end = DrupalDateTime::createFromTimestamp(REQUEST_TIME-24*60*60)->format('Y-m-d');


    //2.) Get ISSN
    $issn = $journal->issn->value;

    $url = "https://api.crossref.org/journals/".$issn."/works?filter=from-created-date:".$start.",until-created-date:".$end.",type:journal-article";

    \Drupal::logger('blender')->notice('Querying '.$journal->abbr->value.'. URL: '.$url);

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

        $journal->set('queued_time',0);
        $journal->set('last_num_articles',$article_count);
        $journal->set('last_update',REQUEST_TIME);
        $journal->save();


        $articles = $data['message']['items'];
        $i = 0;
        $required_tags = [
          'author',
          'title',
          'issued',
          'DOI'
        ];

        foreach($articles as $a)
        {
          //ensure that all required fields are set
          $success = true;
          foreach($required_tags as $tag)
          {
            if(!isset($a[$tag]))
            {
              $success = false;
              break;
            }
          }

          if(!$success)
          {
            $article_count--;
            continue;
          }

          //select a user from the user_list
          $this_user = $user_list[$i];
          $i++;
          if($i >= sizeof($user_list))
            $i = 0;

          //create article; set all possible metadata
          $article = $this->article_storage->create();

          $article->set('user_id',$this_user);
          $article->set('inbox',true);
          $article->set('new',true);
          $article->set('journal_id',$journal->id->value);

          //build up sensible author string from list
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

          //abstract, volume, and page are optional
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

        \Drupal::logger('blender')->notice("Found ".$article_count." articles in ".$journal->abbr->value);

      }

    }
    catch (RequestException $e) {
      \Drupal::logger('blender')->warning("Exception occurred when requesting ".$journal->abbr->value.". URL: ".$url);
    }


  }
}


?>
