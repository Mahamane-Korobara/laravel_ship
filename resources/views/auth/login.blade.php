<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel Ship') }} - Connexion</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-zinc-950 text-zinc-100 antialiased">
    <main class="mx-auto flex min-h-full max-w-5xl items-center justify-center px-4">
        <div class="grid w-full overflow-hidden rounded-2xl border border-zinc-800 bg-zinc-900 shadow-2xl shadow-black/40 md:grid-cols-2">
            <div class="hidden bg-gradient-to-br from-zinc-800 via-zinc-900 to-black p-8 md:block">
                <h1 class="text-2xl font-bold text-white">Laravel Ship</h1>
                <p class="mt-3 text-sm text-zinc-400">Plateforme de déploiement pour vos projets Laravel.</p>
            </div>

            <div class="p-6 md:p-8">
                <h2 class="text-xl font-semibold text-white">Connexion</h2>
                <p class="mt-1 text-sm text-zinc-400">Accède à ton espace de déploiement.</p>

                @if ($errors->any())
                    <div class="mt-4 rounded-lg border border-rose-800/60 bg-rose-900/20 p-3 text-sm text-rose-300">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
                    @csrf
                    <div>
                        <label for="email" class="text-xs text-zinc-400">E-mail</label>
                        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                            class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 outline-none focus:border-zinc-500" />
                    </div>

                    <div>
                        <label for="password" class="text-xs text-zinc-400">Mot de passe</label>
                        <input id="password" name="password" type="password" required
                            class="mt-1 w-full rounded-lg border border-zinc-700 bg-zinc-950 px-3 py-2 text-sm text-zinc-100 outline-none focus:border-zinc-500" />
                    </div>

                    <label class="flex items-center gap-2 text-xs text-zinc-400">
                        <input type="checkbox" name="remember" class="rounded border-zinc-700 bg-zinc-950">
                        Se souvenir de moi
                    </label>

                    <button type="submit" class="w-full rounded-lg bg-white px-3 py-2 text-sm font-semibold text-zinc-900 transition hover:bg-zinc-200">
                        Se connecter
                    </button>

                    @if (Route::has('auth.github'))
                        <a href="{{ route('auth.github') }}" class="block w-full rounded-lg border border-zinc-700 px-3 py-2 text-center text-sm text-zinc-200 transition hover:bg-zinc-800">
                            Continuer avec GitHub
                        </a>
                    @endif
                </form>
            </div>
        </div>
    </main>
</body>
</html>
