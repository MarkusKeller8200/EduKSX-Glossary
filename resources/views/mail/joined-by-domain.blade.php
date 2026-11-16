<x-mail::message>
# In eine Organisation aufgenommen

Ihr Konto bei {{ config('app.name') }} wurde in die Organisation **{{ $organization }}** aufgenommen, weil Ihre
bestätigte E-Mail-Adresse zur Domain {{ '@'.$domain }} gehört und die Organisation Konten dieser Domain automatisch
aufnimmt. Sie sehen die Begriffe nun in der Darstellung dieser Organisation.

Möchten Sie nicht Mitglied sein, treten Sie unter «Mein Konto → Meine Organisation» aus.

<x-mail::button :url="$url">
Meine Organisation
</x-mail::button>
</x-mail::message>
