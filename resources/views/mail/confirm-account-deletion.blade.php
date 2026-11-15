<x-mail::message>
# Konto löschen bestätigen

Sie möchten Ihr Konto bei {{ config('app.name') }} löschen. Der Link gilt {{ $minutes }} Minuten; auf der Seite
bestätigen Sie das Löschen nochmals. Erst dann wird das Konto gelöscht.

<x-mail::button :url="$url">
Konto löschen
</x-mail::button>

Haben Sie das nicht angefragt? Dann ignorieren Sie diese Mail – es wird nichts gelöscht. Jemand hatte aber Zugriff
auf Ihre Anmeldung: Melden Sie sich bitte ab und wieder an und informieren Sie die Administration.
</x-mail::message>
