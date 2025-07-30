<?php

namespace Drupal\Tests\iiif_random_block\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the image selection rules logic from the iiif_random_block.module file.
 *
 * @group iiif_random_block
 */
class IiifSelectionRulesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['iiif_random_block'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Load the module file to make the global helper function available for testing.
    $this->container->get('module_handler')->load('iiif_random_block');
  }

  /**
   * Creates a mock array of canvases for testing purposes.
   *
   * @param int $count
   *   The number of mock canvases to create.
   *
   * @return array
   *   An array of mock canvases.
   */
  private function createMockCanvases(int $count): array {
    $canvases = [];
    for ($i = 0; $i < $count; $i++) {
      // Each canvas only needs a unique identifier for this test.
      $canvases[] = ['@id' => 'canvas_' . ($i + 1)];
    }
    return $canvases;
  }

  /**
   * Tests the various rule conditions and actions.
   */
  public function testSelectionRules(): void {
    // Test Case 1: Exact number match.
    $canvases = $this->createMockCanvases(5);
    // If exactly 5 canvases, select the 3rd.
    $rules = '5 => 3';
    $selected = _iiif_random_block_get_canvas_by_rules($canvases, $rules);
    $this->assertEquals('canvas_3', $selected['@id'], 'Rule "5 => 3" correctly selects the 3rd canvas.');

    // Test Case 2: Range match.
    $canvases = $this->createMockCanvases(4);
    $rules = "1-5 => 2\n6+ => 1";
    $selected = _iiif_random_block_get_canvas_by_rules($canvases, $rules);
    $this->assertEquals('canvas_2', $selected['@id'], 'Rule "1-5 => 2" correctly selects the 2nd canvas.');

    // Test Case 3: Greater than match.
    $canvases = $this->createMockCanvases(12);
    $rules = "1-10 => 1\n11+ => last";
    $selected = _iiif_random_block_get_canvas_by_rules($canvases, $rules);
    $this->assertEquals('canvas_12', $selected['@id'], 'Rule "11+ => last" correctly selects the last canvas.');

    // Test Case 4: Random from a specific range.
    $canvases = $this->createMockCanvases(10);
    $rules = '10 => random(3-7)';
    // Run multiple times to test randomness.
    for ($i = 0; $i < 10; $i++) {
      $selected = _iiif_random_block_get_canvas_by_rules($canvases, $rules);
      $selectedIndex = (int) substr($selected['@id'], strpos($selected['@id'], '_') + 1);
      $this->assertGreaterThanOrEqual(3, $selectedIndex, 'Random selection "random(3-7)" is within the lower bound.');
      $this->assertLessThanOrEqual(7, $selectedIndex, 'Random selection "random(3-7)" is within the upper bound.');
    }

    // Test Case 5: Random excluding the last page.
    $canvases = $this->createMockCanvases(10);
    $rules = '10+ => random(1-last-1)';
    for ($i = 0; $i < 10; $i++) {
      $selected = _iiif_random_block_get_canvas_by_rules($canvases, $rules);
      $selectedIndex = (int) substr($selected['@id'], strpos($selected['@id'], '_') + 1);
      $this->assertLessThan(10, $selectedIndex, 'Random selection with "last-1" correctly excludes the last canvas.');
    }

    // Test Case 6: Random excluding first and last page.
    $canvases = $this->createMockCanvases(10);
    $rules = '10+ => random(2-last-1)';
    for ($i = 0; $i < 10; $i++) {
      $selected = _iiif_random_block_get_canvas_by_rules($canvases, $rules);
      $selectedIndex = (int) substr($selected['@id'], strpos($selected['@id'], '_') + 1);
      $this->assertGreaterThanOrEqual(2, $selectedIndex, 'Random selection "2-last-1" is above the lower bound.');
      $this->assertLessThan(10, $selectedIndex, 'Random selection "2-last-1" is below the upper bound.');
    }

    // Test Case 7: No matching rule, should fall back to random(all).
    $canvases = $this->createMockCanvases(8);
    $rules = "1-5 => 1\n10+ => 1";
    $selected = _iiif_random_block_get_canvas_by_rules($canvases, $rules);
    $this->assertNotNull($selected, 'Fallback to random selection returns a canvas when no rules match.');
    $selectedIndex = (int) substr($selected['@id'], strpos($selected['@id'], '_') + 1);
    $this->assertGreaterThanOrEqual(1, $selectedIndex);
    $this->assertLessThanOrEqual(8, $selectedIndex);

    // Test Case 8: Invalid range in random, should fall back to random(all).
    $canvases = $this->createMockCanvases(5);
    $rules = '5 => random(4-2)';
    $selected = _iiif_random_block_get_canvas_by_rules($canvases, $rules);
    $this->assertNotNull($selected, 'Invalid random range "random(4-2)" falls back to selecting a canvas.');
  }

}
