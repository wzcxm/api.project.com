<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/18
 * Time: 17:22
 */

namespace App\Jobs;

use Illuminate\Support\Facades\Mail;

class EmailJob extends Job
{
    protected $code;
    protected $email;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($code,$email)
    {
        //
        $this->code = $code;
        $this->email = $email;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //发送邮件
        $code = $this->code;
        $email = $this->email;
        Mail::send('emails.email', ['code' => $code], function($message) use($email)
        {
            $message->to($email)->subject('TestEmail验证码');
        });

    }

}