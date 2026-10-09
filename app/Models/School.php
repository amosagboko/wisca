<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Support\LandingContent;

class School extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'slug', 'address', 'logo', 'settings', 'status'];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    public function academicSessions(): HasMany
    {
        return $this->hasMany(AcademicSession::class);
    }

    public function pillars(): HasMany
    {
        return $this->hasMany(Pillar::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(SchoolClass::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function landingContent(): array
    {
        return LandingContent::resolve($this->settings['landing'] ?? null);
    }

    public function landingHeroUrl(): string
    {
        $path = $this->settings['landing']['hero_image'] ?? null;

        if ($path) {
            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk('public');

            return $disk->url($path);
        }

        return asset('images/landing-hero.jpg');
    }

    public function storeLandingHero(UploadedFile $file): void
    {
        $settings = $this->settings ?? [];
        $landing = $settings['landing'] ?? [];

        if (! empty($landing['hero_image'])) {
            Storage::disk('public')->delete($landing['hero_image']);
        }

        $landing['hero_image'] = $file->store('landing-heroes', 'public');
        $settings['landing'] = $landing;

        $this->forceFill(['settings' => $settings])->save();
    }

    public function deleteLandingHeroFile(): void
    {
        $settings = $this->settings ?? [];
        $landing = $settings['landing'] ?? [];

        if (empty($landing['hero_image'])) {
            return;
        }

        Storage::disk('public')->delete($landing['hero_image']);
        unset($landing['hero_image']);
        $settings['landing'] = $landing;

        $this->forceFill(['settings' => $settings])->save();
    }

    public function logoUrl(): ?string
    {
        if (! $this->logo) {
            return null;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('public');

        return $disk->url($this->logo);
    }

    public function storeLogo(UploadedFile $file): void
    {
        if ($this->logo) {
            Storage::disk('public')->delete($this->logo);
        }

        $this->forceFill([
            'logo' => $file->store('school-logos', 'public'),
        ])->save();
    }

    public function deleteLogoFile(): void
    {
        if ($this->logo) {
            Storage::disk('public')->delete($this->logo);
            $this->forceFill(['logo' => null])->save();
        }
    }

    protected static function booted(): void
    {
        static::deleting(function (self $school) {
            if ($school->logo) {
                Storage::disk('public')->delete($school->logo);
            }

            $hero = $school->settings['landing']['hero_image'] ?? null;
            if ($hero) {
                Storage::disk('public')->delete($hero);
            }
        });
    }
}
