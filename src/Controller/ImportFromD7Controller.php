<?php

declare(strict_types=1);

namespace Drupal\migration_hbk_auto\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for Migration Hbk Auto routes.
 */
final class ImportFromD7Controller extends ControllerBase {

  /**
   * Builds the response.
   */
  public function __invoke(): array {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

}
