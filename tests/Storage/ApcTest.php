<?php
namespace Gamegos\NoSql\Tests\Storage;

/* Import from gamegos/nosql */
use Gamegos\NoSql\Storage\Apc;
use Gamegos\NoSql\Storage\Exception\ApcExtensionException;

/**
 * Test Class for Storage\Apc
 * @author Safak Ozpinar <safak@gamegos.com>
 */
class ApcTest extends AbstractCommonStorageTest
{
    /**
     * Catched extension exception before running tests.
     * @var ApcExtensionException|null
     */
    private static $extensionException;

    /**
     * {@inheritdoc}
     */
    public static function setUpBeforeClass()
    {
        try {
            new Apc();
        } catch (ApcExtensionException $e) {
            self::$extensionException = $e;
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        if (self::$extensionException) {
            $this->fail(self::$extensionException->getMessage());
        }
        apcu_clear_cache();
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        if (function_exists('uopz_unset_return')) {
            if ($this->getName() == 'testApcuNotLoaded') {
                uopz_unset_return('extension_loaded');
            }
            if ($this->getName() == 'testApcDisabled' || $this->getName() == 'testApcCliDisabled') {
                uopz_unset_return('ini_get');
            }
        }
        apcu_clear_cache();
    }

    /**
     * {@inheritdoc}
     * @return \Gamegos\NoSql\Storage\Apc
     */
    public function createStorage()
    {
        return new Apc();
    }

    public function testApcuNotLoaded()
    {
        if (function_exists('uopz_set_return')) {
            uopz_set_return(
                'extension_loaded',
                function ($extName) {
                    if ($extName == 'apcu') {
                        return false;
                    }
                    return extension_loaded($extName);
                },
                true
            );
            $this->setExpectedException(ApcExtensionException::class, 'APCu extension not loaded!');
            new Apc();
        } else {
            $this->markTestSkipped('The uopz extension is not available.');
        }
    }

    public function testApcDisabled()
    {
        if (function_exists('uopz_set_return')) {
            uopz_set_return(
                'ini_get',
                function ($key) {
                    if ($key == 'apc.enabled') {
                        return false;
                    }
                    return ini_get($key);
                },
                true
            );
            $this->setExpectedException(ApcExtensionException::class, 'APC disabled by PHP runtime configuration!');
            new Apc();
        } else {
            $this->markTestSkipped('The uopz extension is not available.');
        }
    }

    public function testApcCliDisabled()
    {
        if (function_exists('uopz_set_return')) {
            uopz_set_return(
                'ini_get',
                function ($key) {
                    if ($key == 'apc.enable_cli') {
                        return false;
                    }
                    return ini_get($key);
                },
                true
            );
            $this->setExpectedException(ApcExtensionException::class, 'APC CLI disabled by PHP runtime configuration!');
            new Apc();
        } else {
            $this->markTestSkipped('The uopz extension is not available.');
        }
    }

    public function testSetPrefixAndFormatKey()
    {
        $apc    = $this->createStorage();
        $key    = 'foo';
        $prefix = 'prefix';

        $this->assertEquals($key, $apc->formatKey($key));
        $apc->setPrefix($prefix);
        $this->assertEquals($prefix . $key, $apc->formatKey($key));
    }

    public function testGetApcuVersion()
    {
        $apc = $this->createStorage();
        $this->assertEquals(phpversion('apcu'), $apc->getApcuVersion());
    }

    /**
     * {@inheritdoc}
     */
    public function nonStringProvider()
    {
        $data = parent::nonStringProvider();
        if (version_compare(phpversion('apcu'), '5.1.13', '<')) {
            return $data;
        }
        if (array_key_exists('resource', $data)) {
            unset($data['resource']);
        }
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function nonIntegerProvider()
    {
        $data = parent::nonIntegerProvider();
        if (version_compare(phpversion('apcu'), '5.1.13', '<')) {
            return $data;
        }
        if (array_key_exists('resource', $data)) {
            unset($data['resource']);
        }
        return $data;
    }
}
