<?php

namespace Drupal\blender\Controller;

use Drupal\Core\Controller\ControllerBase;

class BlenderController extends ControllerBase {

  /**
   * Display the markup.
   *
   * @return array
   */
  public function content() {



    $element['#articles'] = array(
      array(
        'authors' => 'Authors 1',
        'title' => 'Title 1',
        'journal' => 'Journal 1',
        'volume' => 'Vol 1',
        'pages' => 'Pages 1',
        'url' => 'URL 1'
      ),
      array(
        'authors' => 'Authors 2',
        'title' => 'Title 2',
        'journal' => 'Journal 2',
        'volume' => 'Vol 2',
        'pages' => 'Pages 2',
        'url' => 'URL 2'
      ),
      array(
        'authors' => 'Authors 3',
        'title' => 'Title 3',
        'journal' => 'Journal 3',
        'volume' => 'Vol 3',
        'pages' => 'Pages 3',
        'url' => 'URL 3'
      ),
    );
    $element['#theme'] = 'blender';
    $element['#attached'] = array(
      'library' => array (
        'blender/blender'
      )
    );

    return $element;
  }

}
