<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Requests\Frontend\ClaimFaucet;
use App\Models\Bonus;
use App\Rules\FaucetIsAllowed;
use App\Services\AccountService;
use App\Services\BonusService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;

class BonusController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $url = url('/') . '?ref=' . $user->id;
        $referralBonusesTypes = [
            Bonus::TYPE_REFERRER_SIGN_UP,
            Bonus::TYPE_REFERRER_GAME_BET,
            Bonus::TYPE_REFERRER_GAME_LOSS,
            Bonus::TYPE_REFERRER_GAME_WIN,
            Bonus::TYPE_REFERRER_DEPOSIT,
            Bonus::TYPE_REFERRER_RAFFLE_TICKET,
        ];

        $faucetRule = new FaucetIsAllowed($request->user());

        return view('frontend.pages.bonuses.index', [
            'user' => $user,
            'referral_bonuses'  => [
                'referee_sign_up_credits'       => $user->referee_sign_up_credits ?: config('settings.bonuses.referral.referee_sign_up_credits'),
                'referrer_sign_up_credits'      => $user->referrer_sign_up_credits ?: config('settings.bonuses.referral.referrer_sign_up_credits'),
                'referrer_game_loss_pct'        => $user->referrer_game_loss_pct ?: config('settings.bonuses.referral.referrer_game_loss_pct'),
                'referrer_game_win_pct'         => $user->referrer_game_win_pct ?: config('settings.bonuses.referral.referrer_game_win_pct'),
                'referrer_raffle_ticket_pct'    => config('settings.bonuses.raffle.ticket_pct'),
                'referrer_deposit_pct'          => $user->referrer_deposit_pct ?: config('settings.bonuses.referral.referrer_deposit_pct'),
            ],
            'referred_users_count'  => $user->referees()->count(),
            'referral_total_bonus'  => $user->account->bonuses($referralBonusesTypes)->sum('amount'),
            'referral_url'          => $url,
            'share_subject'         => __('Sign up with :name now and get free credits', ['name' => __('Crypto Casino')]),
            'share_body'            => __('Click this link to sign up: :url', ['url' => $url]),
            'faucet'                => [
                'config'    => config('settings.bonuses.faucet'),
                'allowed'   => $faucetRule->passes(),
                'time'      => $faucetRule->getAllowedTime()->getTimestamp(),
            ]
        ]);
    }

    public function faucet(ClaimFaucet $request)
    {
        $accountService = new AccountService($request->user()->account);

        BonusService::create($request->user()->account, Bonus::TYPE_FAUCET, config('settings.bonuses.faucet.amount'));

        return back()->with('success', __('Faucet successfully claimed.'));
    }
}
