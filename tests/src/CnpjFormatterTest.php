<?php

namespace Tests\ConectaEnte;

use ConectaEnte\Services\CnpjFormatter;
use PHPUnit\Framework\TestCase;

class CnpjFormatterTest extends TestCase
{
    public function testFormatsFourteenDigits()
    {
        $this->assertSame('00.000.000/0001-91', CnpjFormatter::format('00000000000191'));
        $this->assertSame('12.200.176/0001-76', CnpjFormatter::format('12200176000176'));
    }

    public function testReturnsAnythingElseUntouched()
    {
        $this->assertSame('', CnpjFormatter::format(null));
        $this->assertSame('123', CnpjFormatter::format('123'));
        $this->assertSame('12200176000176x', CnpjFormatter::format('12200176000176x'));
        $this->assertSame("12200176000176\n", CnpjFormatter::format("12200176000176\n"));
    }
}
