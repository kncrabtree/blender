<?php

namespace Drupal\blender\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\blender\JournalInterface;
use Drupal\blender\JournalArticleInterface;
use Drupal\user;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception;
use Drupal\Component\Datetime\Time;
use Drupal\Core\Datetime\DrupalDateTime;
use GuzzleHttp\Client;

class BlenderController extends ControllerBase {

  protected $query_service;

  protected $time_service;

  protected $http_client;

  protected $page_size = 20;

  protected $conditions;

  protected $page;


  public function __construct( QueryFactory $qf, Time $time, Client $client)
  {
    $this->query_service = $qf;
    $this->time_service = $time;
    $this->http_client = $client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.query'),
      $container->get('datetime.time'),
      $container->get('http_client')
    );
  }

  protected function lookup_more_available($type)
  {
    $numquery = $this->query_service->get($type);
    if(isset($this->conditions))
    {
      foreach($this->conditions as $key => $value)
      {
        if(isset($value[1]))
          $numquery->condition($key,$value[0],$value[1]);
        else
          $numquery->condition($key,$value[0]);
      }
    }

    //Debugging statements
//     $str = "Checking ".$type." for entries with conditions:";
//     if(isset($this->conditions))
//     {
//       foreach($this->conditions as $key => $value)
//       {
//         $str.=' '.$key;
//         if(isset($value[1]))
//           $str.=$value[1].$value[0];
//         else
//           $str.='=='.$value[0];
//       }
//     }
//     else
//       $str.= " none";
//     $str .= ".";
//     \Drupal::logger('blender')->notice($str);
//     $count = $numquery->count()->execute();
//     \Drupal::logger('blender')->notice("Found ".$count." articles.");


    return  ($numquery->count()->execute() > 0);
  }

  protected function article_lookup_list()
  {
    $articlequery = $this->query_service->get('blender_article');
    if(isset($this->conditions))
    {
      foreach($this->conditions as $key => $value)
      {
        if(isset($value[1]))
          $articlequery->condition($key,$value[0],$value[1]);
        else
          $articlequery->condition($key,$value[0]);
      }
    }

    $a_ids = $articlequery->sort('id','DESC')->pager($this->page_size)->execute();
    $articles = $this->entityTypeManager()->getStorage('blender_article')->loadMultiple($a_ids);

    $this->conditions['id'] = [
      end($a_ids),
      '<'
    ];

    return $articles;

  }

  protected function other_lookup_list($type,$sort='article_id',$order='DESC')
  {
    $done = false;
    //in some cases (e.g., votes or comments), the same article may appear multiple times. Remove any duplicates
    $articles = array();
    $article_ids = array();
    $duplicates = 0;

    while(!$done)
    {
      $query = $this->query_service->get($type);
      if(isset($this->conditions))
      {
        foreach($this->conditions as $key => $value)
        {
          if(isset($value[1]))
            $query->condition($key,$value[0],$value[1]);
          else
            $query->condition($key,$value[0]);
        }
      }

      $fetch = ($duplicates > 0 ? $duplicates : $this->page_size);

//       //Debugging statements
//       $str = "Fetching ".$fetch." entries from ".$type;
//       if(isset($this->conditions[$sort]))
//         $str.=" with condition ".$sort.$this->conditions[$sort][1].$this->conditions[$sort][0];
//       $str.=" sorted by ".$sort." ".$order;
//       \Drupal::logger('blender')->notice($str);

      $ids = $query->sort($sort,$order)->pager($fetch)->execute();

      $list = $this->entityTypeManager()->getStorage($type)->loadMultiple($ids);


      $duplicates = 0;

      foreach($list as $item)
      {
        $this_article = $item->get('article_id')->entity;
        $this_id = $this_article->get('id')->value;
        if(!in_array($this_id,$article_ids))
        {
          if(strpos($type,'recommendation') !== false)
            $this_article->set_recommended($item);

          $articles[] = $this_article;
          $article_ids[] = $this_id;
        }
        else
          $duplicates++;
      }

//       //Debugging statements
//       $str = "Found ".count($list)." entries. First article: ".current($article_ids).", Last article: ".end($article_ids).". Duplicates: ".$duplicates.".";
//       reset($article_ids);
//       \Drupal::logger('blender')->notice($str);


      //if several duplicates, get more articles
      if($duplicates < $this->page_size/10 || count($list)==0)
        $done = true;

      if(count($list)>0)
      {
        $this->conditions[$sort] = [
          isset(end($list)->get($sort)->target_id) ? end($list)->get($sort)->target_id : end($list)->get($sort)->value,
          $order === 'DESC' ? '<' : '>',
        ];
      }

    }

    return $articles;
  }

  protected function build_render_array($articles, $more, $jid = 0, $standalone = false) {

    $a_array = array();
    foreach($articles as $a)
    {
      $a_d = $a->article_details();
      $a_d['is_owner'] = ($a_d['user_id'] == $this->currentUser()->id());

      $bm = ($this->query_service->get('blender_bookmark')
        ->condition('user_id',$this->currentUser()->id())
        ->condition('article_id',$a->id->value)
        ->count()->execute() > 0);
      $a_d['bookmark'] = $bm;

      $voted = ($this->query_service->get('blender_vote')
        ->condition('user_id',$this->currentUser()->id())
        ->condition('article_id',$a->id->value)
        ->count()->execute() > 0);
      $a_d['voted'] = $voted;

      $a_d['num_comments'] = $this->get_comment_count($a_d['id']);
      $a_d['num_votes'] = $this->get_vote_count($a_d['id']);

      $a_array[] = $a_d;
    }

    $q = $this->query_service->get('blender_journal');
    $jids = $q->sort('abbr','ASC')->execute();

    $j_list = $this->entityTypeManager()->getStorage('blender_journal')->loadMultiple($jids);

    $journals = array();
    foreach($j_list as $j)
    {
      $journals[] = [
        'id' => $j->get('id')->value,
        'abbr' => $j->get('abbr')->value,
        'title' => $j->get('title')->value,
      ];
    }

    $render = array(
      '#articles' => $a_array,
      '#more' => $more,
      '#cache' => [
        'max-age' => 0,
      ],
    );

    if($standalone)
      $render['#theme'] = 'blender-article';
    else
    {
      $render['#theme'] = 'blender';
      $render['#journals'] = $journals;
      $render['#journalfilter'] = isset($jid) ? $jid : 0;
      $render['#page'] = $this->page;
      $render['#inbox_new'] = $this->get_new_inbox();
      $render['#recommend_new'] = $this->get_new_recommend();
      $render['#attached'] = array(
        'library' => array (
          'blender/blender',
          'blender/google.icons',
          'blender/ckeditor',
          'blender/mathjax',
        ),
      );
    }

    return $render;
  }

  public function home() {
    //if the user is active, send to inbox.
    //for a passive user, send to recommendations if they have any
    //new ones, and to all commented otherwise
    $user = $this->entityTypeManager()->getStorage('user')->load($this->currentUser()->id());

    if($user->hasRole('blender_active_user'))
      return new RedirectResponse(\Drupal\Core\Url::fromRoute('blender.inbox')->toString());

    $new_recs = $this->query_service->get('blender_recommendation')
      ->condition('user_id',$this->currentUser()->id())
      ->condition('new',true)
      ->count()->execute();

    if($new_recs > 0)
      return new RedirectResponse(\Drupal\Core\Url::fromRoute('blender.user-recommendations')->toString());

    return new RedirectResponse(\Drupal\Core\Url::fromRoute('blender.all-comments')->toString());

  }

  public function inbox(Request $request) {

    $jid = $request->get('journal_id');
    if($jid !== NULL)
    {
      if($jid == 0)
        return new RedirectResponse(\Drupal\Core\Url::fromRoute('blender.inbox')->toString());
      else
        $this->conditions['journal_id'] = [$jid];
    }

    $this->conditions['user_id'] = [$this->currentUser()->id()];
    $this->conditions['inbox'] = [true];
    $this->page = 'inbox';

    $articles = $this->article_lookup_list();
    $more = $this->lookup_more_available('blender_article');

    return $this->build_render_array($articles,$more,$jid);
  }

  public function all_user_articles(Request $request) {

    $jid = $request->get('journal_id');
    if($jid !== NULL)
    {
      if($jid == 0)
        return new RedirectResponse(\Drupal\Core\Url::fromRoute('blender.user-archive')->toString());
      else
        $this->conditions['journal_id'] = [$jid];
    }

    $this->conditions['user_id'] = [$this->currentUser()->id()];
    $this->page = 'user-archive';

    $articles = $this->article_lookup_list();
    $more = $this->lookup_more_available('blender_article');

    return $this->build_render_array($articles,$more,$jid);
  }

  public function article(JournalArticleInterface $blender_article)
  {
    return $this->build_render_array([$blender_article],false);
  }

  public function all_articles(Request $request) {

    $jid = $request->get('journal_id');
    if($jid !== NULL)
    {
      if($jid == 0)
        return new RedirectResponse(\Drupal\Core\Url::fromRoute('blender.all')->toString());
      else
        $this->conditions['journal_id'] = [$jid];
    }

    $articles = $this->article_lookup_list();
    $more = $this->lookup_more_available('blender_article');
    $this->page = 'archive';

    return $this->build_render_array($articles,$more,$jid);
  }

  public function user_bookmarks(Request $request) {

    $jid = $request->get('journal_id');
    if($jid !== NULL)
    {
      if($jid == 0)
        return new RedirectResponse(\Drupal\Core\Url::fromRoute('blender.user-bookmarks')->toString());
      else
        $this->conditions['article_id.entity:blender_article.journal_id'] = [$jid];
    }

    $this->conditions['user_id'] = [$this->currentUser()->id()];
    $this->page = 'bookmarks';

    $articles = $this->other_lookup_list('blender_bookmark');
    $more = $this->lookup_more_available('blender_bookmark');

    return $this->build_render_array($articles,$more,$jid);

  }

  public function user_comments(Request $request) {

    $jid = $request->get('journal_id');
    if($jid !== NULL)
    {
      if($jid == 0)
        return new RedirectResponse(\Drupal\Core\Url::fromRoute('blender.user-comments')->toString());
      else
        $this->conditions['article_id.entity:blender_article.journal_id'] = [$jid];
    }

    $this->conditions['user_id'] = [$this->currentUser()->id()];
    $this->page = 'user-comments';

    $articles = $this->other_lookup_list('blender_comment',
    'id');
    $more = $this->lookup_more_available('blender_comment',
    'id');

    return $this->build_render_array($articles,$more,$jid);

  }

  public function all_comments(Request $request) {

    $jid = $request->get('journal_id');
    if($jid !== NULL)
    {
      if($jid == 0)
        return new RedirectResponse(\Drupal\Core\Url::fromRoute('blender.all-comments')->toString());
      else
        $this->conditions['article_id.entity:blender_article.journal_id'] = [$jid];
    }

    $this->page = 'all-comments';

    $articles = $this->other_lookup_list('blender_comment',
    'id');
    $more = $this->lookup_more_available('blender_comment',
    'id');

    return $this->build_render_array($articles,$more,$jid);

  }

  public function user_votes(Request $request) {

    $jid = $request->get('journal_id');
    if($jid !== NULL)
    {
      if($jid == 0)
        return new RedirectResponse(\Drupal\Core\Url::fromRoute('blender.user-votes')->toString());
      else
        $this->conditions['article_id.entity:blender_article.journal_id'] = [$jid];
    }

    $this->conditions['user_id'] = [$this->currentUser()->id()];
    $this->page = 'user-votes';

    $articles = $this->other_lookup_list('blender_vote');
    $more = $this->lookup_more_available('blender_vote');

    return $this->build_render_array($articles,$more,$jid);

  }

  public function user_recommendations(Request $request) {

    $jid = $request->get('journal_id');
    if($jid !== NULL)
    {
      if($jid == 0)
        return new RedirectResponse(\Drupal\Core\Url::fromRoute('blender.user-recommendations')->toString());
      else
        $this->conditions['article_id.entity:blender_article.journal_id'] = [$jid];
    }

    $this->conditions['user_id'] = [$this->currentUser()->id()];
    $this->page = 'user-recommendations';

    $articles = $this->other_lookup_list('blender_recommendation','id','DESC');
    $more = $this->lookup_more_available('blender_recommendation');

    return $this->build_render_array($articles,$more,$jid);

  }

  public function all_votes(Request $request) {

    $jid = $request->get('journal_id');
    if($jid !== NULL)
    {
      if($jid == 0)
        return new RedirectResponse(\Drupal\Core\Url::fromRoute('blender.all-votes')->toString());
      else
        $this->conditions['article_id.entity:blender_article.journal_id'] = [$jid];
    }

    $articles = $this->other_lookup_list('blender_vote');
    $more = $this->lookup_more_available('blender_vote');
    $this->page = 'votes';

    return $this->build_render_array($articles,$more,$jid);

  }

  public function starred(Request $request) {

    $jid = $request->get('journal_id');
    if($jid !== NULL)
    {
      if($jid == 0)
        return new RedirectResponse(\Drupal\Core\Url::fromRoute('blender.starred')->toString());
      else
        $this->conditions['journal_id'] = [$jid];
    }

    $this->conditions['is_starred'] = [true];
    $articles = $this->article_lookup_list();
    $more = $this->lookup_more_available('blender_article');
    $this->page = 'starred';

    return $this->build_render_array($articles,$more,$jid);

  }

  public function search(Request $request) {

    $search_terms = $request->get('search-text');
    $this->page = "search";

    $type = $request->get('search-type') !== NULL ? $request->get('search-type') : 'or';
    $user = $request->get('user') === 'true' ? true : false;
    $star = $request->get('star') === 'true' ? true : false;

    if(!empty($request->get('min-date')))
    {
      try
      {
        $search['date_start'] = DrupalDateTime::createFromFormat('Y-m-d',$request->get('min-date'))->format('U');
      }
      catch(Exception $e) {}
    }

    if(!empty($request->get('max-date')))
    {
      try
      {
        $search['date_end'] = DrupalDateTime::createFromFormat('Y-m-d',$request->get('max-date'))->format('U');
      }
      catch(Exception $e) {}
    }

    $search['terms'] = $search_terms;
    $search['type'] = $type;
    $search['user'] = $user;
    $search['star'] = $star;
    if(!empty($request->get('journal')))
      $search['journal'] = $request->get('journal');


    return $this->process_search($search);
  }

  public function process_search($search,$standalone = false) {

    $articles = array();
    $more = false;

    $terms = preg_split("/[\s,]+/",$search['terms'],-1,PREG_SPLIT_NO_EMPTY);

    if(count($terms)>0)
    {
      $aq = $this->query_service->get('blender_article');
      $cq = $this->query_service->get('blender_comment');
      //here, check for 'or', 'and', or 'exact' matching
      if($search['type'] === 'or')
      {
        $group = $aq->orConditionGroup();
        $c_group = $cq->orConditionGroup();
        foreach($terms as $term)
        {
          $group->condition('title',$term,'CONTAINS');
          $group->condition('authors',$term,'CONTAINS');
          $group->condition('abstract',$term,'CONTAINS');
          $c_group->condition('text.value',$term,'CONTAINS');
        }
        $aq->condition($group);
        $cq->condition($c_group);
      }
      else if($search['type'] === 'and')
      {
        $group = $aq->andConditionGroup();
        $c_group = $cq->andConditionGroup();
        foreach($terms as $term)
        {
          $subgroup = $aq->orConditionGroup();
          $subgroup->condition('title',$term,'CONTAINS');
          $subgroup->condition('authors',$term,'CONTAINS');
          $subgroup->condition('abstract',$term,'CONTAINS');
          $group->condition($subgroup);
          $c_group->condition('text.value',$term,'CONTAINS');
        }
        $aq->condition($group);
        $cq->condition($c_group);
      }
      else
      {
        $group = $aq->orConditionGroup();
        $group->condition('title',$search['terms'],'CONTAINS');
        $group->condition('authors',$search['terms'],'CONTAINS');
        $group->condition('abstract',$search['terms'],'CONTAINS');
        $aq->condition($group);
        $cq->condition('text.value',$search['terms'],'CONTAINS');
      }

      if($search['user'])
      {
        $aq->condition('user_id',$this->currentUser()->id());
        $cq->condition('article_id.entity:blender_article.user_id',$this->currentUser()->id());
      }

      if(isset($search['journal']))
      {
        $aq->condition('journal_id',$search['journal']);
        $cq->condition('article_id.entity:blender_article.journal_id',$search['journal']);
      }

      if(isset($search['date_start']))
      {
        $aq->condition('date_added',$search['date_start'],'>');
        $cq->condition('article_id.entity:blender_article.date_added',$search['date_start'],'>');
      }

      if(isset($search['date_end']))
      {
        $aq->condition('date_added',$search['date_end'],'<');
        $cq->condition('article_id.entity:blender_article.date_added',$search['date_end'],'<');
      }

      if($search['star'])
      {
        $aq->condition('is_starred',true);
        $cq->condition('article_id.entity:blender_article.is_starred',true);
        if(isset($search['max_star_date']))
        {
          $aq->condition('star_date',$search['max_star_date'],'<');
          $cq->condition('article_id.entity:blender_article.star_date',$search['max_star_date'],'<');
        }
        $aq->sort('star_date','DESC');
        $cq->sort('article_id.entity:blender_article.star_date');
      }
      else
      {
        if(isset($search['min_article_id']))
        {
          $aq->condition('id',$search['min_article_id'],'<');
          $cq->condition('article_id',$search['min_article_id'],'<');
        }
        $aq->sort('id','DESC');
        $cq->sort('article_id','DESC');
      }



      $a_ids = $aq->execute();
      $article_matches = $this->entityTypeManager()->getStorage('blender_article')->loadMultiple($a_ids);

      $c_ids = $cq->execute();
      $comment_matches = $this->entityTypeManager()->getStorage('blender_comment')->loadMultiple($c_ids);
      $i = 0;
      $a_ids2 = array();
      foreach($comment_matches as $c)
      {
        if(!in_array($c->get('article_id')->target_id,$a_ids) && !in_array($c->get('article_id')->target_id,$a_ids2))
          $a_ids2[] = $c->get('article_id')->target_id;

        $i++;
        if($i > $this->page_size)
          break;
      }
      $comment_articles = $this->entityTypeManager()->getStorage('blender_article')->loadMultiple($a_ids2);


      //need to put all articles in order.
      //limit to page_size
      $articles = array();
      $sort_c = ($search['star']) ? 'star_date' : 'id';
      for($i=0; $i<$this->page_size; $i+=1)
      {
        //shift first elements off each array
        $aa = array_shift($article_matches);
        $ca = array_shift($comment_articles);

        if(!isset($aa) && !isset($ca))
          break;

        if(!isset($aa))
        {
          $articles[] = $ca;
          continue;
        }

        if(!isset($ca))
        {
          $articles[] = $aa;
          continue;
        }

        if($aa->get($sort_c)->value > $ca->get($sort_c)->value)
        {
          $articles[] = $aa;
          array_unshift($comment_articles,$ca);
        }
        else
        {
          $articles[] = $ca;
          array_unshift($article_matches,$aa);
        }
      }

      if(!empty($article_matches) || !empty($comment_articles))
        $more = true;

    }

    return $this->build_render_array($articles,$more,0,$standalone);

  }

  public function more_articles(Request $request) {

    $a_id = $request->request->get('last_id');

    if(!isset($a_id))
      throw new NotFoundHttpException();

    //use the page to determine what conditions to use
    $page = $request->request->get('origin');

    parse_str(substr($request->request->get('query'),1),$query);

    //searches use different logic than the rest
    if(strpos($page,'search') !== false)
    {
      $search['terms'] = $query['search-text'];
      $search['type'] = isset($query['search-type']) ? $query['search-type'] : 'or';
      $search['user'] = isset($query['user']) && $query['user'] === 'true' ? true : false;
      $search['star'] = isset($query['star']) && $query['star'] === 'true' ? true : false;

      if(!empty($query['journal']))
        $search['journal'] = $query['journal'];

      if(!empty($query['min-date']))
      {
        try
        {
          $search['date_start'] = DrupalDateTime::createFromFormat('Y-m-d',$query['min-date'])->format('U');
        }
        catch(Exception $e) {}
      }

      if(!empty($query['max-date']))
      {
        try
        {
          $search['date_end'] = DrupalDateTime::createFromFormat('Y-m-d',$query['max-date'])->format('U');
        }
        catch(Exception $e) {}
      }


      $search['min_article_id'] = $a_id;

      if($search['star'])
      {
        $article = $this->entityTypeManager()->getStorage('blender_article')->load($a_id);
        $search['max_star_date'] = $article->get('star_date')->value;
      }

      $render = $this->process_search($search,true);
      $return_data = array(
        'html' => render($render),
        'more' => $render['#more'],
      );

      return new JsonResponse($return_data);
    }

    if(strpos($page,'inbox') !== false)
      $this->conditions['inbox'] = [true];

    //should user be a filter?
    if(strpos($page,'user') !== false)
      $this->conditions['user_id'] = [$this->currentUser()->id()];

    $articles = array();
    $type = 'blender_article';
    $sort = 'article_id';
    $order = 'DESC';

    if(strpos($page,'bookmarks') !== false)
    {
      $this->conditions['article_id'] = [$a_id,'<'];
      $type = 'blender_bookmark';
      if(isset($query['journal_id']))
        $this->conditions['article_id.entity:blender_article.journal_id'] = $query['journal_id'];

      $articles = $this->other_lookup_list($type);
    }
    else if(strpos($page,'votes') !== false)
    {
      $this->conditions['article_id'] = [$a_id,'<'];
      $type = 'blender_vote';
      if(isset($query['journal_id']))
        $this->conditions['article_id.entity:blender_article.journal_id'] = $query['journal_id'];

      $articles = $this->other_lookup_list($type);
    }
    else if(strpos($page,'comments') !== false)
    {
      //lookup most recent comment of LAST article displayed
      $c_ids = $this->query_service->get('blender_comment')
        ->condition('article_id',$a_id)
        ->sort('id','DESC')
        ->execute();

      $val = isset($c_ids[0]) ? $c_ids[0] : $c_ids;
      $this->conditions['id'] = [$val,'<'];
      $type = 'blender_comment';
      $sort = 'id';
      if(isset($query['journal_id']))
        $this->conditions['article_id.entity:blender_article.journal_id'] = $query['journal_id'];

      $articles = $this->other_lookup_list($type,$sort);
    }
    else if(strpos($page,'recommendations') !== false)
    {
      //retrieve id of last rec
      $r_ids = $this->query_service->get('blender_recommendation')
        ->condition('article_id',$a_id)
        ->condition('user_id',$this->currentUser()->id())
        ->execute();

      $val = isset($r_ids[0]) ? $r_ids[0] : $r_ids;
      $this->conditions['id'] = [$r_id,'<'];
      $type = 'blender_recommendation';
      $sort = 'id';
      if(isset($query['journal_id']))
        $this->conditions['article_id.entity:blender_article.journal_id'] = $query['journal_id'];


      $articles = $this->other_lookup_list($type,$sort);
    }
    else if(strpos($page,'starred') !== false)
    {
      //get star timestamp of last article
      $a = $this->entityTypeManager()->getStorage('blender_article')->load($a_id);
      $this->conditions['is_starred'] = [true];
      $this->conditions['star_date'] = [$a->get('star_date')->value,'<'];
      $sort = 'star_date';
      if(isset($query['journal_id']))
        $this->conditions['journal_id'] = $query['journal_id'];

    }
    else
    {
      $this->conditions['id'] = [$a_id,'<'];
      if(isset($query['journal_id']))
        $this->conditions['journal_id'] = $query['journal_id'];


      $articles = $this->article_lookup_list();
    }

    $more = $this->lookup_more_available($type);

    $render = $this->build_render_array($articles,$more,0,true);

    $return_data = array(
      'html' => render($render),
      'more' => $render['#more'],
    );

    return new JsonResponse($return_data);

  }

  public function toggle_archive(Request $request) {
    $a_id = $request->request->get('article_id');

    if(!isset($a_id))
      throw new NotFoundHttpException();

    $user = $this->currentUser();

    $article = $this->entityTypeManager()->getStorage('blender_article')->load($a_id);

    if($article->get('user_id')->target_id != $user->id())
      throw new NotFoundHttpException();

    $inbox = !($article->get('inbox')->value);
    $article->set('inbox',$inbox);
    $article->set('new',false);
    $article->save();

    $remove = false;
    if(strpos($request->request->get('origin'),'inbox') !== false)
      $remove = true;

    $return_data = array(
      'article_id' => $a_id,
      'inbox' => $inbox,
      'remove' => $remove,
      'new_inbox' => $this->get_new_inbox(),
    );

    return new JsonResponse($return_data);

  }

  public function toggle_bookmark(Request $request) {
    $a_id = $request->request->get('article_id');

    if(!isset($a_id))
      throw new NotFoundHttpException();

    $user = $this->currentUser();

    //load this bookmark if it exists
    $bmlist = $this->entityTypeManager()->getStorage('blender_bookmark')->loadByProperties([
      'user_id' => $user->id(),
      'article_id' => $a_id,
    ]);

    $is_bookmark = true;

    if(count($bmlist) == 0)
    {
      //bookmark does not exist. Create it.
      $bm = $this->entityTypeManager()->getStorage('blender_bookmark')->create();
      $bm->set('user_id',$user->id());
      $bm->set('article_id',$a_id);
      $bm->save();
    }
    else
    {
      //bookmark exists. Delete it
      $bm = array_shift($bmlist);
      $bm->delete();
      $is_bookmark = false;
    }

    $remove = false;
    if(strpos($request->request->get('origin'),'bookmarks') !== false)
      $remove = true;

    $this->check_article_preserve($a_id);

    $return_data = $this->mark_read($a_id,$request->request->get('origin'));

    $return_data['article_id'] = $a_id;
    $return_data['bookmark'] = $is_bookmark;

    return new JsonResponse($return_data);

  }

  public function add_vote(Request $request) {
    $a_id = $request->request->get('article_id');

    if(!isset($a_id))
      throw new NotFoundHttpException();

    $user = $this->currentUser();

    $vs = $this->entityTypeManager()->getStorage('blender_vote');

    //see if the vote is already in the database
    $vl = $vs->loadByProperties([
      'user_id' => $user->id(),
      'article_id' => $a_id
    ]);

    $return_data = $this->mark_read($a_id,$request->request->get('origin'));

    $return_data['article_id'] = $a_id;
    $vc = $this->get_vote_count($a_id);

    if(count($vl) == 0)
    {
      //add vote to system
      $vc++;
      $vote = $vs->create();
      $vote->set('user_id',$user->id());
      $vote->set('article_id',$a_id);
      $article = $this->entityTypeManager()->getStorage('blender_article')->load($a_id);

      //post to Slack
      if($this->config('blender-slack.settings')->get('blender-slack.enabled') && !$this->config('blender-slack.settings')->get('blender-slack.aggregate'))
      {
        //\Drupal::logger('blender')->notice('Trying to post to Slack.');
        $slack['channel'] = $this->config('blender-slack.settings')->get('blender-slack.channel');
        $slack['text'] = $this->currentUser()->getDisplayName().' voted for an article in '.$article->get('journal_id')->entity->get('abbr')->value.'.';
        $slack['attachments'] = [
          [
            "title" => $article->get('title')->value,
            "text" => "Total votes: ".$vc,
            "color" => "#ce0610",
            "actions" => [
              [
                "type" => "button",
                "text" => "View Abstract",
                "url" => 'http://dx.doi.org/'.$article->get('doi')->value,
              ],
              [
                "type" => "button",
                "text" => "Open in Blender",
                "url" => \Drupal\Core\Url::fromRoute('blender.blender_article.canonical',[ 'blender_article' => $a_id, ],[ 'absolute' => true, ])->toString(),
              ],
            ],
          ],
        ];

        $reply = $this->post_to_slack('chat.postMessage',$slack);
        if(isset($reply['ok']) && $reply['ok'] == true)
          $vote->set('slack_ts',$reply['ts']);
        else
        {
          \Drupal::logger('blender')->warning('Could not post to Slack. Error: '.$reply['error']);
        }
      }
      $vote->save();
      $return_data['vote_added'] = true;
    }
    else
    {
      $vote = array_shift($vl);
      if($vote->get('article_id')->entity->get('is_starred')->value)
        $return_data['can_remove_vote'] = false;
      else
        $return_data['can_remove_vote'] = true;

      $return_data['vote_added'] = false;

    }

    $this->check_article_preserve($a_id);

    $return_data['count'] = $vc;

    return new JsonResponse($return_data);

  }

  public function remove_vote(Request $request) {

    $a_id = $request->request->get('article_id');

    if(!isset($a_id))
      throw new NotFoundHttpException();

    $user = $this->currentUser();

    $vs = $this->entityTypeManager()->getStorage('blender_vote');

    //see if the vote is already in the database
    $vl = $vs->loadByProperties([
      'user_id' => $user->id(),
      'article_id' => $a_id
    ]);

    $return_data = $this->mark_read($a_id,$request->request->get('origin'));
    $return_data['article_id'] = $a_id;

    if(count($vl) == 0)
    {
      //this should never happen, but if it does, just pretend like it was successful
      $return_data['vote_removed'] = true;
    }
    else
    {
      $vote = array_shift($vl);
      if($vote->get('article_id')->entity->get('is_starred')->value)
        $return_data['vote_removed'] = false;
      else
      {
        //remove from Slack if it's there
        //does not work with aggregation
        if($this->config('blender-slack.settings')->get('blender-slack.enabled'))
        {
          if(isset($vote->get('slack_ts')->value))
          {
            $slack['channel'] = $this->config('blender-slack')->get('blender-slack.channel');
            $slack['as_user'] = true;
            $slack['ts'] = $vote->get('slack_ts')->value;
            $this->post_to_slack('chat.delete',$slack);
          }
        }
        $vote->delete();
        $return_data['vote_removed'] = true;
      }
    }

    $this->check_article_preserve($a_id);

    $return_data['count'] = $this->get_vote_count($a_id);

    return new JsonResponse($return_data);

  }

  public function mark_unread(Request $request) {
    $a_id = $request->request->get('article_id');

    if(!isset($a_id))
      throw new NotFoundHttpException();

    $user = $this->currentUser();

    //handle case if recommendation here
    $list = array();
    if(strpos($request->request->get('origin'),'recommendation') !== false)
    {
      $list = $this->entityTypeManager()->getStorage('blender_recommendation')->loadByProperties([
        'user_id' =>$this->currentUser()->id(),
        'article_id' => $a_id,
      ]);
    }
    else
    {
      $list = $this->entityTypeManager()->getStorage('blender_article')->loadByProperties([
        'user_id' =>$this->currentUser()->id(),
        'id' => $a_id,
      ]);
    }

    if(count($list) > 0)
    {
      $item = array_shift($list);
      $item->set('new',true);
      $item->save();
    }

    $return_data['article_id'] = $a_id;
    $return_data['new_inbox'] = $this->get_new_inbox();
    $return_data['new_recommend'] = $this->get_new_recommend();

    return new JsonResponse($return_data);

  }

  public function get_eligible_recommend(Request $request) {

    $a_id = $request->request->get('article_id');

    if(!isset($a_id))
      throw new NotFoundHttpException();


    //get all active and passive users
    $query = $this->query_service->get('user');
    $group = $query->orConditionGroup()
      ->condition('roles','blender_active_user')
      ->condition('roles','blender_passive_user');
    $u_ids = $query->condition($group)->execute();
    $user_list = $this->entityTypeManager()->getStorage('user')->loadMultiple($u_ids);

    //lookup any existing recommendations for this article
    $rec_list = $this->entityTypeManager()->getStorage('blender_recommendation')->loadByProperties([
      'article_id' => $a_id,
    ]);

    //cannot share an article with its owner or with oneself.
    $article = $this->entityTypeManager()->getStorage('blender_article')->load($a_id);
    $owner_id = $article->get('user_id')->target_id;

    $ineligible = [$owner_id,$this->currentUser()->id()];

    if(count($rec_list) > 0)
    {
      foreach($rec_list as $rec)
        $ineligible[] = $rec->get('user_id')->target_id;
    }

    $return_data = $this->mark_read($a_id,$request->request->get('origin'));
    $return_data['query'] = 'Unit';

    foreach($user_list as $u)
    {
      if(!in_array($u->id(),$ineligible))
      {
        $return_data['suggestions'][] = [
          'value' => $u->getDisplayName(),
          'data' => $u->id(),
        ];
      }
    }

    return new JsonResponse($return_data);

  }

  public function recommend(Request $request) {

    $a_id = $request->request->get('article_id');
    $target_user = $request->get('user_id');

    if(!isset($a_id) || !isset($target_user))
      throw new NotFoundHttpException();

    $this_user = $this->currentUser()->id();

    //make sure that this article hasn't already been sent to this user.
    $rec_list = $this->entityTypeManager()->getStorage('blender_recommendation')->loadByProperties([
      'article_id' => $a_id,
      'user_id' => $target_user,
    ]);

    $return_data['article_id'] = $a_id;

    if(count($rec_list) > 0)
    {
      $return_data['success'] = false;
      $return_data['msg'] = 'This article has already been recommended to that user.';
    }
    else
    {
      $rec = $this->entityTypeManager()->getStorage('blender_recommendation')->create();
      $rec->set('article_id',$a_id);
      $rec->set('user_id',$target_user);
      $rec->set('sender_id',$this_user);
      $rec->set('new',true);
      $rec->save();

      //get im channel and notify user on Slack
      $receiver = $this->entityTypeManager()->getStorage('user')->load($target_user)->get('slack_id')->value;
      $article = $this->entityTypeManager()->getStorage('blender_article')->load($a_id);

      if(isset($receiver) && $this->config('blender-slack.settings')->get('blender-slack.enabled'))
      {
        $slack['user'] = $receiver;

        $reply = $this->post_to_slack('im.open',$slack,true);
        if(isset($reply['ok']) && $reply['ok'] == true)
        {
          $channel = $reply['channel']['id'];
          $slack2['channel'] = $channel;
          $slack2['text'] = $this->currentUser()->getDisplayName().' shared an article from '.$article->get('journal_id')->entity->get('abbr')->value.' with you.';
          $slack2['attachments'] = [
            [
              "title" => $article->get('title')->value,
              "color" => "good",
              "actions" => [
                [
       	          "type" => "button",
                  "text" => "View Abstract",
                  "url" => 'http://dx.doi.org/'.$article->get('doi')->value,
                ],
                [
       	          "type" => "button",
                  "text" => "Open in Blender",
                  "url" => \Drupal\Core\Url::fromRoute('blender.blender_article.canonical',[ 'blender_article' => $a_id, ],[ 'absolute' => true, ])->toString(),
                ],
              ],
            ],
          ];
          $response2 = $this->post_to_slack('chat.postMessage',$slack2,true);
          if(isset($response2['ok']) && $response2['ok'] == false)
          {
            \Drupal::logger('blender')->warning('Could not post message to user '.$receiver.'. Error: '.$response2['error']);
          }
        }
        else
        {
          if(isset($reply['error']))
            \Drupal::logger('blender')->warning('Could not open IM with user '.$receiver.'. Error: '.$reply['error']);
          else
          {
            \Drupal::logger('blender')->warning('Could not open IM with user '.$receiver.'. No error message received. WTF?');
          }
        }
      }

      $return_data['success'] = true;
    }

    $this->check_article_preserve($a_id);

    return new JsonResponse($return_data);

  }

  public function mark_read_if_owner(Request $request) {

    $a_id = $request->request->get('article_id');

    if(!isset($a_id))
      throw new NotFoundHttpException();

    $return_data = $this->mark_read($a_id,$request->request->get('origin'));

    return new JsonResponse($return_data);

  }

  protected function mark_read($a_id,$page) {
    $return_data['remove_new'] = false;

    if(strpos($page,'recommendation') !== false)
    {
      $rec_list = $this->entityTypeManager()->getStorage('blender_recommendation')->loadByProperties([
      'article_id' => $a_id,
      'user_id' => $this->currentUser()->id(),
      ]);

      if(count($rec_list) > 0)
      {
        $rec = array_shift($rec_list);
        $rec->set('new',false);
        $rec->save();
        $return_data['remove_new'] = true;
      }
    }
    else
    {
      $art = $this->entityTypeManager()->getStorage('blender_article')->load($a_id);

      if($this->currentUser()->id() == $art->get('user_id')->target_id)
      {
        $return_data['remove_new'] = true;
        $art->set('new',false);
        $art->save();
      }
    }

    $return_data['new_inbox'] = $this->get_new_inbox();
    $return_data['new_recommend'] = $this->get_new_recommend();

    return $return_data;
  }

  public function fetch_article_comments(Request $request) {

    $a_id = $request->request->get('article_id');

    if(!isset($a_id))
      throw new NotFoundHttpException();

    $c_ids = $this->query_service->get('blender_comment')
      ->condition('article_id',$a_id)
      ->sort('timestamp','ASC')->execute();

    $comments = $this->entityTypeManager()->getStorage('blender_comment')->loadMultiple($c_ids);

    $render = array(
      '#cache' => [
        'max-age' => 0,
      ],
    );

    $c_array = [];
    foreach($comments as $c)
    {
      $c_data = $c->get_comment_details();
      $c_data['is_author'] = $c_data['user_id'] == $this->currentUser()->id();
      $c_array[] = $c_data;
    }

    $render['#comments'] = $c_array;
    $render['#theme'] = 'blender-comment';

    $return_data['html'] = render($render);
    $return_data['count'] = $this->get_comment_count($a_id);

    return new JsonResponse($return_data);

  }

  public function fetch_comment(Request $request) {

    $c_id = $request->request->get('comment_id');

    if(!isset($c_id))
      throw new NotFoundHttpException();

    $c = $this->entityTypeManager()->getStorage('blender_comment')->load($c_id);

    $c_data = $c->get_comment_details();
    $c_data['is_author'] = $c_data['user_id'] == $this->currentUser()->id();

    return new JsonResponse($c_data);

  }


  public function edit_comment(Request $request) {

    $c_id = $request->request->get('comment_id');

    if(!isset($c_id))
      throw new NotFoundHttpException();

    $comment = $request->request->get('comment');

    if(!isset($comment))
      throw new NotFoundHttpException();

    $c = $this->entityTypeManager()->getStorage('blender_comment')->load($c_id);

    if($c->get('user_id')->target_id != $this->currentUser()->id())
      throw new NotFoundHttpException();

    $return_data['success'] = true;

    $article = $this->entityTypeManager()->getStorage('blender_article')->load($c->get('article_id')->target_id);

    if(!isset($c))
      $return_data['success'] = false;
    else
    {
      $c->set('text',[
        'value' => $comment,
        'format' => 'Basic HTML',
      ]);

      //edit comment on slack. Does not work with aggregation
      if($this->config('blender-slack.settings')->get('blender-slack.enabled'))
      {
        if($c->get('slack_ts')->value !== NULL)
        {
          $slack['ts'] = $c->get('slack_ts')->value;
          $slack['channel'] = $this->config('blender-slack.settings')->get('blender-slack.channel');
          $slack['text'] = $this->currentUser()->getDisplayName().' commented on an article in '.$article->get('journal_id')->entity->get('abbr')->value.'. (Edited: '.DrupalDateTime::createFromTimestamp($this->time_service->getRequestTime())->format('Y-m-d g:i:s A').')';
          $slack['as_user'] = true;
          $slack['attachments'] = [
            [
              "title" => $article->get('title')->value,
              "text" => html_entity_decode(strip_tags($comment), ENT_QUOTES|ENT_HTML5),
              "color" => "#a000c4",
              "actions" => [
                [
                  "type" => "button",
                  "text" => "View Abstract",
                  "url" => 'http://dx.doi.org/'.$article->get('doi')->value,
                ],
                [
                  "type" => "button",
                  "text" => "Open in Blender",
                  "url" => \Drupal\Core\Url::fromRoute('blender.blender_article.canonical',[ 'blender_article' => $article->get('id')->value, ],[ 'absolute' => true, ])->toString(),
                ],
              ],
            ],
          ];


          $reply = $this->post_to_slack('chat.update',$slack);
          if(isset($reply['ok']) && $reply['ok'] == true)
            $c->set('slack_ts',$reply['ts']);

        }
      }

      $c->set('edited_time',$this->time_service->getRequestTime());
      $c->save();
    }

    return new JsonResponse($return_data);


  }

  public function delete_comment(Request $request) {

    $c_id = $request->request->get('comment_id');

    if(!isset($c_id))
      throw new NotFoundHttpException();

    $c = $this->entityTypeManager()->getStorage('blender_comment')->load($c_id);

    if($c->get('user_id')->target_id != $this->currentUser()->id())
      throw new NotFoundHttpException();

    $this->check_article_preserve($c->get('article_id')->target_id);

    //delete comment on slack. Does not work with aggregation
    if(isset($c->get('slack_ts')->value) && $this->config('blender-slack.settings')->get('blender-slack.enabled'))
    {
      $slack['channel'] = $this->config('blender-slack.settings')->get('blender-slack.channel');
      $slack['as_user'] = true;
      $slack['ts'] = $c->get('slack_ts')->value;
      $this->post_to_slack('chat.delete',$slack);
    }

    $c->delete();
    $return_data['success'] = true;

    return new JsonResponse($return_data);


  }


  public function add_comment(Request $request) {

    $a_id = $request->request->get('article_id');

    if(!isset($a_id))
      throw new NotFoundHttpException();

    $comment = $request->request->get('comment');

    if(!isset($comment))
      throw new NotFoundHttpException();

    $c = $this->entityTypeManager()->getStorage('blender_comment')->create();
    $c->set('user_id',$this->currentUser()->id());
    $c->set('article_id',$a_id);
    $c->set('text',[
      'value' => $comment,
      'format' => 'Basic HTML',
    ]);

    $article = $this->entityTypeManager()->getStorage('blender_article')->load($a_id);

    //send comment to slack; get TS ID for use with editing
    if($this->config('blender-slack.settings')->get('blender-slack.enabled') && !$this->config('blender-slack.settings')->get('blender-slack.aggregate'))
    {
      $slack['channel'] = $this->config('blender-slack.settings')->get('blender-slack.channel');
      $slack['text'] = $this->currentUser()->getDisplayName().' commented on an article in '.$article->get('journal_id')->entity->get('abbr')->value.'.';
      $slack['attachments'] = [
        [
          "title" => $article->get('title')->value,
          "text" => html_entity_decode(strip_tags($comment), ENT_QUOTES|ENT_HTML5),
          "color" => "good",
          "actions" => [
            [
              "type" => "button",
              "text" => "View Abstract",
              "url" => 'http://dx.doi.org/'.$article->get('doi')->value,
            ],
            [
              "type" => "button",
              "text" => "Open in Blender",
              "url" => \Drupal\Core\Url::fromRoute('blender.blender_article.canonical',[ 'blender_article' => $a_id, ],[ 'absolute' => true, ])->toString(),
            ],
          ],
        ],
      ];

      $reply = $this->post_to_slack('chat.postMessage',$slack);
      if(isset($reply['ok']) && $reply['ok'] == true)
        $c->set('slack_ts',$reply['ts']);
    }

    $c->save();

    $article->set('preserve',true);
    $article->save();

    $return_data['success'] = true;

    return new JsonResponse($return_data);

  }

  public function get_journal_list() {
    $q = $this->query_service->get('blender_journal');
    $ids = $q->sort('abbr','ASC')->execute();

    $j_list = $this->entityTypeManager()->getStorage('blender_journal')->loadMultiple($ids);
    $return_data['query'] = 'Unit';

    foreach($j_list as $j)
    {
      $str = $j->get('abbr')->value.': '.$j->get('title')->value;
      $return_data['suggestions'][] = [
        'value' => $str,
        'data' => $j->get('id')->value,
      ];
    }

    return new JsonResponse($return_data);
  }

  public function get_new_inbox() {
    return $this->query_service->get('blender_article')
      ->condition('user_id',$this->currentUser()->id())
      ->condition('inbox',true)
      ->condition('new',true)
      ->count()->execute();
  }

  public function get_new_recommend() {
    return $this->query_service->get('blender_recommendation')
      ->condition('user_id',$this->currentUser()->id())
      ->condition('new',true)
      ->count()->execute();
  }

  public function get_comment_count($a_id) {
    return $this->query_service->get('blender_comment')
      ->condition('article_id',$a_id)
      ->count()->execute();
  }

  public function get_vote_count($a_id) {
    return $this->query_service->get('blender_vote')
      ->condition('article_id',$a_id)
      ->count()->execute();
  }

  public function post_to_slack($method, $array, $bot = false) {

    if(!$this->config('blender-slack.settings')->get('blender-slack.enabled'))
      return;

    $url = 'https://slack.com/api/'.$method;


    $headers['Content-type'] = 'application/json';
    if($bot)
      $headers['Authorization'] = 'Bearer '.$this->config('blender-slack.settings')->get('blender-slack.bot-token');
    else
      $headers['Authorization'] = 'Bearer '.$this->config('blender-slack.settings')->get('blender-slack.workspace-token');

    $response = $this->http_client->request('POST',$url, [
      'headers' => $headers,
      'body' => json_encode($array)
    ]);

    if($response->getStatusCode() != 200)
    {
      \Drupal::logger('blender')->warning("Received error code ".$response->getStatusCode()," when posting to Slack (url = ".$url.").");
      return array();
    }

    return json_decode($response->getBody(),true);

  }

  public function check_article_preserve($a_id) {

    $article = $this->entityTypeManager()->getStorage('blender_article')->load($a_id);

    if(!isset($article) || $article->get('is_starred')->value)
      return;

    //step 2: check to see if article has votes, comments, bookmarks, or recommendations
    $tables = ['blender_bookmark', 'blender_vote', 'blender_comment', 'blender_recommendation'];

    foreach( $tables as $t )
    {
      if($this->query_service->get($t)->condition('article_id',$a_id)->count()->execute() > 0)
      {
        $article->set('preserve',true);
        $article->save();
        return;
      }
    }

    $article->set('preserve',false);
    $article->save();

  }


}

