<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class GithubController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('github')
            ->scopes(['repo', 'read:user', 'admin:repo_hook'])
            ->redirect();
    }

    public function callback()
    {
        $githubUser = Socialite::driver('github')->user();

        $user = User::updateOrCreate(
            ['github_id' => $githubUser->getId()],
            [
                'name'                 => $githubUser->getName() ?? $githubUser->getNickname(),
                'email'                => $githubUser->getEmail(),
                'github_username'      => $githubUser->getNickname(),
                'github_avatar'        => $githubUser->getAvatar(),
                'github_token'         => $githubUser->token,
                'github_refresh_token' => $githubUser->refreshToken,
                'password'             => bcrypt(str()->random(32)),
            ]
        );

        Auth::login($user, remember: true);

        return redirect()->route('dashboard');
    }
}
