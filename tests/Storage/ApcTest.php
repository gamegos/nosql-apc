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
        apc_clear_cache('user');
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        apc_clear_cache('user');
    }

    /**
     * {@inheritdoc}
     * @return \Gamegos\NoSql\Storage\Apc
     */
    public function createStorage()
    {
        return new Apc();
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
}
