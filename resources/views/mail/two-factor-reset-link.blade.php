<x-mail::message>
# Zweiten Faktor zurücksetzen

Sie haben bei {{ config('app.name') }} angefragt, den zweiten Faktor Ihres Kontos zurückzusetzen, z. B. weil das
Handy mit der Authenticator-App fehlt. Der Link gilt {{ $minutes }} Minuten und nur einmal; danach bestätigen Sie mit
E-Mail und Passwort.

<x-mail::button :url="$url">
Zweiten Faktor zurücksetzen
</x-mail::button>

Haben Sie das nicht angefragt? Dann ignorieren Sie diese Mail und ändern Sie zur Sicherheit Ihr Passwort – jemand
kennt es offenbar.
</x-mail::message>
