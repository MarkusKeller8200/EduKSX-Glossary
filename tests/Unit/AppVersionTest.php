<?php

namespace Tests\Unit;

use App\Support\AppVersion;
use PHPUnit\Framework\TestCase;

class AppVersionTest extends TestCase
{
    public function test_naechste_version_und_genau_ein_release(): void
    {
        $changelog = "## [Unveröffentlicht]\n\n### Neu\n\n- etwas\n";
        $this->assertSame('0.2.0', AppVersion::planned('0.1.0', $changelog));
        $this->assertNull(AppVersion::developerRelease('0.1.0', 'v0.1.0-0-gabc1234'));
        $this->assertNull(AppVersion::developerRelease('0.1.0', null));
    }

    public function test_nur_behoben_ergibt_patch(): void
    {
        $this->assertSame('0.1.1', AppVersion::planned('0.1.0', "## [Unveröffentlicht]\n\n### Behoben\n\n- x\n"));
    }

    public function test_changelog_zerlegen(): void
    {
        $releases = AppVersion::parse("## [Unveröffentlicht]\n\n### Neu\n\n- **A:** eins\n  weiter\n\n## [0.1.0] – 2026-11-07\n\n### Neu\n\n- zwei\n");
        $this->assertSame('Unveröffentlicht', $releases[0]['version']);
        $this->assertSame('**A:** eins weiter', $releases[0]['sections'][0]['items'][0]);
        $this->assertSame('2026-11-07', $releases[1]['date']);
    }
}
