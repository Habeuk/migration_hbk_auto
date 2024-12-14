<?php
declare(strict_types = 1);

namespace Drupal\migration_hbk_auto\Services;

use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class ManageNodesConfig extends ManageFieldsConfig {
  /**
   * The config storage.
   *
   * @var \Drupal\Core\Config\CachedStorage
   */
  protected $configStorage;
  protected $EntityTypeManager;
  protected $ConfigManager;
  
  function __construct(StorageInterface $config_storage, EntityTypeManagerInterface $EntityTypeManager, ConfigManager $ConfigManager) {
    parent::__construct($config_storage, $EntityTypeManager, $ConfigManager);
  }
  
  /**
   * Permet d'analyser la configuration
   */
  function CheckConfiguration($config_id, $datas) {
    $configD7 = $datas['content'];
    $results = [
      'config' => [
        'note' => "La configuration n'existe pas",
        'status' => false
      ],
      'fields' => [
        'note' => "Les champs n'existe pas",
        'status' => false
      ],
      'count_entities' => [
        'note' => "Aucun contenu importer",
        'status' => false
      ]
    ];
    /**
     * 1: Verification de la config.
     */
    $config = $this->config($config_id);
    if (!$config->isNew()) {
      $results['config']['note'] = "La configuration existe";
      $results['config']['value'] = $config->getRawData();
      $results['config']['status'] = true;
    }
    // Verification des champs.
    $results['fields']['value'] = $this->getFields("node", $configD7['type']);
    $results['fields']['status'] = $this->compareFieldsD7__D10($results['fields'], $results['fields']['value'], $datas['fields']);
    if ($results['fields']['status']) {
      $results['fields']['note'] = 'Les champs sont ok';
    }
    if ($count = $this->CountEntities("node", $configD7['type'])) {
      $results['count_entities']['status'] = true;
      $results['count_entities']['note'] = "Contenu importés : " . $count;
    }
    /**
     * 2: Verification des modes d'affichages.
     */
    //
    /**
     * 3: Verification des modes d'editions.
     */
    
    return $results;
  }
  
  protected function compareFieldsD7__D10(&$results, $newFields, $olds_fields) {
    $status = true;
    $results['errors'] = [];
    foreach ($olds_fields as $fieldName => $value) {
      if (empty($newFields[$fieldName]['field_config'])) {
        $status = false;
        $results['errors'][$fieldName] = $value;
      }
      
      if ($status) {
        //
      }
    }
    return $status;
  }
  
  /**
   * Cest un tableau qui contient les bases de l'informations.
   *
   * @param array $configD7
   */
  function buildConfigFromD7($configD7) {
    // Les données de bases contenants le bundle.
    $content = $configD7['content'];
    $values = [
      'status' => true,
      'name' => $content['name'],
      'new_revision' => false,
      'preview_mode' => 1,
      'type' => $content['type'],
      'description' => $content['description'],
      'dependencies' => [],
      'display_submitted' => true,
      'help' => $content['help']
      // 'langcode' => 'en' On doit verifier cette information.
    ];
    return $values;
  }
}
