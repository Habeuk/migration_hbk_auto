<?php

namespace Drupal\migration_hbk_auto\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Listens to the dynamic route events.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   *
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Change the route associated with the 'system.site_information_settings'
    // form.
    if ($route = $collection->get('apivuejs.save.entity')) {
      $route->setDefault('_controller', 'Drupal\migration_hbk_auto\Controller\MigrationHbkAutoApivuejsController::saveEntity');
    }
  }
}
