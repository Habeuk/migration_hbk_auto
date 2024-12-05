<?php
declare(strict_types = 1);

namespace Drupal\migration_hbk_auto\Services;

use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Controller\ControllerBase;

class ManageNodesConfig extends ControllerBase {
  /**
   * The config storage.
   *
   * @var \Drupal\Core\Config\CachedStorage
   */
  protected $configStorage;
  protected $EntityTypeManager;
  protected $ConfigManager;
  
  function __construct(StorageInterface $config_storage, EntityTypeManagerInterface $EntityTypeManager, ConfigManager $ConfigManager) {
    $this->configStorage = $config_storage;
    $this->EntityTypeManager = $EntityTypeManager;
    $this->ConfigManager = $ConfigManager;
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
      ]
    ];
    // verification de la config.
    $config = $this->config($config_id);
    if (!$config->isNew()) {
      $results['config']['note'] = "La configuration existe";
      $results['config']['value'] = $config->getRawData();
      $results['config']['status'] = true;
    }
    // verification des champs.
    $results['fields']['value'] = $this->getFields("node", $configD7['type']);
    $results['fields']['status'] = $this->compareFieldsD7__D10($results['fields'], $results['fields']['value'], $datas['fields']);
    if ($results['fields']['status']) {
      $results['fields']['note'] = 'Les champs sont ok';
    }
    // verification des modes d'affichages
    // verification des modes d'editions.
    return $results;
  }
  
  public function getFields($entiy_type_id, $bundle) {
    $fields = [];
    $queryField = $this->entityTypeManager()->getStorage('field_config')->getQuery();
    $queryField->accessCheck(TRUE);
    $queryField->condition('entity_type', $entiy_type_id);
    $queryField->condition('bundle', $bundle);
    $ids = $queryField->execute();
    foreach ($ids as $id) {
      $keys = explode(".", $id);
      $fields[$keys[2]] = $this->getConfigField($keys[0], $keys[1], $keys[2]);
      $fields[$keys[2]]['id'] = $id;
    }
    return $fields;
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
   * Permet de recuperer les configurations d'un champs.
   *
   * @param string $entity_type
   * @param string $bundle
   * @param string $fieldName
   */
  public function getConfigField($entity_type, $bundle, $fieldName) {
    $field = [
      'field_config' => [],
      'field_storage_config' => []
    ];
    /**
     *
     * @var \Drupal\field\Entity\FieldConfig $FieldConfig
     */
    $FieldConfig = $this->entityTypeManager()->getStorage('field_config')->load($entity_type . '.' . $bundle . '.' . $fieldName);
    if ($FieldConfig) {
      $field['field_config'] = $FieldConfig->toArray();
    }
    
    /**
     *
     * @var \Drupal\field\Entity\FieldStorageConfig $FieldStorageConfig
     */
    $FieldStorageConfig = $this->entityTypeManager()->getStorage('field_storage_config')->load($entity_type . '.' . $fieldName);
    if ($FieldStorageConfig) {
      $field['field_storage_config'] = $FieldStorageConfig->toArray();
    }
    return $field;
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
