<?php

namespace Drupal\iiif_random_block\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Render\RendererInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure IIIF Random Block settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The database connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;
  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs a SettingsForm object.
   */
  public function __construct(Connection $database, RendererInterface $renderer) {
    $this->database = $database;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('renderer')
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

    // Currently Displayed Images.
    $form['current_images'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Currently Displayed Images'),
    ];
    try {
      // Add image_url to the query.
      $query = $this->database->select('iiif_display_images', 'd')
        ->fields('d', ['label', 'manifest_url', 'related_url', 'image_url']);
      $results = $query->execute()->fetchAll();

      if (empty($results)) {
        $form['current_images']['info'] = [
          '#markup' => $this->t('No images are currently selected. Save this form to generate the initial set.'),
        ];
      }
      else {
        $items = [];
        foreach ($results as $item) {
          // Build the links with slashes as separators.
          $items[] = [
            '#markup' => $this->t('@label (<a href="@image_url" target="_blank">Image</a> / <a href="@manifest_url" target="_blank">Manifest</a> / <a href="@related_url" target="_blank">Source Page</a>)', [
              '@label' => $item->label,
              '@image_url' => $item->image_url,
              '@manifest_url' => $item->manifest_url,
              '@related_url' => $item->related_url,
            ]),
          ];
        }
        $form['current_images']['list'] = [
          '#theme' => 'item_list',
          '#items' => $items,
          '#title' => $this->t('The following images are currently being displayed in the block:'),
        ];
      }
    }
    catch (\Exception $e) {
      $this->messenger()->addError($this->t('Could not load the list of current images.'));
    }

    // Source Settings...
    $form['source_settings'] = ['#type' => 'fieldset', '#title' => $this->t('Source Information')];
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

    // Display Settings...
    $form['display_settings'] = ['#type' => 'fieldset', '#title' => $this->t('Display Settings')];
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

    // Image Selection Rules...
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

    // Manifest URL list...
    $query = $this->database->select('iiif_manifest_urls', 'm')->fields('m', ['url']);
    $results = $query->execute()->fetchCol();
    $urls = implode("\n", $results);
    $form['manifest_urls_fieldset'] = ['#type' => 'fieldset', '#title' => $this->t('Manifest URLs')];
    $form['manifest_urls_fieldset']['manifest_urls'] = [
      '#type' => 'textarea',
      '#title' => $this->t('IIIF Manifest URLs'),
      '#default_value' => $urls,
      '#rows' => 10,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('iiif_random_block.settings');
    $config
      ->set('source_link_text', $form_state->getValue('source_link_text'))
      ->set('source_link_url', $form_state->getValue('source_link_url'))
      ->set('number_of_images', $form_state->getValue('number_of_images'))
      ->set('carousel_duration', $form_state->getValue('carousel_duration'))
      ->set('image_size', $form_state->getValue('image_size'))
      ->set('selection_rules', $form_state->getValue('selection_rules'))
      ->set('cron_interval', $form_state->getValue('cron_interval'))
      ->save();

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
      $this->messenger()->addStatus($this->t('The manifest URL list has been updated.'));
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      $this->messenger()->addError($this->t('An error occurred while updating the URL list.'));
    }

    // Immediately update the displayed images by passing the values directly.
    $update_result = static::updateDisplayedImages(
        (int) $form_state->getValue('number_of_images'),
        (int) $form_state->getValue('image_size'),
        (string) $form_state->getValue('selection_rules')
    );

    if ($update_result) {
      $this->messenger()->addStatus($this->t('The displayed images have been updated immediately with @count items.', ['@count' => $update_result]));
    }
    else {
      $this->messenger()->addWarning($this->t('Could not update the displayed images. Please check the logs.'));
    }

    parent::submitForm($form, $form_state);
  }

  /**
   * Selects and updates the images for the block display.
   *
   * @param int $number_of_images
   *   The number of images to select.
   * @param int $image_size
   *   The max size for the image.
   * @param string $selection_rules
   *   The rule string for selecting a canvas.
   *
   * @return int|false
   *   The number of items saved, or FALSE on failure.
   */
  public static function updateDisplayedImages(int $number_of_images, int $image_size, string $selection_rules) {
    $database = \Drupal::database();
    $client_factory = \Drupal::service('http_client_factory');
    $logger = \Drupal::logger('iiif_random_block');

    $query = $database->select('iiif_manifest_urls', 'm')->fields('m', ['url'])->orderRandom()->range(0, $number_of_images);
    $manifest_urls = $query->execute()->fetchCol();

    if (count($manifest_urls) < $number_of_images) {
      $logger->warning('Could not retrieve @num random manifests.', ['@num' => $number_of_images]);
      return FALSE;
    }

    $display_data = [];
    $client = $client_factory->fromOptions(['timeout' => 20]);

    foreach ($manifest_urls as $manifest_url) {
      try {
        $response = $client->get($manifest_url);
        $manifest = json_decode((string) $response->getBody(), TRUE);
        if (json_last_error() !== JSON_ERROR_NONE) {
          continue;
        }

        $context = $manifest['@context'] ?? '';
        $is_v3 = is_string($context) && strpos($context, 'presentation/3') !== FALSE;
        $canvases = $is_v3 ? ($manifest['items'] ?? []) : ($manifest['sequences'][0]['canvases'] ?? []);

        $selected_canvas = static::getCanvasByRules($canvases, $selection_rules);
        if (!$selected_canvas) {
          continue;
        }

        $image_service = NULL;
        if ($is_v3) {
          if (isset($selected_canvas['items'][0]['items'][0]['body']['service'])) {
            foreach ($selected_canvas['items'][0]['items'][0]['body']['service'] as $service) {
              if (isset($service['type']) && $service['type'] === 'ImageService3') {
                $image_service = $service['id'] ?? $service['@id'] ?? NULL;
                break;
              }
            }
          }
        }
        else {
          $image_service = $selected_canvas['images'][0]['resource']['service']['@id'] ?? NULL;
        }
        if (!$image_service) {
          continue;
        }

        $related_url = $manifest['related']['@id'] ?? $manifest['homepage'][0]['id'] ?? '#';

        // ** FIX **: Updated label extraction logic for v3 manifests.
        $label = 'Untitled';
        if (isset($manifest['label'])) {
          if (is_string($manifest['label'])) {
            $label = $manifest['label'];
          }
          elseif (is_array($manifest['label'])) {
            // Handles v3 language maps like {"en": ["Title"], "ja": ["..."]}.
            // It just takes the first available language's first value.
            $first_lang_values = reset($manifest['label']);
            if (is_array($first_lang_values) && isset($first_lang_values[0])) {
              $label = $first_lang_values[0];
            }
            // Fallback for other structures like {"@value": "Title"}.
            elseif (isset($manifest['label']['@value'])) {
              $label = $manifest['label']['@value'];
            }
          }
        }

        $display_data[] = [
          'image_url' => rtrim($image_service, '/') . "/full/$image_size,/0/default.jpg",
          'manifest_url' => $manifest_url,
          'related_url' => $related_url,
          'label' => mb_substr($label, 0, 512),
        ];
      }
      catch (\Exception $e) {
        $logger->error(
          'Failed to process manifest @url: @message',
          [
            '@url' => $manifest_url,
            '@message' => $e->getMessage(),
          ]
        );
      }
    }

    if (!empty($display_data)) {
      $transaction = $database->startTransaction();
      try {
        $database->truncate('iiif_display_images')->execute();
        $query = $database->insert('iiif_display_images')->fields(['image_url', 'manifest_url', 'related_url', 'label']);
        foreach ($display_data as $item) {
          $query->values($item);
        }
        $query->execute();
        return count($display_data);
      }
      catch (\Exception $e) {
        $transaction->rollBack();
        $logger->error('Failed to save display images: @message', ['@message' => $e->getMessage()]);
      }
    }
    return FALSE;
  }

  /**
   * Helper function to select a canvas based on defined rules.
   */
  private static function getCanvasByRules(array $canvases, string $rules_string): ?array {
    $canvas_count = count($canvases);
    if ($canvas_count === 0) {
      return NULL;
    }
    $rules = preg_split('/\\r\\n|\\r|\\n/', $rules_string, -1, PREG_SPLIT_NO_EMPTY);
    $selected_index = -1;
    $rule_condition_was_met = FALSE;
    foreach ($rules as $rule) {
      if (strpos($rule, '=>') === FALSE) {
        continue;
      }
      [$condition, $action] = array_map('trim', explode('=>', $rule, 2));
      $condition_met = FALSE;
      if (strpos($condition, '+') !== FALSE) {
        if ($canvas_count >= (int) $condition) {
          $condition_met = TRUE;
        }
      }
      elseif (strpos($condition, '-') !== FALSE) {
        [$min, $max] = array_map('intval', explode('-', $condition));
        if ($canvas_count >= $min && $canvas_count <= $max) {
          $condition_met = TRUE;
        }
      }
      else {
        if ($canvas_count == (int) $condition) {
          $condition_met = TRUE;
        }
      }
      if ($condition_met) {
        $rule_condition_was_met = TRUE;
        $action = strtolower($action);
        if ($action === 'last') {
          $selected_index = $canvas_count - 1;
        }
        elseif (strpos($action, 'random') !== FALSE) {
          preg_match('/random\((\d+)-(\d+|last)(-(\d+))?\)/', $action, $matches);
          $rand_min = 0;
          $rand_max = $canvas_count - 1;
          if (!empty($matches)) {
            $rand_min = (int) $matches[1] - 1;
            $end_val = $matches[2];
            $offset = isset($matches[4]) ? (int) $matches[4] : 0;
            $rand_max = ($end_val === 'last') ? $canvas_count - 1 - $offset : (int) $end_val - 1;
          }
          $rand_min = max(0, $rand_min);
          $rand_max = min($canvas_count - 1, $rand_max);
          if ($rand_min <= $rand_max) {
            $selected_index = mt_rand($rand_min, $rand_max);
          }
        }
        else {
          $selected_index = (int) $action - 1;
        }
        if ($selected_index >= 0) {
          break;
        }
      }
    }
    if (!$rule_condition_was_met) {
      $selected_index = mt_rand(0, $canvas_count - 1);
    }
    if ($selected_index < 0 || $selected_index >= $canvas_count) {
      return NULL;
    }
    return $canvases[$selected_index] ?? NULL;
  }

}
