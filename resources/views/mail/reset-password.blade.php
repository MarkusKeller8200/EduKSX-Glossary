<x-mail::message>
# Passwort zurücksetzen

Für dieses Konto bei {{ config('app.name') }} wurde ein neues Passwort angefordert. Der Link gilt 60 Minuten und
lässt sich einmal verwenden.

<x-mail::button :url="$url">
Neues Passwort setzen
</x-mail::button>

Haben Sie nichts angefordert? Dann ignorieren Sie diese Nachricht; Ihr Passwort bleibt unverändert.
</x-mail::message>
