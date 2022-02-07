<?php

namespace Autum\SAML\Jobs;

use Spatie\WebhookClient\Jobs\ProcessWebhookJob as ProcessWebhookJobBaseClass;

class ProcessWebhookJob extends ProcessWebhookJobBaseClass
{   

    public $events = [
        'AccountUpdated' => 'Autum\\SAML\\Events\\Webhooks\AccountUpdatedEvent',
    ];

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if($this->webhookCall->payload && 
           in_array($this->webhookCall->payload['event'], array_keys($this->events))) 
        {
            $class = $this->events[$this->webhookCall->payload['event']];

            (new $class)->dispatch($this->webhookCall->payload);

        }
    }
}
