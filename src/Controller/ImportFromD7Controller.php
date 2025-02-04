<?php

declare(strict_types=1);

namespace Drupal\migration_hbk_auto\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns responses for Migration Hbk Auto routes.
 */
final class ImportFromD7Controller extends ControllerBase {

  /**
   * Builds the response.
   */
  public function __invoke(): array {
    $build['content'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#value' => 'Chargement ...',
      '#attributes' => [
        'id' => 'migrate-app-build'
      ],
      '#attached' => [
        'library' => [
          'migration_hbk_auto/buildinterface'
        ]
      ]
    ];

    return $build;
  }
  /**
   * Returns the JSON representation of the migration_hbk_auto.settings configuration.
   */
  public function getMigrationSettings(): Response {
    $config = $this->config('migration_hbk_auto.settings');
    $response = new Response();
    if (!$config->get('source_site_url')) {
      return new Response('Missing configurations', Response::HTTP_NO_CONTENT);
    }
    $response->setContent(json_encode($config->getRawData()));
    $response->headers->set('Content-Type', 'application/json');
    return $response;
  }
}
