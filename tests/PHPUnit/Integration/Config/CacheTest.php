<?php
/**
 * Piwik - free/libre analytics platform
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Tests\Integration\Config\Cache;

use PHPUnit_Framework_TestCase;
use Piwik\Config;
use Piwik\Config\Cache;
use Piwik\Config\IniFileChain;
use Piwik\Tests\Integration\Settings\IntegrationTestCase;

/**
 * @group Core
 */
class CacheTest extends IntegrationTestCase
{
    /**
     * @var Cache
     */
    private $cache;

    private $testHost = 'analytics.test.matomo.org';

    public function setUp()
    {
        unset($GLOBALS['ENABLE_CONFIG_PHP_CACHE']);
        $this->setTrustedHosts();
        $_SERVER['HTTP_HOST'] = $this->testHost;
        $this->cache = new Cache();
        $this->cache->doDelete(IniFileChain::CONFIG_CACHE_KEY);
        parent::setUp();
    }

    private function setTrustedHosts()
    {
        Config::setSetting('General', 'trusted_hosts', array($this->testHost, 'foonot.exists'));
    }

    public function tearDown()
    {
        $this->setTrustedHosts();
        $this->cache->doDelete(IniFileChain::CONFIG_CACHE_KEY);
        unset($_SERVER['HTTP_HOST']);
        parent::tearDown();
    }

    public function test_doFetch_noValueSaved_shouldReturnFalse()
    {
        $noValue = $this->cache->doFetch(IniFileChain::CONFIG_CACHE_KEY);
        $this->assertFalse($noValue);
    }

    /**
     * @dataProvider getRandmHosts
     * @expectedException \Exception
     * @expectedExceptionMessage  Unsupported host
     */
    public function test_construct_failsWhenUsingRandomHost($host)
    {
        $_SERVER['HTTP_HOST'] = $host;
        new Cache();
    }

    public function getRandmHosts()
    {
        return [
            ['foo..test'],
            ['foo\test'],
            ['']
        ];
    }

    public function test_doSave_doFetch_savesAndReadsData()
    {
        $value = array('mergedSettings' => 'foobar', 'settingsChain' => array('bar' => 'baz'));
        $this->cache->doSave(IniFileChain::CONFIG_CACHE_KEY, $value, 60);
        $this->assertEquals($value, $this->cache->doFetch(IniFileChain::CONFIG_CACHE_KEY));

        // also works when creating new instance to ensure it's read from file
        $this->cache = new Cache();
        $this->assertEquals($value, $this->cache->doFetch(IniFileChain::CONFIG_CACHE_KEY));
    }

    public function test_doDelete()
    {
        $value = array('mergedSettings' => 'foobar', 'settingsChain' => array('bar' => 'baz'));
        $this->cache->doSave(IniFileChain::CONFIG_CACHE_KEY, $value, 60);

        $this->setTrustedHosts();

        $this->assertEquals($value, $this->cache->doFetch(IniFileChain::CONFIG_CACHE_KEY));

        $this->cache->doDelete(IniFileChain::CONFIG_CACHE_KEY);

        $this->assertFalse($this->cache->doFetch(IniFileChain::CONFIG_CACHE_KEY));

        $cache = new Cache();
        $this->assertFalse($cache->doFetch(IniFileChain::CONFIG_CACHE_KEY));
    }

    public function test_isValidHost()
    {
        $this->assertTrue($this->cache->isValidHost(array('General' => array('trusted_hosts' => array('foo.com', $this->testHost, 'bar.baz')))));
        $this->assertFalse($this->cache->isValidHost(array('General' => array('trusted_hosts' => array('foo.com', 'bar.baz')))));
        $this->assertFalse($this->cache->isValidHost(array('General' => array('trusted_hosts' => array()))));
        $this->assertFalse($this->cache->isValidHost(array()));
    }

}