<x-mail::message>
# E-Mail-Adresse bestätigen

Für diese Adresse wurde ein Konto bei {{ config('app.name') }} angelegt. Mit dem Knopf bestätigen Sie die Adresse
und schalten das Konto frei. Der Link gilt 60 Minuten.

<x-mail::button :url="$url">
Adresse bestätigen
</x-mail::button>

Waren Sie das nicht? Dann ignorieren Sie diese Nachricht; das Konto wird nach 7 Tagen automatisch gelöscht.
</x-mail::message>
