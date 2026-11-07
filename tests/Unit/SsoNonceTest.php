<?php

namespace Tests\Unit;

use App\Access\Sso\MicrosoftLogin;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** Microsoft-Anmeldung: nonce im id_token (#61). */
class SsoNonceTest extends TestCase
{
    public function test_passender_nonce(): void
    {
        MicrosoftLogin::checkNonce((object) ['nonce' => 'abc123'], 'abc123');
        $this->addToAssertionCount(1);
    }

    public function test_falscher_nonce(): void
    {
        $this->expectException(RuntimeException::class);
        MicrosoftLogin::checkNonce((object) ['nonce' => 'manipuliert'], 'abc123');
    }

    public function test_fehlender_nonce(): void
    {
        $this->expectException(RuntimeException::class);
        MicrosoftLogin::checkNonce((object) [], 'abc123');
    }

    public function test_ohne_nonce_in_der_sitzung(): void
    {
        $this->expectException(RuntimeException::class);
        MicrosoftLogin::checkNonce((object) ['nonce' => 'abc123'], null);
    }
}
