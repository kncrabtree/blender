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

class BlenderController extends ControllerBase {

  protected $query_service;

  protected $page_size = 20;

  protected $conditions;

  protected $page;


  public function __construct( QueryFactory $qf)
  {
    $this->query_service = $qf;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.query')
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

    return  ($numquery->count()->execute() > $this->page_size);
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

      //if several duplicates, get more articles
      if($duplicates > $this->page_size/10)
      {
        $this->conditions[$sort] = [
          $list[count($list)-1]->get($sort)->value,
          $order === 'DESC' ? '<' : '>',
        ];
      }
      else
        $done = true;
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
        ->condition('status',true)
        ->count()->execute() > 0);
      $a_d['bookmark'] = $bm;

      $voted = ($this->query_service->get('blender_vote')
        ->condition('user_id',$this->currentUser()->id())
        ->condition('article_id',$a->id->value)
        ->count()->execute() > 0);
      $a_d['voted'] = $voted;

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
          'blender/google.icons'
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
    $this->conditions['status'] = [true];
    $this->page = 'bookmarks';

    $articles = $this->other_lookup_list('blender_bookmark');
    $more = $this->lookup_more_available('blender_bookmark');

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

    $articles = $this->other_lookup_list('blender_recommendation','timestamp','DESC');
    $more = $this->lookup_more_available('blender_recommendation');

    return $this->build_render_array($articles,$more);

  }

  public function all_votes() {

    $articles = $this->other_lookup_list('blender_vote');
    $more = $this->lookup_more_available('blender_vote');
    $this->page = 'votes';

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
    else if(strpos($page,'recommendations') !== false)
    {
      //retrieve article; get its timestamp
      $a = $this->entityTypeManager()->getStorage('blender_article')->load($a_id);
      $this->conditions['timestamp'] = [$a->get('timestamp')->value,'<'];
      $type = 'blender_recommendation';

      $articles = $this->other_lookup_list($type);
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
      $bm->set('status',$is_bookmark);
      $bm->save();
    }
    else
    {
      $bm = array_shift($bmlist);
      $is_bookmark = !($bm->get('status')->value);
      $bm->set('status',$is_bookmark);
      $bm->save();
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


    //get all active users
    $user_list = $this->entityTypeManager()->getStorage('user')->loadByProperties([
      'roles' => 'blender_active_user',
    ]);

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


}

