<?php
namespace Csgt\Crud\Tests;

/**
 * The views carry two halves of one mechanism: the script block sets a flag as
 * its last statement, and a listener in the content block reports a problem
 * when that flag is missing once the page has loaded.
 *
 * They have to travel together. A view that sets the flag without the listener
 * loses the warning; a view with the listener but no flag shows a red banner on
 * every single page load.
 */
class ViewGuardTest extends TestCase
{
    private function views()
    {
        $directory = __DIR__ . '/../src/resources/views';

        return [
            'index' => file_get_contents($directory . '/index.blade.php'),
            'edit'  => file_get_contents($directory . '/edit.blade.php'),
        ];
    }

    public function testEveryViewPairsTheBootFlagWithItsBackstop()
    {
        foreach ($this->views() as $name => $source) {
            $sets    = substr_count($source, 'window.csgtCrudBooted = true');
            $reads   = substr_count($source, 'if (window.csgtCrudBooted)');

            $this->assertSame(1, $sets, "$name.blade.php has to set the boot flag exactly once");
            $this->assertSame(1, $reads, "$name.blade.php has to check the boot flag exactly once");
        }
    }

    public function testTheFlagIsSetAfterTheDependencyCheck()
    {
        // Setting it before the check would mark the view as booted even when a
        // missing library aborted the block.
        foreach ($this->views() as $name => $source) {
            $check = strpos($source, 'csgtCrudRequire(');
            $flag  = strpos($source, 'window.csgtCrudBooted = true');

            $this->assertNotFalse($check, "$name.blade.php has to check its dependencies");
            $this->assertGreaterThan($check, $flag, "$name.blade.php sets the boot flag before checking dependencies");
        }
    }
}
