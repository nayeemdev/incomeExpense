<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use NotificationChannels\WebPush\PushSubscription as WebPushSubscription;

class PushSubscription extends WebPushSubscription
{
    use HasUlids;
}
