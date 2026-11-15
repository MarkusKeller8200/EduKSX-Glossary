<x-mail::message>
# Neue Wiederherstellungscodes

Für Ihr Konto bei {{ config('app.name') }} wurden neue Wiederherstellungscodes erzeugt. Die bisherigen Codes gelten
nicht mehr.

<x-mail::button :url="$url">
Zweiten Faktor ansehen
</x-mail::button>

Haben Sie das nicht veranlasst? Dann ändern Sie bitte sofort Ihr Passwort und melden Sie sich bei der Administration.
</x-mail::message>
