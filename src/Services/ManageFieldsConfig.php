<?php
declare(strict_types = 1);

namespace Drupal\migration_hbk_auto\Services;

use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\paragraphs\Entity\ParagraphsType;
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
  /**
   *
   * @var EntityFieldManagerInterface $entityFieldManager
   */
  protected $entityFieldManager;
  
  function __construct(StorageInterface $config_storage, EntityTypeManagerInterface $EntityTypeManager, EntityFieldManagerInterface $entity_field_manager, ConfigManager $ConfigManager) {
    $this->configStorage = $config_storage;
    $this->EntityTypeManager = $EntityTypeManager;
    $this->ConfigManager = $ConfigManager;
    $this->entityFieldManager = $entity_field_manager;
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
        'field_storage_config' => [],
        'value' => [],
        'field_config' => []
      ];
      $values_storage_config = [];
      $values_field_config = [];
      $id_storage_config = $entity_type . '.' . $fieldName;
      $id_field_config = $entity_type . '.' . $bundle . '.' . $fieldName;
      // $field_storage_config = $this->config($id_storage_config);
      // $field_config = $this->config($id_field_config);
      switch ($field_type['type']) {
        case "text":
          [
            $values_storage_config,
            $values_field_config
          ] = $this->Build__string($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config);
          break;
        case "text_with_summary":
        case "text_long":
          [
            $values_storage_config,
            $values_field_config
          ] = $this->Build__text_long($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config);
          
          break;
        case 'list_boolean':
          $field_type["type"] = "boolean";
          [
            $values_storage_config,
            $values_field_config
          ] = $this->Build__list_element($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config);
          $values_field_config["settings"]["on_label"] = $values_field_config["settings"]["on_label"] ?? "On";
          $values_field_config["settings"]["off_label"] = $values_field_config["settings"]["off_label"] ?? "Off";
          break;
        case "list_integer":
          $field_type["type"] = "integer";
          [
            $values_storage_config,
            $values_field_config
          ] = $this->Build__list_element($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config);
          break;
        case "image":
          [
            $values_storage_config,
            $values_field_config
          ] = $this->Build__image($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config);
          
          break;
        case "taxonomy_term_reference":
          $label = $field["label"];
          $description = $field["value"]["description"];
          [
            $values_storage_config,
            $values_field_config
          ] = $this->Build__taxonomy($field_type, $entity_type, $bundle, $label, $description);
          break;
        case "entityreference":
          [
            $values_storage_config,
            $values_field_config
          ] = $this->Build__entityreference("field_storage_config", $field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config);
          
          break;
        case "multifield":
          $this->build__multifield($field, $entity_type, $bundle);
          dd("pause");
          break;
        default:
          $new_fields[$fieldName]['note'] = ' Champs "' . $field['value']['label'] . '" n\'est pas traité, type de champs :' . $field_type['type'];
          $new_fields[$fieldName]['status'] = false;
          $new_fields[$fieldName]['value'] = $field_type;
          break;
      }
      if ($values_storage_config || $values_field_config) {
        $hasError = false;
        if ($values_storage_config)
          try {
            $new_fields[$fieldName]['field_storage_config'] = $this->importConfig('field.storage.' . $id_storage_config, $values_storage_config);
            $new_fields[$fieldName]['note'] = 'Champs "' . $field['value']['label'] . '" crée';
            $new_fields[$fieldName]['status'] = true;
          }
          catch (\Exception $e) {
            $hasError = true;
            $new_fields[$fieldName]['note'] = $e->getMessage();
            $new_fields[$fieldName]['status'] = false;
            $new_fields[$fieldName]['field_storage_config'] = $values_storage_config;
          }
        if ($values_field_config && !$hasError)
          try {
            $new_fields[$fieldName]['field_config'] = $this->importConfig('field.field.' . $id_field_config, $values_field_config);
            $new_fields[$fieldName]['note'] = 'Champs "' . $field['value']['label'] . '" crée';
            $new_fields[$fieldName]['status'] = true;
          }
          catch (\Exception $e) {
            $hasError = true;
            $new_fields[$fieldName]['note'] = $e->getMessage();
            $new_fields[$fieldName]['status'] = false;
            $new_fields[$fieldName]['field_config'] = $values_field_config;
          }
      }
      elseif (empty($new_fields[$fieldName]['value'])) {
        $new_fields[$fieldName]['note'] = 'Champs "' . $field['value']['label'] . '" existe deja';
        $new_fields[$fieldName]['status'] = true;
        $new_fields[$fieldName]['field_storage_config'] = $this->ConfigManager->readConfigById('field.storage.' . $id_storage_config);
        $new_fields[$fieldName]['field_config'] = $this->ConfigManager->readConfigById('field.field.' . $id_field_config);
      }
    }
    return $new_fields;
  }
  
  /**
   * Import via les mecanismes d'import, il verifie egalement que
   * l'environnement est pret.
   *
   * @param string $name
   *        Le nom doit contenir le prexise.
   * @param array $values
   * @return array|boolean
   */
  protected function importConfig(string $name, array $values) {
    if (str_contains($name, "field.storage.") || str_contains($name, "field.field.")) {
      $configData = Json::encode($values);
      $this->ConfigManager->importConfig($name, $configData);
      if ($config = $this->ConfigManager->readConfigById($name)) {
        return $config;
      }
      throw new \ErrorException(" Une erreur s'est produite lors de la creation de configuration " . $name);
    }
    else {
      throw new \ErrorException(" La module : '$name', ne respecte pas la structure. Doit commencer par 'field.storage.' ou 'field.field.' ");
    }
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
  
  protected function Build__taxonomy($field_type, $entityType, $bundle, $label, $description) {
    $vocabulary = $field_type['settings']['handler_settings']["target_bundles"];
    $target_bundles = [];
    foreach ($field_type["settings"]["allowed_values"] as $voc) {
      $target_bundles[$voc["vocabulary"]] = $voc["vocabulary"];
    }
    $storage = [
      'field_name' => $field_type['field_name'],
      'entity_type' => $entityType,
      'type' => 'entity_reference',
      'settings' => [
        'target_type' => 'taxonomy_term'
      ],
      'cardinality' => $field_type['cardinality'],
      'translatable' => $field_type['translatable'],
      'indexes' => [
        'target_id' => [
          'target_id'
        ]
      ],
      'foreign keys' => [
        'target_id' => [
          'table' => 'taxonomy_term_field_data',
          'columns' => [
            'target_id' => 'tid'
          ]
        ]
      ]
    ];
    
    $config = [
      'field_name' => $field_type['field_name'],
      'field_type' => "entity_reference",
      'entity_type' => $entityType,
      'bundle' => $bundle,
      'label' => $label,
      'description' => $description,
      'required' => FALSE,
      'settings' => [
        'handler' => 'default',
        'handler_settings' => [
          'target_bundles' => $target_bundles
        ]
      ],
      'widget' => [
        'type' => 'entity_reference_autocomplete',
        'settings' => []
      ]
    ];
    
    return [
      $storage,
      $config
    ];
  }
  
  /**
   *
   * @return array|null field entity_reference field definition
   */
  protected function build__multifield($multifield, $entity_type, $bundle) {
    $label = "multifield " . $multifield["label"];
    $fields = [];
    /**
     *
     * @var \Drupal\field\FieldStorageConfigStorage
     */
    $configStorageManager = $this->entityTypeManager()->getStorage('field_storage_config');
    /**
     *
     * @var \Drupal\field\FieldConfigStorage
     */
    $fieldConfigManager = $this->entityTypeManager()->getStorage('field_config');
    if (strlen($label) >= 25) {
      $label = substr($label, 0, 25);
    }
    $paragrapheType = $this->create_or_select_paragraph_type($multifield["id"], $label, $multifield["value"]["description"]);
    $fieldList = $multifield["value"]["field_type"]["storage"]["details"]["sql"]["FIELD_LOAD_CURRENT"];
    
    foreach ($fieldList as $key => $value) {
      unset($value["id"]);
      $fields = \array_map(function ($element) {
        $array = \explode("_", $element);
        unset($array[count($array) - 1]);
        $field_key = \implode("_", $array);
        return $field_key;
      }, array_keys($value));
      $fields = \array_values(array_unique($fields));
    }
    \Stephane888\Debug\debugLog::kintDebugDrupal($fields, '');
    dd($fields);
    foreach ($fields as $field_id) {
      $paragraph_field_definition = $configStorageManager->load("paragraph." . $field_id);
      if (!$paragraph_field_definition) {
        /**
         *
         * @var \Drupal\field\Entity\FieldStorageConfig $source_field_definition
         */
        $source_field_definition = $configStorageManager->load($entity_type . "." . $field_id);
        if (!$source_field_definition) {
          dd($entity_type . "." . $field_id);
        }
        $paragraph_storage_configs = [
          'field_name' => $source_field_definition->getName(), // Use the same
                                                                // field name.
          'entity_type' => 'paragraph', // Set the entity type to 'paragraph'.
          'type' => $source_field_definition->getType(), // Copy the field
                                                          // type.
          'settings' => $source_field_definition->getSettings() // Copy the
                                                                // settings.
        ];
        $paragraph_field_definition = $configStorageManager->create($paragraph_storage_configs);
        $paragraph_field_definition->save();
      }
      $paragraph_field_config = $fieldConfigManager->loadByProperties([
        "field_name" => $field_id,
        "entity_type" => "paragraph"
      ]);
      if (!$paragraph_field_config) {
        /**
         *
         * @var \Drupal\field\Entity\FieldConfig
         */
        $source_field_config = $fieldConfigManager->load($entity_type . "." . $bundle . "." . $field_id);
        if ($source_field_config) {
          $configs = [
            'field_name' => $source_field_config->getName(), // The same field
                                                              // name as above.
            'bundle' => $paragrapheType->id(), // Replace with your paragraph
                                                // type machine name.
            'entity_type' => 'paragraph',
            'label' => $source_field_config->getLabel(), // The label for the
                                                          // field.
            'required' => $source_field_config->isRequired(), // Set to TRUE if
                                                               // the field is
                                                               // required.
            'settings' => $source_field_config->getSettings() // Additional
                                                              // settings can be
                                                              // defined here if
                                                              // needed.
          ];
          $paragraph_field_config = $fieldConfigManager->create($configs);
          $paragraph_field_config->save();
        }
      }
      else {
        if (!$paragraph_field_config["paragraph." . $paragrapheType->id() . "." . $field_id]) {
          dd($paragraph_field_config);
        }
        else {
        }
      }
    }
    dd($paragrapheType);
    return null;
  }
  
  protected function create_or_select_paragraph_type($id, $label, $description) {
    $paragraph_type = ParagraphsType::load($id);
    
    // If the paragraph type exists, return it.
    if ($paragraph_type) {
      return $paragraph_type;
    }
    
    // Otherwise, create a new paragraph type.
    $paragraph_type = ParagraphsType::create(
      [
        'id' => $id,
        'label' => $label,
        'status' => true,
        'dependencies' => [],
        'icon_uuid' => null,
        'icon_default' => null,
        'behavior_plugins' => [],
        'description' => $description
      ]);
    $paragraph_type->save();
    
    return $paragraph_type;
  }
  
  protected function Build__entityreference($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config) {
    $configs = $this->Build__base($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config);
    // overrride
    $this->addDependencyEntityreference($configs[1], 'field_config');
    $this->addDependencyEntityreference($configs[0], 'field_storage_config');
    
    return $configs;
  }
  
  protected function addDependencyEntityreference(&$config, $type, $field_type) {
    if ($type == 'field_config') {
      // Le type de champs 'entityreference' devient "entity_reference".
      $config['field_type'] = 'entity_reference';
      // Le module "entityreference" n'est pas requis, on le supprime.
      foreach ($config['dependencies']['module'] as $key => $moduleName) {
        if ($moduleName == 'entityreference')
          unset($config['dependencies']['module'][$key]);
      }
      // Definition du settings.
      if ($field_type['settings']['handler'] == 'views') {
        $config['settings'] = [
          'handler' => 'views',
          'handler_settings' => [
            'view' => [
              'view_name' => $field_type['settings']['handler_settings']['view']['view_name'],
              'display_name' => $field_type['settings']['handler_settings']['view']['display_name'],
              'arguments' => $field_type['settings']['handler_settings']['view']['args']
            ]
          ]
        ];
      }
      elseif ($field_type['settings']['handler'] == 'base') {
        $target_bundles = $field_type['settings']['handler_settings']['target_bundles'] ?? "";
        $config['settings'] = [
          'handler' => 'default:' . $field_type['settings']['target_type'],
          'handler_settings' => [
            'target_bundles' => $target_bundles
          ]
        ];
      }
      else {
        throw new \ErrorException(" Probleme dans la configuration du setting du champs reference ");
      }
    }
    if ($type == "field_storage_config") {
      $config["type"] = "entity_reference";
      $keyToDel = array_search("entityreference", $config["dependencies"]["module"]);
      if ($keyToDel !== false) {
        unset($config["dependencies"]["module"][$keyToDel]);
        $config["dependencies"]["module"] = array_values($config["dependencies"]["module"]);
      }
    }
  }
  
  protected function Build__string($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config) {
    $field_type["type"] = "string";
    $configs = $this->Build__base($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config);
    return $configs;
  }
  
  protected function Build__list_element($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config) {
    /**
     *
     * @var \Drupal\field\Entity\FieldStorageConfig $storage
     */
    $storage = $this->entityTypeManager()->getStorage('field_storage_config')->load($id_storage_config);
    if ($storage && $storage->get("cardinality") != -1) {
      $storage->setCardinality(-1);
      $storage->save();
    }
    $field_type["cardinality"] = "-1";
    $configs = $this->Build__base($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config);
    $this->deleteDependency("list", $configs[0]);
    $this->deleteDependency("list", $configs[1]);
    return $configs;
  }
  
  protected function deleteDependency($dependency, &$configs) {
    if (isset($configs["dependencies"]["module"])) {
      $keyToDel = array_search("list", $configs["dependencies"]["module"]);
      if ($keyToDel !== false) {
        unset($configs["dependencies"]["module"][$keyToDel]);
        $configs["dependencies"]["module"] = array_values($configs["dependencies"]["module"]);
      }
    }
  }
  
  protected function Build__image($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config) {
    $config = $this->Build__base($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config);
    // overrride
    return $config;
  }
  
  protected function Build__text_long($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config) {
    $config = $this->Build__base($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config);
    // overrride
    return $config;
  }
  
  protected function Build__base($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config) {
    $result = [];
    
    $result[] = !$this->entityTypeManager()->getStorage('field_storage_config')->load($id_storage_config) ? [
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
      'status' => (int) $field_type['active'],
      'translatable' => (int) $field_type['translatable'],
      'type' => $field_type['type'],
      'settings' => []
    ] : [];
    $result[] = !$this->entityTypeManager()->getStorage('field_config')->load($id_field_config) ? [
      "bundle" => $bundle,
      "default_value" => [],
      "default_value_callback" => "",
      "dependencies" => [
        'config' => [
          "field.storage." . $id_storage_config,
          $entity_type . ".type." . $bundle
        ],
        "module" => [
          $field_type['module']
        ]
      ],
      "description" => $field['value']['description'],
      "entity_type" => $entity_type,
      "field_name" => $fieldName,
      'field_type' => $field_type['type'],
      'id' => $id_field_config,
      'label' => $field['label'],
      'langcode' => "und",
      'required' => $field['value']['required'],
      'settings' => [],
      'status' => (int) $field_type['active'],
      'translatable' => (int) $field_type['translatable']
    ] : [];
    return $result;
    // throw new \ErrorException(" Le type de configuration de champs definit
    // n'est pas pris en compte");
  }
  
  /**
   *
   * @deprecated code use Build__base()
   * @param string $type
   * @param string $field_type
   * @param string $entity_type
   * @param string $fieldName
   * @param string $id_storage_config
   * @param string $bundle
   * @param string $field
   * @param string $id_field_config
   * @return []
   */
  protected function Build__base_config($type, $field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config) {
    if ($type == 'field_storage_config')
      return [
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
        'status' => (int) $field_type['active'],
        'translatable' => (int) $field_type['translatable'],
        'type' => $field_type['type'],
        'settings' => []
      ];
    if ($type == 'field_config')
      return [
        "bundle" => $bundle,
        "default_value" => [],
        "default_value_callback" => "",
        "dependencies" => [
          'config' => [
            "field.storage." . $id_storage_config,
            $entity_type . ".type." . $bundle
          ],
          "module" => [
            $field_type['module']
          ]
        ],
        "description" => $field['value']['description'],
        "entity_type" => $entity_type,
        "field_name" => $fieldName,
        'field_type' => $field_type['type'],
        'id' => $id_field_config,
        'label' => $field['label'],
        'langcode' => "und",
        'required' => $field['value']['required'],
        'settings' => [],
        'status' => (int) $field_type['active'],
        'translatable' => (int) $field_type['translatable']
      ];
    throw new \ErrorException(" Le type de configuration de champs definit n'est pas pris en compte");
  }
  
  /**
   * Import via l'entite.
   * (preferable d'utiliser importConfig )
   *
   * @param string $name
   * @param array $values
   * @return mixed[]
   */
  protected function importConfig__field_config(string $name, array $values) {
    $FieldConfig = $this->entityTypeManager()->getStorage('field_config')->create($values);
    $FieldConfig->save();
    return $FieldConfig->toArray();
  }
  
  /**
   * import via l'entite.
   * (preferable d'utiliser importConfig )
   *
   * @param string $name
   * @param array $values
   * @return mixed[]
   */
  protected function importConfig__storage_config(string $name, array $values) {
    $FieldStorageConfig = $this->entityTypeManager()->getStorage('field_storage_config')->create($values);
    $FieldStorageConfig->save();
    return $FieldStorageConfig->toArray();
  }
}
