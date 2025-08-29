<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Aviso de caducidad</title>
</head>
<body>
    <p>Hola {{ $empresa }},</p>
    <p>Tu certificado digital caduca en 
        <strong>{{ $diasRestantes }} días</strong> (fecha: {{ $fechaValidez }}).
    </p>
    @if($diasRestantes <= 10)
        <p style="color:red;">Atención: te queda menos de 10 días.</p>
    @elseif($diasRestantes <= 20)
        <p style="color:orange;">Atención: te queda menos de 20 días.</p>
    @else
        <p style="color:blue;">Te queda menos de 30 días.</p>
    @endif
</body>
</html>
