<?php

namespace Drupal\blender\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\blender\JournalInterface;
use Drupal\blender\JournalArticleInterface;
use Drupal\user;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception;

class BlenderController extends ControllerBase {

  /**
  * Entity access service
  *
  * @var \Drupal\Core\Entity\EntityStorageInterface
  */
  protected $journal_storage;

  protected $user_storage;

  protected $article_storage;

  protected $query_service;

  protected $page_size = 20;

  protected $conditions;


  public function __construct(EntityStorageInterface $j_storage, EntityStorageInterface $u_storage, EntityStorageInterface $a_storage,
  QueryFactory $qf)
  {
    $this->journal_storage = $j_storage;
    $this->article_storage = $a_storage;
    $this->user_storage = $u_storage;
    $this->query_service = $qf;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')->getStorage('blender_journal'),
      $container->get('entity.manager')->getStorage('user'),
      $container->get('entity.manager')->getStorage('blender_article'),
      $container->get('entity.query')
    );
  }

  protected function build_render_array($theme = 'blender') {

    $numquery = $this->query_service->get('blender_article');
    $articlequery = $this->query_service->get('blender_article');
    foreach($this->conditions as $key => $value)
    {
      if(isset($value[1]))
      {
        $numquery->condition($key,$value[0],$value[1]);
        $articlequery->condition($key,$value[0],$value[1]);
      }
      else
      {
        $numquery->condition($key,$value[0]);
        $articlequery->condition($key,$value[0]);
      }
    }

    $num = $numquery->count()->execute();
    $a_ids = $articlequery->sort('id','DESC')->pager($this->page_size)->execute();


    $articles = $this->article_storage->loadMultiple($a_ids);

    $more = false;
    if($num > $this->page_size)
      $more = true;

    $a_array = array();
    foreach($articles as $a)
    {
      $a_d = $a->article_details();
      $a_d['is_owner'] = ($a_d['user_id'] == $this->currentUser()->id());
      $a_array[] = $a_d;
    }

    $render = array(
      '#theme' => $theme,
      '#attached' => array(
        'library' => array (
          'blender/blender',
          'blender/google.icons'
        )
      ),
      '#articles' => $a_array,
      '#more' => $more,
      '#cache' => [
        'max-age' => 0,
      ],
    );

    return $render;
  }

  public function inbox() {

    $this->conditions['user_id'] = [$this->currentUser()->id()];
    $this->conditions['inbox'] = [true];

    return $this->build_render_array();
  }

  public function all_user_articles() {

    $this->conditions['user_id'] = [$this->currentUser()->id()];

    return $this->build_render_array();
  }

  public function all_articles() {
    return $this->build_render_array();
  }

  public function toggle_archive(Request $request) {
    $a_id = $request->request->get('article_id');

    if(!isset($a_id))
      throw new NotFoundHttpException();

    $user = $this->currentUser();

    $article = $this->article_storage->load($a_id);
    if($article->get('user_id')->target_id != $user->id())
      throw new NotFoundHttpException();

    $inbox = !($article->get('inbox')->value);
    $article->set('inbox',$inbox);
    $article->save();

    $remove = false;
    if(strpos($request->request->get('origin'),'inbox') !== false)
      $remove = true;

    $return_data = array(
      'article_id' => $a_id,
      'inbox' => $inbox,
      'remove' => $remove
    );

    return new JsonResponse($return_data);

  }

  public function more_articles(Request $request) {

    $a_id = $request->request->get('last_article_id');

    if(!isset($a_id))
      throw new NotFoundHttpException();

    $this->conditions['id'] = [$a_id,'<'];

    //use the page to determine what conditions to use
    $page = $request->request->get('origin');

    if(strpos($request->request->get('origin'),'inbox') !== false)
      $this->conditions['inbox'] = [true];

    //should user be a filter?
    if(strpos($request->request->get('origin'),'user') !== false)
      $this->conditions['user_id'] = [$this->currentUser()->id()];

    $render = $this->build_render_array('blender-article');

    $return_data = array(
      'html' => render($render),
      'more' => $render['#more'],
    );

    return new JsonResponse($return_data);

  }

}
