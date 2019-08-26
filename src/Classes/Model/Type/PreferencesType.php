<?php

namespace Helio\Panel\Model\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\StringType;
use Helio\Panel\Model\Preferences\AbstractPreferences;

class PreferencesType extends StringType
{
    /** @var string */
    const TypeName = 'preferences';

    /**
     * Gets the name of this type.
     *
     * @return string
     */
    public function getName(): string
    {
        return self::TypeName;
    }

    /**
     * @param $value
     * @param AbstractPlatform $platform
     *
     * @return mixed|string
     *
     * @throws ConversionException
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform): string
    {
        if (!$value instanceof AbstractPreferences) {
            throw new ConversionException('Invalid Object passed to PreferenceType->convertToDatabaseValue: .' . $value, 1566833900);
        }

        /** @var AbstractPreferences $value */
        $class = get_class($value);
        $intValue = $value->getIntegerValue();

        return "$class:$intValue";
    }

    /**
     * @param  mixed               $value
     * @param  AbstractPlatform    $platform
     * @return int|mixed
     * @throws ConversionException
     */
    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        if (null === $value || $value instanceof AbstractPreferences) {
            return $value;
        }

        if (1 !== mb_substr_count($value, ':')) {
            throw new ConversionException('Invalid String passed to PreferenceType->convertToPHPValue: .' . $value, 1566834121);
        }

        /** @var AbstractPreferences $type */
        /** @var int $value */
        [$type, $value] = explode(':', $value);

        return new $type((int) $value);
    }
}
