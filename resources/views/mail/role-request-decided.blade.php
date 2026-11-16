<x-mail::message>
@if ($accepted)
# Willkommen in der Moderation

Ihre Bewerbung als Moderator/in bei {{ config('app.name') }} wurde angenommen. Sie können jetzt Vorschläge prüfen und
Begriffe bearbeiten. Die Administration erreichen Sie über den Link unten; der zweite Faktor ist dafür Pflicht.
@else
# Ihre Bewerbung als Moderator/in

Ihre Bewerbung bei {{ config('app.name') }} wurde diesmal nicht angenommen.
@endif

@if ($note)
**Hinweis der Administration:** {{ $note }}
@endif

<x-mail::button :url="$url">
{{ $accepted ? 'Zur Administration' : 'Mein Konto' }}
</x-mail::button>
</x-mail::message>
