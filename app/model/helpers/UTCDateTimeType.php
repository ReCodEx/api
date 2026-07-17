<?php

namespace DoctrineExtensions\DBAL\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\DateTimeType;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Special type for storing and loading UTC datetime structures from database.
 * Taken from: http://docs.doctrine-project.org/projects/doctrine-orm/en/latest/cookbook/working-with-datetime.html
 */
class UTCDateTimeType extends DateTimeType
{
    private static $utc;

    private static function getUtc()
    {
        return self::$utc ? self::$utc : self::$utc = new DateTimeZone('UTC');
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value instanceof DateTimeImmutable) {
            $value = DateTime::createFromImmutable($value);
        }
        if ($value instanceof DateTime) {
            $value->setTimezone(self::getUtc());
        }

        return parent::convertToDatabaseValue($value, $platform);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?DateTime
    {
        if (null === $value || $value instanceof DateTime) {
            return $value;
        }

        $converted = DateTime::createFromFormat(
            $platform->getDateTimeFormatString(),
            $value,
            self::getUtc()
        );

        if (!$converted) {
            $value = strlen($value) > 32 ? substr($value, 0, 20) . '...' : $value;
            throw new ConversionException(
                'Could not convert database value "' . $value . '" to Doctrine Type datetime. Expected format: '
                    . $platform->getDateTimeFormatString(),
            );
        }

        return $converted;
    }
}
