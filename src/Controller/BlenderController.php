<?php

namespace Drupal\blender\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\blender\JournalInterface;
use Drupal\blender\JournalArticleInterface;
use Drupal\user;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception;
use Drupal\Component\Datetime\Time;

class BlenderController extends ControllerBase {

  protected $query_service;

  protected $time_service;

  protected $page_size = 20;

  protected $conditions;

  protected $page;


  public function __construct( QueryFactory $qf, Time $time)
  {
    $this->query_service = $qf;
    $this->time_service = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.query'),
      $container->get('datetime.time')
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
    $str = "Checking ".$type." for entries with conditions:";
    if(isset($this->conditions))
    {
      foreach($this->conditions as $key => $value)
      {
        $str.=' '.$key;
        if(isset($value[1]))
          $str.=$value[1].$value[0];
        else
          $str.='=='.$value[0];
      }
    }
    else
      $str.= " none";
    $str .= ".";
    \Drupal::logger('blender')->notice($str);
    $count = $numquery->count()->execute();
    \Drupal::logger('blender')->notice("Found ".$count." articles.");


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

      //Debugging statements
      $str = "Fetching ".$fetch." entries from ".$type;
      if(isset($this->conditions[$sort]))
        $str.=" with condition ".$sort.$this->conditions[$sort][1].$this->conditions[$sort][0];
      $str.=" sorted by ".$sort." ".$order;
      \Drupal::logger('blender')->notice($str);

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

      //Debugging statements
      $str = "Found ".count($list)." entries. First article: ".current($article_ids).", Last article: ".end($article_ids).". Duplicates: ".$duplicates.".";
      reset($article_ids);

      \Drupal::logger('blender')->notice($str);

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

  protected function build_render_array($articles, $more, $standalone = false) {

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
      $render['#page'] = $this->page;
      $render['#inbox_new'] = $this->get_new_inbox();
      $render['#recommend_new'] = $this->get_new_recommend();
      $render['#attached'] = array(
        'library' => array (
          'blender/blender',
          'blender/google.icons',
          'blender/ckeditor',
        ),
      );
    }

    return $render;
  }

  public function inbox() {

    $this->conditions['user_id'] = [$this->currentUser()->id()];
    $this->conditions['inbox'] = [true];
    $this->page = 'inbox';

    $articles = $this->article_lookup_list();
    $more = $this->lookup_more_available('blender_article');

    return $this->build_render_array($articles,$more);
  }

  public function all_user_articles() {

    $this->conditions['user_id'] = [$this->currentUser()->id()];
    $this->page = 'user-archive';

    $articles = $this->article_lookup_list();
    $more = $this->lookup_more_available('blender_article');

    return $this->build_render_array($articles,$more);
  }

  public function article(JournalArticleInterface $blender_article)
  {
    return $this->build_render_array([$blender_article],false);
  }

  public function all_articles() {

    $articles = $this->article_lookup_list();
    $more = $this->lookup_more_available('blender_article');
    $this->page = 'archive';

    return $this->build_render_array($articles,$more);
  }

  public function user_bookmarks() {

    $this->conditions['user_id'] = [$this->currentUser()->id()];
    $this->page = 'bookmarks';

    $articles = $this->other_lookup_list('blender_bookmark');
    $more = $this->lookup_more_available('blender_bookmark');

    return $this->build_render_array($articles,$more);

  }

  public function user_comments() {

    $this->conditions['user_id'] = [$this->currentUser()->id()];
    $this->page = 'user-comments';

    $articles = $this->other_lookup_list('blender_comment',
    'id');
    $more = $this->lookup_more_available('blender_comment',
    'id');

    return $this->build_render_array($articles,$more);

  }

  public function all_comments() {

    $this->page = 'all-comments';

    $articles = $this->other_lookup_list('blender_comment',
    'id');
    $more = $this->lookup_more_available('blender_comment',
    'id');

    return $this->build_render_array($articles,$more);

  }

  public function user_votes() {

    $this->conditions['user_id'] = [$this->currentUser()->id()];
    $this->page = 'user-votes';

    $articles = $this->other_lookup_list('blender_vote');
    $more = $this->lookup_more_available('blender_vote');

    return $this->build_render_array($articles,$more);

  }

  public function user_recommendations() {

    $this->conditions['user_id'] = [$this->currentUser()->id()];
    $this->page = 'user-recommendations';

    $articles = $this->other_lookup_list('blender_recommendation','id','DESC');
    $more = $this->lookup_more_available('blender_recommendation');

    return $this->build_render_array($articles,$more);

  }

  public function all_votes() {

    $articles = $this->other_lookup_list('blender_vote');
    $more = $this->lookup_more_available('blender_vote');
    $this->page = 'votes';

    return $this->build_render_array($articles,$more);

  }

  public function starred() {

    $this->conditions['is_starred'] = [true];
    $articles = $this->article_lookup_list();
    $more = $this->lookup_more_available('blender_article');
    $this->page = 'starred';

    return $this->build_render_array($articles,$more);

  }

  public function more_articles(Request $request) {

    $a_id = $request->request->get('last_id');

    if(!isset($a_id))
      throw new NotFoundHttpException();

    //use the page to determine what conditions to use
    $page = $request->request->get('origin');

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
      $articles = $this->other_lookup_list($type);
    }
    else if(strpos($page,'votes') !== false)
    {
      $this->conditions['article_id'] = [$a_id,'<'];
      $type = 'blender_vote';
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

      $articles = $this->other_lookup_list($type,$sort);
    }
    else if(strpos($page,'starred') !== false)
    {
      //get star timestamp of last article
      $a = $this->entityTypeManager()->getStorage('blender_article')->load($a_id);
      $this->conditions['is_starred'] = [true];
      $this->conditions['star_date'] = [$a->get('star_date')->value,'<'];
      $sort = 'star_date';
    }
    else
    {
      $this->conditions['id'] = [$a_id,'<'];

      $articles = $this->article_lookup_list();
    }

    $more = $this->lookup_more_available($type);

    $render = $this->build_render_array($articles,$more,true);

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

    $return_data = $this->mark_read($a_id,$request->request->get('origin'));

    $return_data['article_id'] = $a_id;
    $return_data['bookmark'] = $is_bookmark;
    $return_data['remove'] = $remove;

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

    if(count($vl) == 0)
    {
      //add vote to system
      $vote = $vs->create();
      $vote->set('user_id',$user->id());
      $vote->set('article_id',$a_id);
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

    $return_data['count'] = $this->get_vote_count($a_id);

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
    $return_data['remove'] = false;

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
        $vote->delete();
        $return_data['vote_removed'] = true;
        if(strpos($request->request->get('origin'),'user') !== false && strpos($request->request->get('origin'),'votes') !== false)
          $return_data['remove'] = true;
      }
    }


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
          'value' => ucwords($u->getDisplayName()),
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
      $return_data['success'] = true;
    }

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

    if(!isset($c))
      $return_data['success'] = false;
    else
    {
      $c->set('text',[
        'value' => $comment,
        'format' => 'Basic HTML',
      ]);
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
    $c->save();

    $return_data['success'] = true;

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


}

