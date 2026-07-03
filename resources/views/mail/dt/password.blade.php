<x-mail::message>
# Clave de acceso temporal

Hola, te enviamos este correo porque has solicitado una clave de acceso al portal {{ config('app.name') }} como funcionario de la DT.

Tu clave temporal es: **{{ $password }}**

Te recordamos que por razones de seguridad, la clave tendrá una duración de {{ config('auth.passwords_expires_days') }} días corridos, transcurridos los cuales caducará.

<x-mail::button :url="$url">
Inicio de sesión
</x-mail::button>

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>
