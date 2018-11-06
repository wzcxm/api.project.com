<?php

namespace App\Jobs;

use Illuminate\Contracts\Mail\Mailer;

class ExampleJob extends Job
{

    protected $code;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($code)
    {
        //
        $this->code = $code;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(Mailer $mailer)
    {
        //
    }
}
