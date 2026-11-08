<x-mail::message>
# Einladung in «{{ $organization }}»

{{ $invitedBy }} lädt Sie in die Organisation **{{ $organization }}** bei {{ config('app.name') }} ein. Mitglieder
sehen die Begriffe in der Darstellung ihrer Organisation.

Legen Sie mit dieser E-Mail-Adresse ein Konto an oder melden Sie sich an und nehmen Sie die Einladung unter «Mein
Konto → Meine Organisation» an. Die Einladung gilt bis am **{{ $validUntil }}**.

<x-mail::button :url="$register">
Konto anlegen
</x-mail::button>

Sie haben schon ein Konto? [Anmelden]({{ $login }})

Wenn Sie die Einladung nicht erwartet haben, können Sie diese E-Mail ignorieren.
</x-mail::message>
