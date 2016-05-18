<?php

/**
 * @file
 * Contains \Drupal\rest_menu_tree\Plugin\rest\resource\MenuTreeResource.
 */

namespace Drupal\rest_menu_tree\Plugin\rest\resource;

use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Psr\Log\LoggerInterface;

/**
 * Provides a resource to get view modes by entity and bundle.
 *
 * @RestResource(
 *   id = "menu_tree_resource",
 *   label = @Translation("Menu Tree Resource"),
 *   uri_paths = {
 *     "canonical" = "/entity/menu_tree/{menu}"
 *   }
 * )
 */
class MenuTreeResource extends ResourceBase {


  /**
   * @param ContainerInterface $container
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @return static
     */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('menu.link_tree'),
      $container->get('logger.factory')->get('rest')
    );
  }

  /**
   * MenuTreeResource constructor.
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param array $serializer_formats
   * @param MenuLinkTreeInterface $menu_tree
   * @param LoggerInterface $logger
     */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    MenuLinkTreeInterface $menu_tree,
    LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->menuLinkTree = $menu_tree;
  }

  /**
   * Responds to GET requests.
   *
   * Returns a list of bundles for specified entity.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function get($menu) {
    $menu_parameters = $this->menuLinkTree->getCurrentRouteMenuTreeParameters($menu);
    $tree = $this->menuLinkTree->load($menu, $menu_parameters);
    $result = array();

    foreach ($tree as $element) {
      $link = $element->link;
      array_push($result, array(
          'title' => $link->getTitle(),
          'url' => $link->getUrlObject(),
          'weight' => $link->getWeight()
        )
      );
    }
    return new ResourceResponse(json_encode($result));
  }
}
