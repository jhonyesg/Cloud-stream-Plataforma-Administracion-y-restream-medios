<?php

namespace App\Services\Restream;

use App\Models\Channel;
use App\Models\RestreamQuota;
use App\Models\RestreamTarget;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RestreamQuotaGuard
{
    public const EXCEPTION_QUOTA_NOT_ENABLED = 'restream.quota_not_enabled';
    public const EXCEPTION_CAP_REACHED = 'restream.cap_reached';

    public function canEnableAnother(User $user, ?Channel $channel): bool
    {
        if (! $channel || ! $user->hasRestreamEnabledFor($channel->id)) {
            return false;
        }

        $max = $user->restreamMaxOutputsFor($channel->id);
        if ($max <= 0) {
            return false;
        }

        $used = $user->restreamUsedOutputsFor($channel->id);
        return $used < $max;
    }

    public function assertCanEnable(User $user, Channel $channel): void
    {
        DB::transaction(function () use ($user, $channel) {
            $quota = RestreamQuota::where('user_id', $user->id)
                ->where('channel_id', $channel->id)
                ->lockForUpdate()
                ->first();

            if (! $quota || ! $quota->enabled) {
                throw new RestreamQuotaException(
                    self::EXCEPTION_QUOTA_NOT_ENABLED,
                    "El módulo Restream no está habilitado para el canal «{$channel->display_name}»."
                );
            }

            $used = RestreamTarget::where('user_id', $user->id)
                ->where('channel_id', $channel->id)
                ->where('enabled', true)
                ->count();

            if ($used >= $quota->max_outputs) {
                throw new RestreamQuotaException(
                    self::EXCEPTION_CAP_REACHED,
                    "Cap de destinos alcanzado ({$used}/{$quota->max_outputs}) en este canal."
                );
            }
        });
    }
}
