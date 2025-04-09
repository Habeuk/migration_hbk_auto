<?php

namespace Drupal\migration_hbk_auto\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Component\Serialization\Json;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\ContentEntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\layout_builder\Section;
use Stephane888\Debug\ExceptionDebug;
use Stephane888\DrupalUtility\HttpResponse;
use Stephane888\Debug\ExceptionExtractMessage;
use Drupal\apivuejs\Services\DuplicateEntityReference;
use Drupal\apivuejs\Services\GenerateForm;
use Drupal\Component\Serialization\Yaml;
use Drupal\apivuejs\Controller\ApivuejsController;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\migration_hbk_auto\Services\ManageNodesConfig;

/**
 * Returns responses for Api vuejs routes.
 */
class MigrationHbkAutoApivuejsController extends ApivuejsController {
  /**
   *
   * @var FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  public function __construct(DuplicateEntityReference $DuplicateEntityReference, GenerateForm $GenerateForm, FileUrlGeneratorInterface $FileUrlGenerator) {
    $this->fileUrlGenerator = $FileUrlGenerator;
    parent::__construct($DuplicateEntityReference, $GenerateForm);
  }

  /**
   *
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('apivuejs.duplicate_reference'), $container->get('apivuejs.getform'), $container->get('file_url_generator'));
  }

  /**
   * On surcharge la methode extractEntity afin de rerirer le JSON contenu dans
   * le texte et le remplacer par du cotnenu apporiprié
   *
   * @param Request $Request
   * @return Array|false
   */
  protected function extractEntity(Request $Request) {
    $datas = Json::decode($Request->getContent());
    $datas["entity"] = $datas["entity"] ?? $datas;
    if (!empty($datas["entity"])) {
      /**
       * Les types de paragraphes.
       *
       * @var array $type_paragraph
       */
      $type_paragraph = [
        'field_image_text' => 'field_image_text',
        'field_popup' => 'field_popup',
        'field_image_blocks' => 'field_image_blocks'
      ];

      foreach ($datas["entity"] as $fielName => $values) {
        if (is_array($values)) {
          // On recupere la bonne valeur pour le champs revision de paragraph.
          if (!empty($type_paragraph[$fielName])) {
            foreach ($values as $delta => $value) {
              $paragraph = \Drupal\paragraphs\Entity\Paragraph::load($value['target_id']);
              $datas["entity"][$fielName][$delta]['target_revision_id'] = (int) $paragraph->getRevisionId();
            }
          } else
            /**
             * On parcours les champs de l'entité dans le but de remplacer les
             * definitions d'images.
             */
            foreach ($values as $delta => $value) {
              if (!empty($value['value']) && is_string($value['value']))
                $datas["entity"][$fielName][$delta]['value'] = $this->ExtractJSONFileAndRempalceIt($value['value']);
              if (!empty($value['summary']) && is_string($value['summary']))
                $datas["entity"][$fielName][$delta]['summary'] = $this->ExtractJSONFileAndRempalceIt($value['summary']);
            }
        }
      }
      return $datas["entity"];
    }
  }

  protected function ExtractJSONFileAndRempalceIt(string $texte) {
    $matches = [];
    $texte_sans_json = null;
    // Expression régulière pour trouver le JSON dans le texte
    $pattern = '/\[\[\{.*?\}\]\]/';
    // $pattern = '/\[\{(?:[^{}]|(?R))*\}\]/';
    // $pattern = '/\[\[\{(?:[^{}]|(?R))*\}\]\]/';
    if (\Drupal::currentUser()->id() == 1) {
      if (preg_match_all($pattern, $texte, $matches)) {
        if (!empty($matches[0]))
          foreach ($matches[0] as $match) {
            $imgs_string = $this->importFilesAndReplaceIt($match);
            $texte = preg_replace($pattern, $imgs_string, $texte, 1);
          }
      }
    } else
      // Chercher le JSON dans le texte et le retirer
      if (preg_match($pattern, $texte, $matches)) {
        /**
         * Contient le json des fichiers images et autres.
         *
         * @var string $json
         */
        $json = $matches[0];
        $imgs_string = $this->importFilesAndReplaceIt($json);
        // remplacer par du contenu Drupal 10 valide.
        $texte_sans_json = preg_replace($pattern, $imgs_string, $texte);
        return $texte_sans_json;
      }
    return $texte;
  }

  protected function importFilesAndReplaceIt(string $json) {
    $imgs_string = '';
    $datasBase = Json::decode($json);
    foreach ($datasBase as $datas) {
      foreach ($datas as $value) {
        if (!empty($value['fid'])) {
          /**
           *
           * @var \Drupal\file\Entity\File $file
           */
          $file = ManageNodesConfig::CheckAndImportImageFromD7($value['fid']);
          /**
           *
           * @var \Drupal\Core\Url $relative_url
           */
          $relative_url = $this->fileUrlGenerator->generateString($file->getFileUri());
          $alt = $value['fields']['alt'] ?? false;
          if (!$alt) {
            $alt = $value['attributes']['alt'] ?? $file->getFilename();
          }
          $style = $value['attributes']['style'] ?? '';
          $class = $value['attributes']['class'] ?? '';
          $imgs_string .= '<img src="' . $relative_url . '" alt="' . $alt . '" style="' . $style . '" class="' . $class . '"  />';
        } else
          throw new \Exception('Datas in not valid');
      }
    }
    return $imgs_string;
  }
}
