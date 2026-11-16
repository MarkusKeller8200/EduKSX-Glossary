<x-mail::message>
# Neue Bewerbung als Moderator/in

{{ $applicant }} hat sich bei {{ config('app.name') }} als Moderator/in beworben. Begründung und Entscheid finden Sie
in der Administration unter «Benutzer › Anfragen».

<x-mail::button :url="$url">
Zu den Anfragen
</x-mail::button>
</x-mail::message>
