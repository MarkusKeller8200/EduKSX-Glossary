<x-mail::message>
# Zweiter Faktor entfernt

Der zweite Faktor Ihres Kontos bei {{ config('app.name') }} wurde entfernt ({{ $by }}). Bei der nächsten Anmeldung
genügen E-Mail und Passwort; danach können Sie den zweiten Faktor wieder einrichten.

<x-mail::button :url="$url">
Zweiten Faktor einrichten
</x-mail::button>

Haben Sie das nicht veranlasst? Dann ändern Sie bitte sofort Ihr Passwort und melden Sie sich bei der Administration.
</x-mail::message>
