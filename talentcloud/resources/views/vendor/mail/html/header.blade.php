{{-- Cabecera de los correos: el logo de TalentCloud AI en PNG (los clientes de correo no muestran bien los SVG). --}}
@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ asset('marca/talentcloud-ai-correo.png') }}" width="171" height="40" alt="TalentCloud AI" style="height: 40px; width: 171px; max-width: 100%; border: 0;">
</a>
</td>
</tr>
