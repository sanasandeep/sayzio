<?php

namespace Tests\Unit;

use App\Support\CountryDialCodes;
use PHPUnit\Framework\TestCase;

class CountryDialCodesTest extends TestCase
{
    public function test_whatsapp_digits_restore_the_country_and_local_number(): void
    {
        foreach (['917013406816' => ['+91', '7013406816'], '17013406816' => ['+1', '7013406816'], '971501234567' => ['+971', '501234567']] as $stored => $expected) {
            $this->assertSame($expected, CountryDialCodes::parse((string) $stored, true));
        }
    }

    public function test_local_profile_numbers_are_not_reinterpreted(): void
    {
        $this->assertSame(['+1', '7013406816'], CountryDialCodes::parse('7013406816'));
        $this->assertSame(['+91', '7013406816'], CountryDialCodes::parse('+917013406816'));
        $this->assertSame(['+1', ''], CountryDialCodes::parse('', true));
    }
}
