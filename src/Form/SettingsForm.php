<?php

namespace Drupal\iiif_random_block\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure IIIF Random Block settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  protected $database;

  /**
   * Constructs a SettingsForm object.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'iiif_random_block_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['iiif_random_block.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('iiif_random_block.settings');

    // Source Settings.
    $form['source_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Source Information'),
    ];
    $form['source_settings']['source_link_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Source Name'),
      '#description' => $this->t('Leave blank to hide the source information.'),
      '#default_value' => $config->get('source_link_text'),
    ];
    $form['source_settings']['source_link_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Source URL'),
      '#default_value' => $config->get('source_link_url'),
    ];

    // Display Settings.
    $form['display_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Display Settings'),
    ];
    $form['display_settings']['number_of_images'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of images to display'),
      '#default_value' => $config->get('number_of_images') ?: 5,
    ];
    $form['display_settings']['carousel_duration'] = [
      '#type' => 'number',
      '#title' => $this->t('Carousel duration'),
      '#default_value' => $config->get('carousel_duration') ?: 10,
      '#field_suffix' => $this->t('seconds'),
    ];
    $form['display_settings']['image_size'] = [
      '#type' => 'number',
      '#title' => $this->t('Image size (max pixels)'),
      '#default_value' => $config->get('image_size') ?: 800,
      '#field_suffix' => $this->t('px'),
    ];

    // Image Selection Rules.
    $default_rules = "1 => 1\n2 => 2\n3+ => random(2-last-1)";
    $form['selection_rules_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Image Selection Rules'),
      '#description' => $this->t('Define rules for selecting an image based on the total number of canvases in a manifest. The first matching rule from the top will be used.'),
    ];
    $form['selection_rules_settings']['selection_rules'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Rules'),
      '#default_value' => $config->get('selection_rules') ?: $default_rules,
      '#rows' => 10,
      '#description' => $this->t('
            <p>Enter one rule per line in the format: <code>Condition => Action</code></p>
            <strong>Conditions:</strong>
            <ul>
                <li><code>5</code>: Exactly 5 canvases.</li>
                <li><code>1-4</code>: Between 1 and 4 canvases (inclusive).</li>
                <li><code>10+</code>: 10 or more canvases.</li>
            </ul>
            <strong>Actions:</strong>
            <ul>
                <li><code>3</code>: Select the 3rd canvas.</li>
                <li><code>last</code>: Select the very last canvas.</li>
                <li><code>random</code>: Select a random canvas from all available.</li>
                <li><code>random(2-last)</code>: Select a random canvas from the 2nd to the last.</li>
                <li><code>random(1-last-1)</code>: Select a random canvas from the 1st to the second to last (excludes the last page).</li>
            </ul>
        '),
    ];

    // Cron Settings.
    $form['cron_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Update Frequency'),
    ];
    $form['cron_settings']['cron_interval'] = [
      '#type' => 'number',
      '#title' => $this->t('Update interval'),
      '#description' => $this->t('The interval for updating the list of displayed images. Common values: 1 hour = 3600, 12 hours = 43200, 24 hours = 86400.'),
      '#default_value' => $config->get('cron_interval') ?: 86400,
    ];

    // Manifest URL list.
    $query = $this->database->select('iiif_manifest_urls', 'm')->fields('m', ['url']);
    $results = $query->execute()->fetchCol();
    $urls = implode("\n", $results);
    $form['manifest_urls_fieldset'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Manifest URLs'),
    ];
    $form['manifest_urls_fieldset']['manifest_urls'] = [
      '#type' => 'textarea',
      '#title' => $this->t('IIIF Manifest URLs'),
      '#default_value' => $urls,
      '#rows' => 25,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Save settings.
    $this->config('iiif_random_block.settings')
      ->set('source_link_text', $form_state->getValue('source_link_text'))
      ->set('source_link_url', $form_state->getValue('source_link_url'))
      ->set('number_of_images', $form_state->getValue('number_of_images'))
      ->set('carousel_duration', $form_state->getValue('carousel_duration'))
      ->set('image_size', $form_state->getValue('image_size'))
      ->set('selection_rules', $form_state->getValue('selection_rules'))
      ->set('cron_interval', $form_state->getValue('cron_interval'))
      ->save();

    // Save manifest URLs.
    $urls_text = $form_state->getValue('manifest_urls');
    $urls = preg_split('/\\r\\n|\\r|\\n/', $urls_text, -1, PREG_SPLIT_NO_EMPTY);
    $urls = array_unique(array_filter($urls, fn($url) => filter_var(trim($url), FILTER_VALIDATE_URL)));
    $transaction = $this->database->startTransaction();
    try {
      $this->database->truncate('iiif_manifest_urls')->execute();
      if (!empty($urls)) {
        $query = $this->database->insert('iiif_manifest_urls')->fields(['url']);
        foreach ($urls as $url) {
          $query->values(['url' => trim($url)]);
        }
        $query->execute();
      }
      $this->messenger()->addStatus($this->t('The manifest URL list has been updated. @count URLs were saved.', ['@count' => count($urls)]));
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      $this->messenger()->addError($this->t('An error occurred while updating the URL list.'));
      \Drupal::logger('iiif_random_block')->error($e->getMessage());
    }

    parent::submitForm($form, $form_state);
  }

}
