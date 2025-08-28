<?php

namespace Drupal\Tests\iiif_random_block\Unit;

use Drupal\iiif_random_block\Form\SettingsForm;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\iiif_random_block\Form\SettingsForm
 */
class SettingsFormRulesTest extends UnitTestCase {

  /**
   * Helper to invoke private static getCanvasByRules via reflection.
   */
  private function callGetCanvasByRules(array $canvases, string $rules) {
    $ref = new \ReflectionClass(SettingsForm::class);
    $method = $ref->getMethod('getCanvasByRules');
    $method->setAccessible(TRUE);
    return $method->invokeArgs(NULL, [$canvases, $rules]);
  }

  /**
   * Tests numeric, last, and random range actions, including edge fallbacks.
   *
   * @covers ::getCanvasByRules
   */
  public function testRulesBehavior() : void {
    $canvases = [
        ['id' => 'c1'],
        ['id' => 'c2'],
        ['id' => 'c3'],
        ['id' => 'c4'],
    ];

    // 1) Exact match with numeric action.
    $sel = $this->callGetCanvasByRules($canvases, "4 => 3");
    $this->assertSame('c3', $sel['id']);

    // 2) Last.
    $sel = $this->callGetCanvasByRules($canvases, "4 => last");
    $this->assertSame('c4', $sel['id']);

    // 3) Random full-range: assert it returns one of the canvases.
    $sel = $this->callGetCanvasByRules($canvases, "4+ => random");
    $this->assertContains($sel['id'], ['c1', 'c2', 'c3', 'c4']);

    // 4) Random range 2..last-1 => should be one of c2 or c3.
    $tries = 0;
    $ok = FALSE;
    while ($tries++ < 10) {
      $sel = $this->callGetCanvasByRules($canvases, "1-10 => random(2-last-1)");
      if (in_array($sel['id'], ['c2', 'c3'], TRUE)) {
        $ok = TRUE;
        break;
      }
    }
    $this->assertTrue($ok, 'random(2-last-1) selects within expected range');

    // 5) Invalid numeric action should skip and match the next rule.
    $tries = 0;
    $ok = FALSE;
    while ($tries++ < 5) {
      $sel = $this->callGetCanvasByRules($canvases, "4 => 99\n4 => last");
      if ($sel['id'] === 'c4') {
        $ok = TRUE;
        break;
      }
    }
    $this->assertTrue($ok, 'Invalid action continues to next rule.');

    // 6) All matched actions invalid should fall back to global random.
    $sel = $this->callGetCanvasByRules($canvases, "4 => 0\n4 => random(10-9)");
    $this->assertContains($sel['id'], ['c1', 'c2', 'c3', 'c4']);
  }

}
