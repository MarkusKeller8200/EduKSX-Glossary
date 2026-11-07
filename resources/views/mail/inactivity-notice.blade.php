<x-mail::message>
# Ihr Konto wird gelöscht

Sie haben sich seit fast zwei Jahren nicht mehr bei {{ config('app.name') }} angemeldet. Nach unserem Löschkonzept
löschen wir das Konto am **{{ $deleteOn }}**.

Möchten Sie es behalten? Dann melden Sie sich bis dahin einmal an.

<x-mail::button :url="$url">
Anmelden
</x-mail::button>

Ihre Vorschläge bleiben nach der Löschung ohne Angaben zu Ihrer Person erhalten.
</x-mail::message>
