<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'cpf', 'telegram', 'telegram_chat_id', 'telegram_user_id', 'role', 'status', 'password', 'telegram_verified_at', 'primary_salary', 'primary_monthly_hours'])]
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
            'primary_salary' => 'decimal:2',
            'primary_monthly_hours' => 'float',
        ];
    }

    public function primaryHourlyRate(): ?float
    {
        $salary = (float) ($this->primary_salary ?? 0);
        $hours = (float) ($this->primary_monthly_hours ?? 0);

        return $salary > 0 && $hours > 0 ? round($salary / $hours, 2) : null;
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
