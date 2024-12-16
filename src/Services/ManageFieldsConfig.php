<?php
declare(strict_types = 1);

namespace Drupal\migration_hbk_auto\Services;

use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Component\Serialization\Json;
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
  
  function __construct(StorageInterface $config_storage, EntityTypeManagerInterface $EntityTypeManager, ConfigManager $ConfigManager) {
    $this->configStorage = $config_storage;
    $this->EntityTypeManager = $EntityTypeManager;
    $this->ConfigManager = $ConfigManager;
  }
  
  /**
   * Permet de generer et d'importer la configuration des champs.
   */
  public function generateConfigFields(string $entity_type, string $bundle, array $fields, string $bundle_key = "type") {
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
          ] = $this->Build__string($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config, $bundle_key);
          break;
        case "text_with_summary":
        case "text_long":
          [
            $values_storage_config,
            $values_field_config
          ] = $this->Build__text_long($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config, $bundle_key);
          
          break;
        case 'list_boolean':
          $field_type["type"] = "boolean";
          [
            $values_storage_config,
            $values_field_config
          ] = $this->Build__list_element($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config, $bundle_key);
          $values_field_config["settings"]["on_label"] = $values_field_config["settings"]["on_label"] ?? "On";
          $values_field_config["settings"]["off_label"] = $values_field_config["settings"]["off_label"] ?? "Off";
          break;
        case "list_integer":
          $field_type["type"] = "integer";
          [
            $values_storage_config,
            $values_field_config
          ] = $this->Build__list_element($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config, $bundle_key);
          break;
        case "image":
          [
            $values_storage_config,
            $values_field_config
          ] = $this->Build__image($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config, $bundle_key);
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
          ] = $this->Build__entityreference($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config, $bundle_key);
          
          break;
        case "colorfield":
          [
            $values_storage_config,
            $values_field_config
          ] = $this->Build__colorfield($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config, $bundle_key);
          
          break;
        case "multifield":
          [
            $values_storage_config,
            $values_field_config
          ] = $this->build__multifield($field, $entity_type, $bundle, $id_storage_config, $id_field_config, $field, $bundle_key);
          $new_fields[$fieldName]['status'] = true;
          $new_fields[$fieldName]['value'] = $field_type;
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
            $new_fields[$fieldName]['id_storage_config'] = $id_storage_config;
            $new_fields[$fieldName]['id_field_config'] = $id_field_config;
          }
          catch (\Exception $e) {
            $hasError = true;
            $new_fields[$fieldName]['note'] = $e->getMessage();
            $new_fields[$fieldName]['status'] = false;
            $new_fields[$fieldName]['field_config'] = $values_field_config;
            $new_fields[$fieldName]['field_storage_config'] = $values_storage_config;
            $new_fields[$fieldName]['errors'] = ExceptionExtractMessage::errorAll($e);
            $new_fields[$fieldName]['value'] = $field;
            $new_fields[$fieldName]['id_storage_config'] = $id_storage_config;
            $new_fields[$fieldName]['id_field_config'] = $id_field_config;
          }
        if ($values_field_config && !$hasError)
          try {
            $new_fields[$fieldName]['field_config'] = $this->importConfig('field.field.' . $id_field_config, $values_field_config);
            $new_fields[$fieldName]['note'] = 'Champs "' . $field['value']['label'] . '" crée';
            $new_fields[$fieldName]['status'] = true;
            $new_fields[$fieldName]['id_storage_config'] = $id_storage_config;
            $new_fields[$fieldName]['id_field_config'] = $id_field_config;
          }
          catch (\Exception $e) {
            $hasError = true;
            $new_fields[$fieldName]['note'] = $e->getMessage();
            $new_fields[$fieldName]['status'] = false;
            $new_fields[$fieldName]['field_config'] = $values_field_config;
            $new_fields[$fieldName]['field_storage_config'] = $values_storage_config;
            $new_fields[$fieldName]['errors'] = ExceptionExtractMessage::errorAll($e);
            $new_fields[$fieldName]['value'] = $field['value'];
            $new_fields[$fieldName]['id_storage_config'] = $id_storage_config;
            $new_fields[$fieldName]['id_field_config'] = $id_field_config;
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
  
  protected function changeFieldEntity(&$field, $entity_type) {
    $field["entity_type"] = $entity_type;
    $bundles = [];
    
    foreach ($field["field_type"]["bundles"]["multifield"] as $bundle_id) {
      if (ParagraphsType::load($bundle_id)) {
        $bundles[] = $bundle_id;
      }
    }
    $field["field_type"]["bundles"] = [
      $entity_type => $bundles
    ];
    return $field;
  }
  
  /**
   *
   * @return array|null field entity_reference field definition
   */
  protected function build__multifield($multifield, $entity_type, $bundle, $id_storage_config, $id_field_config, $field_multifield, $bundle_key) {
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
    $fields = $multifield["value"]["field_type"]["sub_fields"];
    foreach ($fields as $field_id => &$field) {
      $this->changeFieldEntity($field, "paragraph");
      $field = [
        "id" => $field_id,
        "label" => $field["label"],
        "value" => $field
      ];
    }
    // on construit les sous champs.
    $this->generateConfigFields("paragraph", $paragrapheType->id(), $fields, "paragraphs_type");
    
    // On ajuste les valeurs de field_type
    $field_type = $multifield["value"]["field_type"];
    $field_type['module'] = 'paragraphs';
    $field_type['type'] = 'entity_reference';
    return $this->createFieldForMultifield($field_type, $entity_type, $multifield["id"], $id_storage_config, $bundle, $field_multifield, $id_field_config, $bundle_key);
  }
  
  protected function createFieldForMultifield($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config, $bundle_key) {
    $configs = $this->Build__base($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config, $bundle_key);
    // override
    return $configs;
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
  
  protected function Build__entityreference($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config, $bundle_key) {
    $configs = $this->Build__base($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config, $bundle_key);
    // overrride
    $this->addDependencyEntityreference($configs[0], 'field_storage_config', $field_type);
    $this->addDependencyEntityreference($configs[1], 'field_config', $field_type);
    return $configs;
  }
  
  protected function addDependencyEntityreference(array &$config, string $type, array $field_type) {
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
      if (!empty($config["dependencies"]["module"])) {
        $keyToDel = array_search("entityreference", $config["dependencies"]["module"]);
        if ($keyToDel !== false) {
          unset($config["dependencies"]["module"][$keyToDel]);
          $config["dependencies"]["module"] = array_values($config["dependencies"]["module"]);
        }
      }
    }
  }
  
  protected function Build__string($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config, $bundle_key) {
    $field_type["type"] = "string";
    $configs = $this->Build__base($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config, $bundle_key);
    return $configs;
  }
  
  protected function Build__list_element($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config, $bundle_key) {
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
    $configs = $this->Build__base($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config, $bundle_key);
    $this->deleteDependency("list", $configs[0]);
    $this->deleteDependency("list", $configs[1]);
    return $configs;
  }
  
  protected function deleteDependency($dependency, &$configs) {
    if (!empty($configs["dependencies"]["module"])) {
      $keyToDel = array_search("list", $configs["dependencies"]["module"]);
      if ($keyToDel !== false) {
        unset($configs["dependencies"]["module"][$keyToDel]);
        $configs["dependencies"]["module"] = array_values($configs["dependencies"]["module"]);
      }
    }
  }
  
  protected function Build__image($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config, $bundle_key) {
    $config = $this->Build__base($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config, $bundle_key);
    // overrride
    return $config;
  }
  
  protected function Build__text_long($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config, $bundle_key) {
    $config = $this->Build__base($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config, $bundle_key);
    // overrride
    return $config;
  }
  
  protected function Build__colorfield($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config, $bundle_key) {
    $configs = $this->Build__base($field_type, $entity_type, $fieldName, $id_storage_config, $bundle, $field, $id_field_config, $bundle_key);
    // Overrride
    $this->overrrideDependencycolorfield($configs[0], 'field_storage_config', $field_type);
    $this->overrrideDependencycolorfield($configs[1], 'field_config', $field_type);
    return $configs;
  }
  
  protected function overrrideDependencycolorfield(array &$config, string $type, $field_type) {
    // On retire le module "colorfield"
    if (!empty($config["dependencies"]["module"])) {
      $keyToDel = array_search("colorfield", $config["dependencies"]["module"]);
      if ($keyToDel !== false) {
        unset($config["dependencies"]["module"][$keyToDel]);
      }
    }
    // on ajoute la dependance au module 'generate_style_theme'
    $config["dependencies"]["module"][] = "generate_style_theme";
    if ($type == 'field_storage_config')
      $config['type'] = 'color_theme_field_type';
    if ($type == 'field_config')
      $config['field_type'] = 'color_theme_field_type';
  }
  
  protected function Build__base(array $field_type, string $entity_type, string $fieldName, string $id_storage_config, string $bundle, array $field, string $id_field_config, $bundle_key = "type") {
    // on ajuste certains parametre.
    $dependence_field_config = "$entity_type.$bundle_key.$bundle";
    $module = $field_type['module'];
    $module2 = null;
    switch ($entity_type) {
      case "node":
        $module2 = "node";
        break;
      case "paragraph":
        $dependence_field_config = "paragraphs.$bundle_key.$bundle";
        $module = "paragraphs";
        $module2 = null;
        break;
      case "taxonomy_term":
        $dependence_field_config = "taxonomy.vocabulary.$bundle";
        $module = "taxonomy";
        $module2 = null;
        break;
    }
    $field_storage_config = !$this->entityTypeManager()->getStorage('field_storage_config')->load($id_storage_config) ? [
      "cardinality" => $field_type['cardinality'],
      'dependencies' => [
        'module' => [
          $module
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
    $field_config = !$this->entityTypeManager()->getStorage('field_config')->load($id_field_config) ? [
      "bundle" => $bundle,
      "default_value" => [],
      "default_value_callback" => "",
      "dependencies" => [
        'config' => [
          "field.storage." . $id_storage_config,
          $dependence_field_config
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
    if ($module2) {
      $field_storage_config['dependencies']['module'][] = $module2;
    }
    return [
      $field_storage_config,
      $field_config
    ];
    // throw new \ErrorException(" Le type de configuration de champs definit
    // n'est pas pris en compte");
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
  
  /**
   * Permet de retourner le nombre de contenu d'une entité.
   *
   * @param string $entity_type_id
   *        // exemple node
   * @param string $nundle
   *        // exemple article
   */
  protected function CountEntities(string $entity_type_id, $nundle = null, $bundle_key = "type") {
    $query = $this->entityTypeManager()->getStorage($entity_type_id)->getQuery();
    $query->accessCheck(TRUE);
    if ($nundle && $this->entityTypeManager()->getStorage($entity_type_id)->getEntityType()->getBundleEntityType()) {
      // on definie la clée du bundle à "type", on va modifier plus tard si
      // necessaire à detectant automatiquement la clée.
      $query->condition($bundle_key, $nundle);
    }
    return $query->count()->execute();
  }
}
