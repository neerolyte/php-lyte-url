<?php
namespace Lyte\URL\Tests;
if (!class_exists('PHPUnit_Framework_TestCase')) {
	class_alias('PHPUnit\\Framework\\TestCase','PHPUnit_Framework_TestCase');
}
abstract class TestCase extends \PHPUnit_Framework_TestCase {}