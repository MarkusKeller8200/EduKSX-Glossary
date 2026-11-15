<x-mail::message>
# Neue E-Mail-Adresse bestätigen

Für Ihr Konto bei {{ config('app.name') }} soll künftig diese Adresse gelten. Bitte bestätigen Sie sie innerhalb von
60 Minuten; erst danach wird sie übernommen.

<x-mail::button :url="$url">
Adresse bestätigen
</x-mail::button>

Haben Sie das nicht angefragt? Dann ignorieren Sie diese Mail – es ändert sich nichts.
</x-mail::message>
