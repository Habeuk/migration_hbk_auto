<?php
declare(strict_types = 1);

namespace Drupal\migration_hbk_auto\Services;

use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Component\Serialization\Json;
use Stephane888\Debug\ExceptionExtractMessage;

class ManageFieldsConfig extends ControllerBase {
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
   * Permet de generer et d'importer la configuration des champs.
   */
  public function generateConfigFields(string $entity_type, string $bundle, array $fields) {
    $new_fields = [];
    foreach ($fields as $field) {
      $field_type = $field['value']['field_type'];
      $fieldName = $field['id'];
      $new_fields[$fieldName] = [
        'value_storage_config' => [],
        'value' => []
      ];
      $values_storage_config = [];
      $id_storage_config = $entity_type . '.' . $fieldName;
      $field_storage_config = $this->config($id_storage_config);
      switch ($field_type['type']) {
        case "text_long":
          if ($field_storage_config->isNew()) {
            $values_storage_config = [
              "cardinality" => $field_type['cardinality'],
              'dependencies' => [
                'module' => [
                  $field_type['module'],
                  $entity_type
                ],
                'config' => []
              ],
              'entity_type' => $entity_type,
              'field_name' => $fieldName,
              'id' => $id_storage_config,
              // 'langcode' => "und",
              'status' => $field_type['active'],
              'translatable' => $field_type['translatable'],
              'type' => $field_type['type']
            ];
          }
          break;
        
        default:
          $new_fields[$fieldName]['note'] = ' Champs "' . $field['value']['label'] . '" n\'est pas traité, type de champs :' . $field_type['type'];
          $new_fields[$fieldName]['status'] = false;
          $new_fields[$fieldName]['value'] = $field_type;
          break;
      }
      if ($values_storage_config) {
        try {
          $new_fields[$fieldName]['value_storage_config'] = $this->importConfig($id_storage_config, $values_storage_config);
          $new_fields[$fieldName]['note'] = 'Champs "' . $field['value']['label'] . '" crée';
          $new_fields[$fieldName]['status'] = true;
        }
        catch (\Exception $e) {
          $new_fields[$fieldName]['note'] = $e->getMessage();
          $new_fields[$fieldName]['status'] = false;
          $new_fields[$fieldName]['value_storage_config'] = $values_storage_config;
        }
      }
      elseif (empty($new_fields[$fieldName]['value'])) {
        $new_fields[$fieldName]['note'] = 'Champs "' . $field['value']['label'] . '" existe deja';
        $new_fields[$fieldName]['status'] = true;
        $new_fields[$fieldName]['value_storage_config'] = $this->ConfigManager->readConfigById($id_storage_config);
      }
    }
    return $new_fields;
  }
  
  protected function importConfig(string $name, array $values) {
    $configData = Json::encode($values);
    $this->ConfigManager->importConfig($name, $configData);
    return $this->ConfigManager->readConfigById($name);
  }
  
  /**
   * Recupere les champs creer au niveau de l'administration pour une entite.
   *
   * @param string $entiy_type_id
   * @param string $bundle
   * @return array[][]|mixed[][][]|number|array
   */
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
}