<x-mail::message>
# Konten aufgenommen

In die Organisation **{{ $organization }}** wurden {{ count($names) }} Konto/Konten mit einer bestätigten Adresse
{{ '@'.$domain }} automatisch aufgenommen:

@foreach ($names as $name)
- {{ $name }}
@endforeach

Die Personen wurden informiert und können jederzeit austreten. Die automatische Aufnahme schalten Sie unter «Meine
Organisation → Verwaltung» aus.

<x-mail::button :url="$url">
Organisation ansehen
</x-mail::button>
</x-mail::message>
