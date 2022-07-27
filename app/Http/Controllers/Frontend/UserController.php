<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Requests\Frontend\UpdatePassword;
use App\Http\Requests\Frontend\UpdateUser;
use App\Models\Formatters\Formatter;
use App\Models\Game;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use Formatter;

    /**
     * Display the specified resource.
     *
     * @param  User $user
     * @return \Illuminate\Http\Response
     */

    /**
     * @param  User  $user
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\Foundation\Application|\Illuminate\View\View
     */
    public function show(User $user)
    {
        $account = $user->account;

        $totalPlayed = Game::select(DB::raw('COUNT(*) AS cnt'))
            ->where('account_id', $account->id)
            ->where('status', Game::STATUS_COMPLETED)
            ->first()
            ->cnt;

        $totalWon = Game::select(DB::raw('COUNT(*) AS cnt'))
            ->where('account_id', $account->id)
            ->whereRaw('win > bet')
            ->first()
            ->cnt;

        $gamesByResult = [
            ['category' => __('Win'),  'value' => $totalWon],
            ['category' => __('Loss'), 'value' => $totalPlayed - $totalWon]
        ];

        $gamesByType = Cache::remember('users.' . $user->id . '.games_by_type', 5, function() use($account) {
            return Game::where('account_id', $account->id)
                ->where('status', Game::STATUS_COMPLETED)
                ->select(
                    DB::raw('REPLACE(gameable_type, "GameMultiSlots", "GameSlots") AS gameable_type'),
                    DB::raw('COUNT(*) AS cnt'),
                    DB::raw('MAX(win) AS max_win')
                )
                ->groupBy(DB::raw('REPLACE(gameable_type, "GameMultiSlots", "GameSlots")'))
                ->get()
                ->map(function ($game) {
                    return [
                        'category' => __('app.game_' . str_replace('GameMultiSlots', 'GameSlots', class_basename($game->gameable_type))),
                        'value' => $game->cnt,
                        'max_win' => (float) $game->max_win,
                        '_max_win' => $this->decimal($game->max_win),
                    ];
                })
                ->sortByDesc('value')
                ->values();
        });

        $recentGames = Game::where('account_id', $account->id)
            ->where('status', Game::STATUS_COMPLETED)
            ->orderBy('updated_at', 'desc')
            ->take(15)
            ->get();

        return view('frontend.pages.users.show', [
            'user'              => $user,
            'games_by_result'   => $gamesByResult,
            'games_by_type'     => $gamesByType,
            'recent_games'      => $totalPlayed > 0 ? $recentGames : collect(),
            'total_played'      => $totalPlayed,
            'last_played'       => $totalPlayed > 0 ? $recentGames->first()->updated_at : NULL,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function edit(Request $request)
    {
        return view('frontend.pages.users.edit', ['user' => $request->user()]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function update(UpdateUser $request)
    {
        $user = $request->user();
        // validator passed, update fields
        $user->name = $request->name;
        // update email only if the user doesn't have linked social profiles
        if ($user->profiles->isEmpty()) {
            $user->email = $request->email;
        }
        $user->save();

        return redirect()
            ->route('frontend.users.show', $user)
            ->with('success', __('Your profile is successfully saved'));
    }

    public function editPassword(Request $request)
    {
        return view('frontend.pages.users.password', ['user' => $request->user()]);
    }

    public function updatePassword(UpdatePassword $request)
    {
        $user = $request->user();
        $user->password = Hash::make($request->password);
        $user->save();

        return redirect()
            ->route('frontend.users.show', $user)
            ->with('success', __('Your password is successfully saved'));
    }
}
