<?php

declare(strict_types=1);

namespace Drupal\migration_hbk_auto\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Migration Hbk Auto settings for this site.
 */
final class MigrationSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'migration_hbk_auto_migration_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['migration_hbk_auto.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['source_site_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Source site url'),
      '#description' => $this->t('the url from witch the configs and contents will be retrieved </br> ex: https://example.com'),
      '#default_value' => $this->config('migration_hbk_auto.settings')->get('source_site_url'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // @todo Validate the form here.
    // Example:
    // @code
    //   if ($form_state->getValue('example') === 'wrong') {
    //     $form_state->setErrorByName(
    //       'message',
    //       $this->t('The value is not correct.'),
    //     );
    //   }
    // @endcode
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('migration_hbk_auto.settings')
      ->set('source_site_url', $form_state->getValue('source_site_url'))
      ->save();
    parent::submitForm($form, $form_state);
  }
}
