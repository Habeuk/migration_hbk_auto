<?php
declare(strict_types = 1);

namespace Drupal\migration_hbk_auto\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Stephane888\DrupalUtility\HttpResponse;
use Drupal\Component\Serialization\Json;
use Stephane888\Debug\ExceptionExtractMessage;
use Stephane888\Debug\ExceptionDebug;
use Drupal\migration_hbk_auto\Services\ManageNodesConfig;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\Entity\File;

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
  
  public function CheckTermsExist(Request $Request) {
    $payload = Json::decode($Request->getContent());
    $results = [];
    try {
      if (!empty($payload['terms']) && !empty($payload['vocabularies'])) {
        $ids = [];
        foreach ($payload['terms'] as $value) {
          $ids[$value['tid']] = $value['tid'];
        }
        
        $query = $this->entityTypeManager()->getStorage("taxonomy_term")->getQuery();
        $query->accessCheck(TRUE);
        $query->condition('vid', $payload['vocabularies'], 'IN');
        $query->condition('tid', $ids, "IN");
        $new_ids = $query->execute();
        if ($new_ids) {
          foreach ($ids as $tid) {
            if (!empty($new_ids[$tid]))
              $results[$tid] = $tid;
            else
              $results[$tid] = false;
          }
        }
      }
      return HttpResponse::response($results);
    }
    catch (\Exception $e) {
      $results['errors'] = ExceptionExtractMessage::errorAll($e);
      return HttpResponse::response($results, 425, $e->getMessage());
    }
  }
  
  public function importFiles(Request $Request) {
    $payload = Json::decode($Request->getContent());
    $results = [];
    try {
      if (!empty($payload['files']) && !empty($payload['base_url'])) {
        /**
         *
         * @var \Drupal\Core\File\FileSystem $filesystem
         */
        // $filesystem = \Drupal::service('file_system');
        /** @var \Drupal\file\FileRepository $fileRepository */
        // $fileRepository = \Drupal::service('file.repository');
        foreach ($payload['files'] as $field) {
          $file = ManageNodesConfig::CheckAndImportImageFromD7($field['fid']);
          // if (!$file) {
          // $file_info = $this->getBasePathFromUri($field['uri']);
          // if ($filesystem->prepareDirectory($file_info['base_path'],
          // FileSystemInterface::CREATE_DIRECTORY |
          // FileSystemInterface::MODIFY_PERMISSIONS)) {
          // $array_uri = \explode("://", $field['uri']);
          // // Save the file.
          // $url = $payload['base_url'] . "/" . rawurlencode($array_uri[1]);
          // // Les images sont dans files
          // // $data = file_get_contents($url);
          // $data = $this->http_get_contents($url);
          // // Les images sont dans /styles/kitchen_image_hp/public
          // if (empty($data)) {
          // $url = $payload['base_url'] . "/styles/kitchen_image_hp/public/" .
          // rawurlencode($array_uri[1]);
          // $data = $this->http_get_contents($url);
          // }
          // if (!empty($data)) {
          // $newUri = $filesystem->saveData($data, $field['uri']);
          // // $file = $fileRepository->writeData($data, $field['uri']);
          // $file = File::create([
          // 'fid' => $field['fid'],
          // 'uri' => $newUri,
          // 'filename' => $field['filename']
          // ]);
          // $file->setOwnerId($this->currentUser()->id());
          // $file->setPermanent();
          // $file->save();
          // }
          // else
          // throw new ExceptionDebug("Le fichier n'a pas pu etre telecharger",
          // $url, 404);
          // }
          // else
          // throw new \Exception("Une erreur s'est produite, le fichier n'a pas
          // pu etre creer");
          // //
          // }
          $results[$file->id()] = [
            'id' => $file->id(),
            'filename' => $file->getFilename()
          ];
        }
      }
      return HttpResponse::response($results);
    }
    catch (ExceptionDebug $e) {
      $results['errors'] = ExceptionExtractMessage::errorAll($e);
      $results['file_not_find'] = $e->getContentToDebug();
      return HttpResponse::response($results, $e->getCode(), $e->getMessage());
    }
    catch (\Exception $e) {
      $results['errors'] = ExceptionExtractMessage::errorAll($e);
      return HttpResponse::response($results, 425, $e->getMessage());
    }
  }
  
  public function loadConfig(Request $Request) {
    $results = [];
    $payload = Json::decode($Request->getContent());
    try {
      // Verifie la presence de l'entite.
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
      if (!empty($payload['fields']) && !empty($payload['entity_type']) && !empty($payload['bundle']) && !empty($payload['bundle_key'])) {
        $results = $this->ManageNodesConfig->generateConfigFields($payload['entity_type'], $payload['bundle'], $payload['fields'], $payload['bundle_key']);
      }
      return HttpResponse::response($results);
    }
    catch (\Exception $e) {
      $results['errors'] = ExceptionExtractMessage::errorAll($e);
      return HttpResponse::response($results, 425, $e->getMessage());
    }
  }
  
  /**
   * Le uri contient egalement le nom de l'image, l'idée est de separer les
   * deux.
   * RQ: dans row on a un filename, mais ce dernier est parfois different ce
   * celui au niveau de URI, et cela peut generer une erreur.
   */
  protected function getBasePathFromUri(string $uri) {
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
  
  protected function http_get_contents($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec($ch);
    curl_close($ch);
    return $output;
  }
}