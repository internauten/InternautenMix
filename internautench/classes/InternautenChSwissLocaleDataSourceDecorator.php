<?php

use PrestaShop\PrestaShop\Core\Localization\CLDR\LocaleData;
use PrestaShop\PrestaShop\Core\Localization\CLDR\LocaleDataLayerInterface as CldrLocaleDataLayerInterface;
use PrestaShop\PrestaShop\Core\Localization\CLDR\NumberSymbolsData;

class InternautenChSwissLocaleDataSourceDecorator implements CldrLocaleDataLayerInterface
{
    private const CONFIG_ENABLED = 'INTERNAUTENCH_ENABLED';
    private const CONFIG_LOCALES = 'INTERNAUTENCH_LOCALES';
    private const CONFIG_DECIMAL_SEPARATOR = 'INTERNAUTENCH_DECIMAL_SEPARATOR';
    private const CONFIG_GROUP_SEPARATOR = 'INTERNAUTENCH_GROUP_SEPARATOR';

    /**
     * @var CldrLocaleDataLayerInterface
     */
    private $inner;

    public function __construct(CldrLocaleDataLayerInterface $inner)
    {
        $this->inner = $inner;
    }

    /**
     * @param string $localeCode
     *
     * @return LocaleData|null
     */
    public function read($localeCode)
    {
        $localeData = $this->inner->read($localeCode);

        if (
            !$localeData instanceof LocaleData
            || !$this->isEnabled()
            || !$this->isConfiguredLocale($localeCode)
        ) {
            return $localeData;
        }

        $numberSymbols = $localeData->getNumberSymbols();
        if (!is_array($numberSymbols)) {
            return $localeData;
        }

        foreach ($numberSymbols as $symbolData) {
            if (!$symbolData instanceof NumberSymbolsData) {
                continue;
            }

            $symbolData->setDecimal($this->getDecimalSeparator());
            $symbolData->setGroup($this->getGroupSeparator());
            $symbolData->setCurrencyDecimal($this->getDecimalSeparator());
            $symbolData->setCurrencyGroup($this->getGroupSeparator());
        }

        $localeData->setMinimumGroupingDigits(1);

        return $localeData;
    }

    /**
     * @param string $localeCode
     * @param LocaleData $localeData
     *
     * @return LocaleData
     */
    public function write($localeCode, $localeData)
    {
        return $this->inner->write($localeCode, $localeData);
    }

    /**
     * @param CldrLocaleDataLayerInterface $lowerLayer
     *
     * @return self
     */
    public function setLowerLayer(CldrLocaleDataLayerInterface $lowerLayer)
    {
        $this->inner->setLowerLayer($lowerLayer);

        return $this;
    }

    /**
     * @param string|null $localeCode
     *
     * @return bool
     */
    private function isEnabled()
    {
        return (bool) Configuration::get(self::CONFIG_ENABLED, 1);
    }

    /**
     * @param string|null $localeCode
     *
     * @return bool
     */
    private function isConfiguredLocale($localeCode)
    {
        $normalizedLocale = $this->normalizeLocale($localeCode);
        if (null === $normalizedLocale) {
            return false;
        }

        $configuredLocales = $this->getConfiguredLocales();

        return in_array($normalizedLocale, $configuredLocales, true);
    }

    /**
     * @param string|null $localeCode
     *
     * @return string|null
     */
    private function normalizeLocale($localeCode)
    {
        if (!is_string($localeCode) || '' === $localeCode) {
            return null;
        }

        return strtolower(str_replace('_', '-', $localeCode));
    }

    /**
     * @return string[]
     */
    private function getConfiguredLocales()
    {
        $locales = (string) Configuration::get(self::CONFIG_LOCALES, 'de-CH,fr-CH,it-CH,rm-CH');
        $parts = array_filter(array_map('trim', explode(',', $locales)));

        return array_values(array_unique(array_map(static function ($locale) {
            return strtolower(str_replace('_', '-', $locale));
        }, $parts)));
    }

    /**
     * @return string
     */
    private function getDecimalSeparator()
    {
        return (string) Configuration::get(self::CONFIG_DECIMAL_SEPARATOR, '.');
    }

    /**
     * @return string
     */
    private function getGroupSeparator()
    {
        return (string) Configuration::get(self::CONFIG_GROUP_SEPARATOR, "'");
    }
}