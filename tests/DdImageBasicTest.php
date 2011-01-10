<?php

require_once('PHPUnit/Framework.php');

define('PACKAGE_LIB', dirname(dirname(__FILE__)) . '/lib/');
$includePath = explode(PATH_SEPARATOR, get_include_path());
array_unshift($includePath, PACKAGE_LIB);
set_include_path(implode(PATH_SEPARATOR, $includePath));

class DdCoreBasicTest extends PHPUnit_Framework_TestCase {

    /**
     * Simple test to see if test starts up.
     */
    public function testStartup() {
    }

}
?>
