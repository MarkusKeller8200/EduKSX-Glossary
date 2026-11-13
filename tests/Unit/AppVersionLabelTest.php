<?php

namespace Tests\Unit;

use App\Support\AppVersion;
use Tests\TestCase;

/** Versionsbezeichnung je Umgebung: Release X.Y.Z, Beta X.Y.Z-beta.N, Developer X.Y.Z-dev.N (#308). */
class AppVersionLabelTest extends TestCase
{
    public function test_developer_mit_geplanter_version_und_commits_seit_release(): void
    {
        $planned = AppVersion::planned('0.12.0', (string) file_get_contents(base_path('CHANGELOG.md')));
        $dev = AppVersion::developerRelease('0.12.0', 'v0.12.0-5-gabc1234');

        $this->assertNotNull($dev);
        $this->assertSame(5, $dev['commits']);
        $this->assertSame('0.12.0', $dev['base']);
        $this->assertSame("{$planned}-dev.5", $dev['label']);
    }

    public function test_developer_nach_einer_beta_zaehlt_ab_dem_release(): void
    {
        $dev = AppVersion::developerRelease('0.13.0-beta.1', 'v0.12.0-12-gabc1234');

        $this->assertNotNull($dev);
        $this->assertSame('0.13.0-dev.12', $dev['label']);
    }

    public function test_beta_und_release_ohne_developer(): void
    {
        $this->assertNull(AppVersion::developerRelease('0.13.0-beta.1', 'v0.13.0-beta.1-0-gabc1234'));
        $this->assertNull(AppVersion::developerRelease('0.13.0', 'v0.13.0-0-gabc1234'));
    }
}
