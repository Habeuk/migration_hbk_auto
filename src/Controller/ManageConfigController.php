<?php
declare(strict_types = 1);

namespace Drupal\migration_hbk_auto\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Stephane888\DrupalUtility\HttpResponse;
use Drupal\Component\Serialization\Json;
use Stephane888\Debug\ExceptionExtractMessage;
use Drupal\migration_hbk_auto\Services\ManageNodesConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Migration Hbk Auto routes.
 */
final class ManageConfigController extends ControllerBase {
  /**
   *
   * @var ManageNodesConfig
   */
  protected $ManageNodesConfig;
  
  function __construct(ManageNodesConfig $ManageNodesConfig) {
    $this->ManageNodesConfig = $ManageNodesConfig;
  }
  
  static function create(ContainerInterface $container) {
    return new static($container->get('migration_hbk_auto.manage_nodes_config'));
  }
  
  public function loadConfig(Request $Request) {
    $results = [];
    $payload = Json::decode($Request->getContent());
    try {
      // verifie la presence de l'entite.
      if (!empty($payload['config_id'])) {
        $results = $this->ManageNodesConfig->CheckConfiguration($payload['config_id'], $payload['datas']);
      }
      return HttpResponse::response($results);
    }
    catch (\Exception $e) {
      $results['errors'] = ExceptionExtractMessage::errorAll($e);
      return HttpResponse::response($results, 425, $e->getMessage());
    }
  }
  
  /**
   * Permet de construire la configuration à partir des données de V7.
   *
   * @param Request $Request
   */
  public function generateFields(Request $Request) {
    $results = [];
    $payload = Json::decode($Request->getContent());
    try {
      // verifie la presence de l'entite.
      if (!empty($payload['fields']) && !empty($payload['entity_type']) && !empty($payload['bundle'])) {
        $results = $this->ManageNodesConfig->generateConfigFields($payload['entity_type'], $payload['bundle'], $payload['fields']);
      }
      return HttpResponse::response($results);
    }
    catch (\Exception $e) {
      $results['errors'] = ExceptionExtractMessage::errorAll($e);
      return HttpResponse::response($results, 425, $e->getMessage());
    }
  }
}