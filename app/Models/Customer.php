<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory, Auditable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'birth_date',
        'gender',
        'level',
        'loyalty_points',
        'is_active',
    ];

    protected $attributes = [
        'level' => 'level1', // Default to level1 (Bronze) for new customers (0 points)
        'loyalty_points' => 0,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Override create to ignore loyalty_points from input
     */
    public static function create(array $attributes = [])
    {
        // Remove loyalty_points from input to prevent manual setting
        unset($attributes['loyalty_points']);

        return static::query()->create($attributes);
    }

    /**
     * Get the transactions for the customer.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Update customer level based on loyalty points
     */
    public function updateLevelBasedOnPoints(): bool
    {
        $points = $this->loyalty_points;

        // Get threshold from settings (using new flexible threshold system)
        $level1Max = (int) Setting::get('loyalty_level1_max', 4999);
        $level2Min = (int) Setting::get('loyalty_level2_min', 5000);
        $level2Max = (int) Setting::get('loyalty_level2_max', 24999);
        $level3Min = (int) Setting::get('loyalty_level3_min', 25000);
        $level3Max = (int) Setting::get('loyalty_level3_max', 99999);
        $level4Min = (int) Setting::get('loyalty_level4_min', 100000);

        // Determine new level based on points (using level1-4 keys)
        $newLevel = 'level1'; // Default
        if ($points >= $level4Min) {
            $newLevel = 'level4';
        } elseif ($points >= $level3Min && $points <= $level3Max) {
            $newLevel = 'level3';
        } elseif ($points >= $level2Min && $points <= $level2Max) {
            $newLevel = 'level2';
        } elseif ($points <= $level1Max) {
            $newLevel = 'level1';
        }

        // Only update if level changed
        if ($this->level !== $newLevel) {
            $this->level = $newLevel;
            $this->save();
            return true; // Level was updated
        }

        return false; // Level unchanged
    }

    /**
     * Add loyalty points to customer and auto-update level
     */
    public function addLoyaltyPoints(int $points): void
    {
        $this->increment('loyalty_points', $points);
        // Refresh to get updated loyalty_points value
        $this->refresh();
        // Auto-update level based on new points
        $this->updateLevelBasedOnPoints();
    }

    /**
     * Deduct loyalty points from customer and auto-update level
     */
    public function deductLoyaltyPoints(int $points): bool
    {
        if ($this->loyalty_points >= $points) {
            $this->decrement('loyalty_points', $points);
            // Refresh to get updated loyalty_points value
            $this->refresh();
            // Auto-update level based on new points
            $this->updateLevelBasedOnPoints();
            return true;
        }
        return false;
    }
}
