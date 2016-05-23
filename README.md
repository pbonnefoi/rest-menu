# Drupal 8 - REST Webservice

## Prérequis :
  - rest => core
  - hal => core
  - serialization => core
  - restui => https://www.drupal.org/project/restui
  - DHC Rest Client Chrome Extension => https://chrome.google.com/webstore/detail/dhc-rest-client/aejoelaoggembcahagimdiliamlcdmfm

## Ressources :
  - http://enzolutions.com/articles/2014/12/16/how-to-create-a-rest-resource-in-drupal-8/
  - https://drupalize.me/blog/201401/introduction-restful-web-services-drupal-8

### Etape 1 :
Créer un module avec la structure suivante :
  - mymodule.info.yml
  - src\Plugin\rest\resources\MyServiceResource.php

### Etape 2 :
Dans la class MyServiceResource :

#### Namespace :
```php
namespace Drupal\rest_menu_tree\Plugin\rest\resource;
```

#### Libraries :
```php
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Psr\Log\LoggerInterface;
```

#### Annotations :

>!IMPORTANT! : Cette notation est obligatoire pour déclarer la route du service REST.

```php
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
```
#### Class :
```php
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

     foreach ($menu_tree as $element) {
       $link = $element->link;
       $urlObject = $link->getUrlObject();
       // Check if given URL is external or internal.
       if ($urlObject->isExternal() || !$urlObject->isRouted()) {
         $url = $urlObject->getUri();
       }
       else {
         $url = $urlObject->getInternalPath();
       }
       $result[] = [
         'title' => $link->getTitle(),
         'url' => $url,
         'weight' => $link->getWeight()
       ];
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
```

