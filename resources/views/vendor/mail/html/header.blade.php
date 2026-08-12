@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" class="brand" aria-label="{{ __('idmis.public.system_name') }}">
<img src="{{ asset('images/branding/devolution-emblem.png') }}" class="logo" alt="{{ __('idmis.public.republic') }}">
<span class="brand-copy">
<strong>IDMIS</strong>
<span>{{ __('idmis.public.department_name') }}</span>
</span>
<img src="{{ asset('images/branding/kenya-flag.svg') }}" class="flag" alt="{{ __('idmis.mail.kenyan_flag') }}">
</a>
</td>
</tr>
