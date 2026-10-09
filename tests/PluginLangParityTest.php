<?php

declare(strict_types=1);

namespace Logingrupa\Activitylog\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Every plugin line exists in English and Latvian. Both lang files are flattened to dotted keys and compared.
 * The files are plain PHP arrays, so this test boots no application.
 */
final class PluginLangParityTest extends TestCase
{
    private const LANG_FOLDER = __DIR__.'/../lang';

    /**
     * testEveryEnglishKeyHasALatvianLine lists the English keys that lv/lang.php lacks.
     */
    public function testEveryEnglishKeyHasALatvianLine(): void
    {
        $this->assertSame([], array_keys(array_diff_key(self::lines('en'), self::lines('lv'))));
    }

    /**
     * testLatvianHoldsNoKeyThatEnglishLacks lists the Latvian keys with no English line.
     */
    public function testLatvianHoldsNoKeyThatEnglishLacks(): void
    {
        $this->assertSame([], array_keys(array_diff_key(self::lines('lv'), self::lines('en'))));
    }

    /**
     * testEveryLatvianLineIsFilled lists the Latvian keys whose line is empty or blank.
     */
    public function testEveryLatvianLineIsFilled(): void
    {
        $arBlankLines = array_filter(self::lines('lv'), static fn (string $sLine): bool => trim($sLine) === '');

        $this->assertSame([], array_keys($arBlankLines));
    }

    /**
     * lines reads one locale's lang file as dotted keys.
     *
     * @return array<string, string>
     */
    private static function lines(string $sLocale): array
    {
        $mLines = require self::LANG_FOLDER.'/'.$sLocale.'/lang.php';
        if (!is_array($mLines)) {
            throw new \UnexpectedValueException("lang/{$sLocale}/lang.php does not return an array.");
        }

        return self::flatten($mLines, '');
    }

    /**
     * flatten turns nested groups into dotted keys and refuses a value that is not a string.
     *
     * @param  array<mixed>  $arLines
     * @return array<string, string>
     */
    private static function flatten(array $arLines, string $sPrefix): array
    {
        $arFlat = [];
        foreach ($arLines as $mKey => $mLine) {
            $sKey = $sPrefix.$mKey;
            if (is_array($mLine)) {
                $arFlat += self::flatten($mLine, $sKey.'.');

                continue;
            }
            if (!is_string($mLine)) {
                throw new \UnexpectedValueException("The lang key {$sKey} does not hold a string.");
            }
            $arFlat[$sKey] = $mLine;
        }

        return $arFlat;
    }
}
