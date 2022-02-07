<?php

namespace Autum\SAML\Listeners\Webhooks;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class UpdateUserModel
{
    
    /**
     * @var array
     */
    public $payload = [];
    
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct()
    {
        
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {

        $this->payload = $event->payload;

        $userData = collect($this->payload['payload']);

        $user = User::where('id', $userData->get('id'))->first();

        Log::info('Has user?');

        if($user) {

            Log::info('true');
            $fields = [
                'name',
                'username',
                'lastname',
                'email',
                'profile_photo_path',
            ];
            $diff = $userData->only($fields)->diffAssoc($user->only($fields));

            Log::info('remote diff');
            Log::info($diff);

            if(!$diff->isEmpty()) {
                $user->update($diff->all());
            }
            
        }

        
    }
}
