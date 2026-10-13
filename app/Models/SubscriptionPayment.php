<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPayment extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id', 'user_id', 'request_id', 'billing_cycle', 'amount', 'currency', 'status', 'payment_methods', 'billing_email', 'checkout_session_id', 'checkout_url', 'payment_id', 'paid_at', 'granted_until'];
    protected $hidden = ['billing_email', 'request_id', 'payment_id', 'payment_methods'];
    protected $casts = ['amount' => 'integer', 'user_id' => 'integer', 'payment_methods' => 'array', 'paid_at' => 'immutable_datetime', 'granted_until' => 'immutable_datetime'];

    public function summary(): array
    {
        return ['id' => $this->id, 'billing_cycle' => $this->billing_cycle, 'amount' => $this->amount, 'currency' => $this->currency, 'status' => $this->status, 'checkout_url' => in_array($this->status, ['creating', 'pending'], true) ? $this->checkout_url : null, 'test_mode' => true];
    }
}
