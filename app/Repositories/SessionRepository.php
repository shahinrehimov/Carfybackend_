<?php

namespace App\Repositories;

use App\Services\LangService;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class SessionRepository
{
    /**
     * @param Model&HasApiTokens $user
     */
    public function getSessions(Model $user): array
    {
        $currentTokenId = $user->currentAccessToken()?->id;

        $tokens = PersonalAccessToken::query()
            ->where('tokenable_id', $user->id)
            ->where('tokenable_type', $user->getMorphClass())
            ->orderByDesc('id')
            ->get();

        $current = null;
        $others  = [];

        foreach ($tokens as $token) {
            $token->is_current = ($token->id === $currentTokenId);

            if ($token->is_current) {
                $current = $token;
            } else {
                $others[] = $token;
            }
        }

        return [
            'current_session' => $current,
            'other_sessions'  => $others,
        ];
    }

    /**
     * @param Model&HasApiTokens $user
     */
    public function deleteSession(Model $user, int $tokenId): void
    {
        $token = PersonalAccessToken::query()
            ->where('id', $tokenId)
            ->where('tokenable_id', $user->id)
            ->where('tokenable_type', $user->getMorphClass())
            ->first();

        if (!$token) {
            throw new AccessDeniedHttpException(
                LangService::instance()
                    ->setDefault('Session not found or access denied.')
                    ->getLang('session_not_found_or_access_denied')
            );
        }

        $token->delete();
    }
}
