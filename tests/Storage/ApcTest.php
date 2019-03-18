<?php
namespace Gamegos\NoSql\Tests\Storage;

/* Imports from gamegos/nosql */
use Gamegos\NoSql\Storage\Apc;
use Gamegos\NoSql\Storage\Exception\ApcExtensionException;

/* Imports from PHP core */
use UnexpectedValueException;

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
            if ($this->getName() == 'testIncrementShouldThrowExceptionOnExistingIntegerFail') {
                uopz_unset_return('apcu_inc');
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

    public function testAddShouldThrowExceptionForResource()
    {
        if (version_compare(phpversion('apcu'), '5.1.12', '>')) {
            $apc   = $this->createStorage();
            $key   = 'foo';
            $value = fopen('php://memory', 'r');
            $this->setExpectedException(
                UnexpectedValueException::class,
                'APCu version 5.1.13 and above cannot store resources.'
            );
            $apc->add($key, $value);
        } else {
            $this->markTestSkipped('Test is only for APCu version 5.1.13 and above.');
        }
    }

    public function testSetShouldThrowExceptionForResource()
    {
        if (version_compare(phpversion('apcu'), '5.1.12', '>')) {
            $apc   = $this->createStorage();
            $key   = 'foo';
            $value = fopen('php://memory', 'r');
            $this->setExpectedException(
                UnexpectedValueException::class,
                'APCu version 5.1.13 and above cannot store resources.'
            );
            $apc->set($key, $value);
        } else {
            $this->markTestSkipped('Test is only for APCu version 5.1.13 and above.');
        }
    }

    public function testCasShouldThrowExceptionForResource()
    {
        if (version_compare(phpversion('apcu'), '5.1.12', '>')) {
            $apc   = $this->createStorage();
            $key   = 'foo';
            $value = fopen('php://memory', 'r');
            $token = null;
            $this->setExpectedException(
                UnexpectedValueException::class,
                'APCu version 5.1.13 and above cannot store resources.'
            );
            $apc->cas($token, $key, $value);
        } else {
            $this->markTestSkipped('Test is only for APCu version 5.1.13 and above.');
        }
    }

    public function testIncrementShouldReturnFalseOnInitialFail()
    {
        if (function_exists('uopz_set_return') && function_exists('uopz_unset_return')) {
            $apc = new Apc();
            uopz_set_return('apcu_store', false);
            $this->assertFalse($apc->increment('foo'));
            uopz_unset_return('apcu_store');
        } else {
            $this->markTestSkipped('The uopz extension is not available.');
        }
    }

    public function testIncrementShouldThrowExceptionOnExistingIntegerFail()
    {
        if (function_exists('uopz_set_return') && function_exists('uopz_unset_return')) {
            $val = 100;
            $apc = new Apc();
            $apc->add('foo', $val);
            // Integer value will not be increased.
            uopz_set_return(
                'apcu_inc',
                function ($key, $step = 1, &$success = false) {
                    $success = false;
                    return false;
                },
                true
            );
            $this->setExpectedException(
                UnexpectedValueException::class,
                sprintf('APC could not increment integer value (%d).', $val)
            );
            $apc->increment('foo');
        } else {
            $this->markTestSkipped('The uopz extension is not available.');
        }
    }

    public function testIncrementShouldReturnFalseOnExistingValueFail()
    {
        if (function_exists('uopz_set_return') && function_exists('uopz_unset_return')) {
            $apc = new Apc();
            $apc->add('foo', 100);
            // Existing value will not be increased.
            uopz_set_return(
                'apcu_inc',
                function ($key, $step = 1, &$success = false) {
                    $success = false;
                    return false;
                },
                true
            );
            // Then existing value will not be fetched.
            uopz_set_return(
                'apcu_fetch',
                function ($key, &$success = false) {
                    $success = false;
                    return false;
                },
                true
            );
            $this->assertFalse($apc->increment('foo'));
            uopz_unset_return('apcu_inc');
            uopz_unset_return('apcu_fetch');
        } else {
            $this->markTestSkipped('The uopz extension is not available.');
        }
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
