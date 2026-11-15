<x-mail::message>
# Einladung angenommen

{{ $member }} hat mit der eingeladenen E-Mail-Adresse ein Konto angelegt und ist jetzt Mitglied von
**{{ $organization }}**.

<x-mail::button :url="$url">
Organisation ansehen
</x-mail::button>
</x-mail::message>
