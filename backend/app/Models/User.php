<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'cpf', 'telegram', 'telegram_chat_id', 'telegram_user_id', 'role', 'status', 'password', 'telegram_verified_at'])]
#[Hidden(['password', 'remember_token', 'iphone_ingest_token_hash'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'telegram_verified_at' => 'datetime',
            'iphone_ingest_token_created_at' => 'datetime',
            'iphone_ingest_last_used_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function financeRecords()
    {
        return $this->hasMany(FinanceRecord::class);
    }

    public function categoryGoals()
    {
        return $this->hasMany(CategoryGoal::class);
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function pendingTelegramRecords()
    {
        return $this->hasMany(PendingTelegramRecord::class);
    }

    public function bankNotificationEvents()
    {
        return $this->hasMany(BankNotificationEvent::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
