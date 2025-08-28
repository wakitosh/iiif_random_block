<?php

namespace Drupal\Tests\iiif_random_block\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\iiif_random_block\Form\SettingsForm;

/**
 * Kernel test for image selection rules using SettingsForm::getCanvasByRules().
 *
 * @group iiif_random_block
 */
class IiifSelectionRulesTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'iiif_random_block'];

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
      $canvases[] = ['@id' => 'canvas_' . ($i + 1)];
    }
    return $canvases;
  }

  /**
   * Helper to invoke the private static selector via reflection.
   */
  private function invokeSelector(array $canvases, string $rules): ?array {
    $ref = new \ReflectionMethod(SettingsForm::class, 'getCanvasByRules');
    $ref->setAccessible(TRUE);
    return $ref->invoke(NULL, $canvases, $rules);
  }

  /**
   * Tests the various rule conditions and actions.
   */
  public function testSelectionRules(): void {
    // 1) Exact number match: 5 => 3 selects 3rd.
    $canvases = $this->createMockCanvases(5);
    $selected = $this->invokeSelector($canvases, '5 => 3');
    $this->assertEquals('canvas_3', $selected['@id']);

    // 2) Range match: 1-5 => 2 when count is 4.
    $canvases = $this->createMockCanvases(4);
    $selected = $this->invokeSelector($canvases, "1-5 => 2\n6+ => 1");
    $this->assertEquals('canvas_2', $selected['@id']);

    // 3) Greater-than: 11+ => last when count is 12.
    $canvases = $this->createMockCanvases(12);
    $selected = $this->invokeSelector($canvases, "1-10 => 1\n11+ => last");
    $this->assertEquals('canvas_12', $selected['@id']);

    // 4) Random from a specific range.
    $canvases = $this->createMockCanvases(10);
    for ($i = 0; $i < 10; $i++) {
      $selected = $this->invokeSelector($canvases, '10 => random(3-7)');
      $idx = (int) substr($selected['@id'], strpos($selected['@id'], '_') + 1);
      $this->assertGreaterThanOrEqual(3, $idx);
      $this->assertLessThanOrEqual(7, $idx);
    }

    // 5) Random excluding the last.
    $canvases = $this->createMockCanvases(10);
    for ($i = 0; $i < 10; $i++) {
      $selected = $this->invokeSelector($canvases, '10+ => random(1-last-1)');
      $idx = (int) substr($selected['@id'], strpos($selected['@id'], '_') + 1);
      $this->assertLessThan(10, $idx);
    }

    // 6) Random excluding first and last.
    $canvases = $this->createMockCanvases(10);
    for ($i = 0; $i < 10; $i++) {
      $selected = $this->invokeSelector($canvases, '10+ => random(2-last-1)');
      $idx = (int) substr($selected['@id'], strpos($selected['@id'], '_') + 1);
      $this->assertGreaterThanOrEqual(2, $idx);
      $this->assertLessThan(10, $idx);
    }

    // 7) No matching rule -> global random fallback.
    $canvases = $this->createMockCanvases(8);
    $selected = $this->invokeSelector($canvases, "1-5 => 1\n10+ => 1");
    $this->assertNotNull($selected);
    $idx = (int) substr($selected['@id'], strpos($selected['@id'], '_') + 1);
    $this->assertGreaterThanOrEqual(1, $idx);
    $this->assertLessThanOrEqual(8, $idx);

    // 8) Invalid random range -> fallback to global random.
    $canvases = $this->createMockCanvases(5);
    $selected = $this->invokeSelector($canvases, '5 => random(4-2)');
    $this->assertNotNull($selected);
  }

}
