<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') — @yield('title') | IDMIS</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: #f3f7f4; color: #12304a; }
        header { background: #006600; color: #fff; }
        .bar, main, footer { width: min(72rem, calc(100% - 2.5rem)); margin-inline: auto; }
        .bar { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding-block: 1rem; }
        .identity { display: flex; align-items: center; gap: .8rem; color: inherit; text-decoration: none; }
        .emblem { width: 5rem; height: 3.5rem; padding: .25rem; object-fit: contain; background: #fff; border-radius: .4rem; }
        .flag { width: 2.5rem; height: auto; }
        .identity strong, .identity span { display: block; }
        .identity span { margin-top: .15rem; font-size: .75rem; opacity: .82; }
        main { min-height: calc(100vh - 10.5rem); display: flex; align-items: center; padding-block: 4rem; }
        .content { max-width: 44rem; }
        .code { margin: 0; color: #006600; font: 700 .875rem ui-monospace, monospace; }
        h1 { margin: 1rem 0 0; max-width: 18ch; font-size: clamp(2.25rem, 7vw, 4rem); line-height: 1.03; letter-spacing: -.035em; text-wrap: balance; }
        .message { margin: 1.25rem 0 0; max-width: 65ch; color: #475569; font-size: 1.05rem; line-height: 1.7; }
        .actions { display: flex; flex-wrap: wrap; gap: .75rem; margin-top: 2rem; }
        .button { display: inline-block; padding: .72rem 1rem; border-radius: .45rem; background: #006600; color: #fff; font-weight: 650; text-decoration: none; }
        .button.secondary { border: 1px solid #94a3b8; background: transparent; color: #12304a; }
        footer { border-top: 1px solid #cbd5e1; padding-block: 1.25rem; color: #475569; font-size: .8rem; }
        .skip { position: fixed; top: .75rem; left: .75rem; z-index: 10; transform: translateY(-200%); padding: .6rem .8rem; background: #fff; color: #12304a; }
        .skip:focus { transform: translateY(0); }
    </style>
</head>
<body>
<a class="skip" href="#main-content">{{ __('idmis.public.skip_to_main_content') }}</a>
<header>
    <div class="bar">
        <a class="identity" href="{{ url('/') }}">
            <img class="emblem" src="{{ asset('images/branding/devolution-emblem.png') }}" alt="{{ __('idmis.public.republic') }}">
            <span><strong>IDMIS</strong><span>{{ __('idmis.public.department_name') }}</span></span>
        </a>
        <img class="flag" src="{{ asset('images/branding/kenya-flag.svg') }}" alt="{{ __('idmis.mail.kenyan_flag') }}">
    </div>
</header>
<main id="main-content" tabindex="-1">
    <div class="content">
        <p class="code">HTTP @yield('code')</p>
        <h1>@yield('title')</h1>
        <p class="message">@yield('message')</p>
        <div class="actions">
            <a class="button" href="{{ url('/') }}">{{ __('idmis.public.home') }}</a>
            <a class="button secondary" href="{{ route('help') }}">{{ __('idmis.header.help') }}</a>
        </div>
    </div>
</main>
<footer>{{ __('idmis.public.system_name') }} · {{ __('idmis.public.republic') }}</footer>
</body>
</html>
