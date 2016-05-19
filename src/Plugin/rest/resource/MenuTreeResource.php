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
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
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
     * @param $menu
     * @return ResourceResponse
     */
    public function get($menu) {
        // $menu is given argument in url.
        if ($menu) {
            // We load the menu as needed.
            $menu_parameters = $this->menuLinkTree->getCurrentRouteMenuTreeParameters($menu);
            $menu_tree = $this->menuLinkTree->load($menu, $menu_parameters);
            $manipulators = array(
                // Only show links that are accessible for the current user.
                array('callable' => 'menu.default_tree_manipulators:checkAccess'),
                // Use the default sorting of menu links.
                array('callable' => 'menu.default_tree_manipulators:generateIndexAndSort'),
            );
            $menu_tree = $this->menuLinkTree->transform($menu_tree, $manipulators);
            $result = [];

            if ($menu_tree) {
                // We recursively call a function to build menu with all the sub items.
                $result = $this->recursively_build_menu($menu_tree);
            }

            if ($result) {
                return new ResourceResponse(json_encode($result));
            }
            // Throw exception if menu doesn't exits.
            throw new NotFoundHttpException(t('Links for menu @menu were not found', array('@menu' => $menu)));
        }
        // Throw exception if $menu is not provided.
        throw new HttpException(t('Menu name wasn\'t provided'));
    }

    /**
     * @param $menu_tree
     * @return mixed
     */
    protected function recursively_build_menu($menu_tree) {
        $i = 0;
        foreach ($menu_tree as $key => $menu_item) {
            $link = $menu_item->link;
            $urlObject = $link->getUrlObject();
            // Check if url is external or internal
            if ($urlObject->isExternal() || !$urlObject->isRouted()) {
                $url = $urlObject->getUri();
            }
            else {
                $url = $urlObject->getInternalPath();
            }
            $subtree = $menu_item->subtree;
            $sub = NULL;
            $result[$i]['name'] = $link->getTitle();
            $result[$i]['url'] = $url;
            $result[$i]['weight'] = $link->getWeight();
            if ($subtree) {
                // Call the function again to get the sub items.
                $sub = $this->recursively_build_menu($subtree);
            }
            $result[$i]['sub'] = $sub;
            $i++;
        }

        return $result;
    }
}
