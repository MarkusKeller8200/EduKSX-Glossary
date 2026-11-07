<?php

namespace Tests\Unit;

use App\Support\Totp;
use PHPUnit\Framework\TestCase;

/** TOTP nach RFC 6238 (#11): Testwerte aus Anhang B (SHA1, 8 Stellen) und Verhalten der Prüfung. */
class TotpTest extends TestCase
{
    private const RFC_SECRET = '12345678901234567890';

    public function test_rfc_6238_testwerte(): void
    {
        $key = self::RFC_SECRET;
        $cases = [59 => '94287082', 1111111109 => '07081804', 1111111111 => '14050471', 1234567890 => '89005924', 2000000000 => '69279037', 20000000000 => '65353130'];
        foreach ($cases as $time => $expected) {
            $this->assertSame($expected, Totp::codeAt($key, intdiv($time, 30), 8), "Zeit {$time}");
        }
    }

    public function test_base32_hin_und_zurueck(): void
    {
        $secret = Totp::base32Encode(self::RFC_SECRET);
        $this->assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $secret);
        $this->assertSame(self::RFC_SECRET, Totp::base32Decode($secret));
        $this->assertSame('287082', Totp::code($secret, 59));
        $this->assertSame(32, strlen(Totp::secret()));
    }

    public function test_pruefung_mit_uhrabweichung_und_ohne_wiederverwendung(): void
    {
        $secret = Totp::base32Encode(self::RFC_SECRET);
        $time = 1111111111;
        $code = Totp::code($secret, $time);

        $step = Totp::verify($secret, $code, null, $time);
        $this->assertSame(intdiv($time, 30), $step);
        $this->assertNotNull(Totp::verify($secret, $code, null, $time + 30));     // eine Periode später noch gültig
        $this->assertNull(Totp::verify($secret, $code, null, $time + 120));       // zu alt
        $this->assertNull(Totp::verify($secret, $code, $step, $time));            // schon verwendet
        $this->assertNull(Totp::verify($secret, '12345', null, $time));           // falsches Format
    }
}
