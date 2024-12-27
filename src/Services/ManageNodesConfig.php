<?php
declare(strict_types = 1);

namespace Drupal\migration_hbk_auto\Services;

use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\file\Entity\File;
use Drupal\Component\Serialization\Json;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\File\FileExists;

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
   * Charge l'image si elle est deja importé sinon essaie de l'impoté et retoune
   * le object image..
   *
   * @param string|int $fid
   * @return \Drupal\file\Entity\File
   */
  static function CheckAndImportImageFromD7($fid) {
    $file = File::load($fid);
    if (!$file) {
      /**
       * On importe le fichier sur Drupal 7.
       */
      /**
       * Doit etre configurable afin qu'on puisse le connecté sur un autre site.
       *
       * @var string $domain_D7
       */
      $domain_D7 = "http://you-v7.kksa";
      $file_D7 = self::http_get_contents($domain_D7 . "/migrateexport/export-import-entity/load-file/" . $fid);
      if (!$file_D7)
        throw new \Exception("Le fichier n'a pas été trouvé sur le serveur source");
      $file_D7 = Json::decode($file_D7);
      /**
       *
       * @var \Drupal\Core\File\FileSystem $filesystem
       */
      $filesystem = \Drupal::service('file_system');
      
      $data = self::http_get_contents($file_D7['url']);
      if (!empty($data)) {
        // Check the directory exists before writing data to it.
        $file_info = self::getBasePathFromUri($file_D7['uri']);
        if ($filesystem->prepareDirectory($file_info['base_path'], FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
          $newUri = $filesystem->saveData($data, $file_D7['uri'], FileExists::Replace);
          $file = File::create([
            'fid' => $fid,
            'uri' => $newUri,
            'filename' => $file_D7['filename']
          ]);
          $file->setOwnerId($file_D7['uid']);
          $file->setPermanent();
          $file->save();
          return $file;
        }
        else
          throw new \Exception("Une erreur s'est produite, le fichier n'a pas pu etre creer");
      }
      else
        throw new \Exception("Le fichier n'a pas pu etre telecharger");
    }
    else
      return $file;
  }
  
  /**
   * Permet d'analyser la configuration
   */
  function CheckConfiguration($config_id, $datas) {
    [
      $entity_id,
      $bundle_key,
      $bundle
    ] = explode(".", $config_id);
    if ($entity_id == 'taxonomy_term') {
      $bundle_key = 'vid';
      $config_id = "taxonomy.vocabulary." . $bundle;
    }
    elseif ($entity_id == 'paragraph') {
      $config_id = "paragraphs.paragraphs_type." . $bundle;
    }
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
    $results['fields']['value'] = $this->getFields($entity_id, $bundle);
    $results['fields']['status'] = $this->compareFieldsD7__D10($results['fields'], $results['fields']['value'], $datas['fields']);
    if ($results['fields']['status']) {
      $results['fields']['note'] = 'Les champs sont ok';
    }
    if ($count = $this->CountEntities($entity_id, $bundle, $bundle_key)) {
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
  
  static protected function http_get_contents($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
  }
  
  /**
   * Le uri contient egalement le nom de l'image, l'idée est de separer les
   * deux.
   * RQ: dans row on a un filename, mais ce dernier est parfois different ce
   * celui au niveau de URI, et cela peut generer une erreur.
   */
  static protected function getBasePathFromUri(string $uri) {
    $f1 = explode("://", $uri);
    $f2 = explode("/", $f1[1]);
    $basePath = $f1[0] . "://";
    $nbre = count($f2) - 1;
    if ($nbre) {
      for ($i = 0; $i < $nbre; $i++) {
        $basePath .= $f2[$i] . "/";
      }
      $filename = $f2[$nbre];
    }
    else {
      $basePath .= "migrations";
      $filename = $f2[0];
    }
    return [
      'base_path' => $basePath,
      'filename' => $filename
    ];
  }
}
