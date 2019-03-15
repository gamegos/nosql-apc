<?php
namespace Gamegos\NoSql\Storage;

/* Imports from PHP core */
use UnexpectedValueException;

/* Imports from gamegos/nosql */
use Gamegos\NoSql\Storage\Exception\ApcExtensionException;

/**
 * NoSQL Storage Implementation for APC
 * @author Safak Ozpinar <safak@gamegos.com>
 */
class Apc extends AbstractStorage
{
    /**
     * Key prefix for current instance.
     * @var string
     */
    protected $prefix = '';

    /**
     * APCu extension version
     * @var string
     */
    private $apcuVersion = '';

    /**
     * Resources can be stored or not.
     * @var bool
     */
    private $storeResources = true;

    /**
     * Construct
     * @throws \Gamegos\NoSql\Storage\Exception\ApcExtensionException If APC extension cannot be used
     */
    public function __construct()
    {
        if (!extension_loaded('apcu')) {
            throw new ApcExtensionException('APCu extension not loaded!');
        }
        if (!ini_get('apc.enabled') || PHP_SAPI == 'cli' && !ini_get('apc.enable_cli')) {
            throw new ApcExtensionException('APC disabled by PHP runtime configuration!');
        }
        $this->apcuVersion    = phpversion('apcu');
        $this->storeResources = version_compare($this->apcuVersion, '5.1.13', '<');
    }

    /**
     * Get key prefix for current instance.
     * @return string
     */
    public function getPrefix()
    {
        return $this->prefix;
    }

    /**
     * Get APCu extension version.
     * @return string
     */
    public function getApcuVersion()
    {
        return $this->apcuVersion;
    }

    /**
     * Set key prefix for current instance.
     * @param string $prefix
     */
    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }

    /**
     * Format a key to store.
     * @param  string $key
     * @return string
     */
    public function formatKey($key)
    {
        return $this->prefix . $key;
    }

    /**
     * {@inheritdoc}
     */
    protected function hasInternal($key)
    {
        return apcu_exists($this->formatKey($key));
    }

    /**
     * {@inheritdoc}
     */
    protected function getInternal($key, & $casToken = null)
    {
        $success = false;
        $value   = apcu_fetch($this->formatKey($key), $success);
        if ($success) {
            if (func_num_args() > 1) {
                $casToken = $this->createCasToken($value);
            }
            return $value;
        }
        return null;
    }

    /**
     * {@inheritdoc}
     */
    protected function getMultiInternal(array $keys, array & $casTokens = null)
    {
        $realKeys = array_map([$this, 'formatKey'], $keys);
        $values   = apcu_fetch($realKeys);
        $result   = [];

        $prefixLength = strlen($this->getPrefix());
        foreach ($values as $realKey => & $value) {
            $key          = substr($realKey, $prefixLength);
            $result[$key] = $value;
        }

        if (func_num_args() > 1) {
            $casTokens = [];
            foreach ($result as $key => $value) {
                $casTokens[$key] = $this->createCasToken($value);
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     * @throws \UnexpectedValueException If the value is resource and APCu cannot store resources.
     */
    protected function addInternal($key, $value, $expiry = 0)
    {
        if (is_resource($value) && !$this->storeResources) {
            throw new UnexpectedValueException('APCu version 5.1.13 and above cannot store resources.');
        }
        return apcu_add($this->formatKey($key), $value, $expiry);
    }

    /**
     * {@inheritdoc}
     * @throws \UnexpectedValueException If the value is resource and APCu cannot store resources.
     */
    protected function setInternal($key, $value, $expiry = 0, $casToken = null)
    {
        if (is_resource($value) && !$this->storeResources) {
            throw new UnexpectedValueException('APCu version 5.1.13 and above cannot store resources.');
        }
        if (func_num_args() > 3) {
            return $this->casInternal($casToken, $key, $value, $expiry);
        }
        return apcu_store($this->formatKey($key), $value, $expiry);
    }

    /**
     * {@inheritdoc}
     * @throws \UnexpectedValueException If the value is resource and APCu cannot store resources.
     */
    protected function casInternal($casToken, $key, $value, $expiry = 0)
    {
        if (is_resource($value) && !$this->storeResources) {
            throw new UnexpectedValueException('APCu version 5.1.13 and above cannot store resources.');
        }

        $casValue = $this->decodeCasToken($casToken);
        $realKey  = $this->formatKey($key);

        if (apcu_fetch($realKey) === $casValue) {
            return apcu_store($realKey, $value, $expiry);
        }
        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function deleteInternal($key)
    {
        return apcu_delete($this->formatKey($key));
    }

    /**
     * {@inheritdoc}
     * @throws \UnexpectedValueException If existing value is not string
     */
    protected function appendInternal($key, $value, $expiry = 0)
    {
        $realKey = $this->formatKey($key);
        if (apcu_exists($realKey)) {
            $oldValue = apcu_fetch($realKey);
            if (!is_string($oldValue)) {
                throw new UnexpectedValueException(sprintf(
                    'Method append() requires existing value to be string, %s found.',
                    gettype($oldValue)
                ));
            }
            $value = $oldValue . $value;
        }
        return apcu_store($realKey, $value, $expiry);
    }

    /**
     * {@inheritdoc}
     * @throws \UnexpectedValueException If existing value is not integer
     */
    protected function incrementInternal($key, $offset = 1, $initial = 0, $expiry = 0)
    {
        $realKey = $this->formatKey($key);
        if (!apcu_exists($realKey) && apcu_store($realKey, $initial, $expiry)) {
            return $initial;
        }

        $success = false;
        $value   = apcu_inc($realKey, $offset, $success);
        if ($success) {
            return $value;
        }

        $oldValue = apcu_fetch($realKey);
        if (!is_int($oldValue)) {
            throw new UnexpectedValueException(sprintf(
                'Method increment() requires existing value to be integer, %s found.',
                gettype($oldValue)
            ));
        }

        return false;
    }

    /**
     * Create a CAS (check-and-set) token for a value.
     * @param  mixed $value
     * @return string
     */
    protected function createCasToken($value)
    {
        return serialize($value);
    }

    /**
     * Decode a CAS token created by {@link APC::createCasToken()}.
     * @param  string $casToken
     * @return mixed
     */
    protected function decodeCasToken($casToken)
    {
        return @ unserialize($casToken);
    }
}
