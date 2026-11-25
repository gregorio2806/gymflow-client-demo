<?php

namespace App\Actions;

use App\Models\Gym;
use App\Models\GymWebsite;
use App\Support\Audit;
use App\Support\WebsiteRichText;
use App\Tenancy\CurrentGym;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ManageGymWebsite
{
    public function save(array $data, ?UploadedFile $file, ?UploadedFile $logo = null, ?UploadedFile $plansImage = null): void
    {
        $gym = app(CurrentGym::class)->get();
        Gate::authorize('manageWebsite', $gym);
        $newPaths = [];
        try {
            DB::transaction(function () use ($gym, $data, $file, $logo, $plansImage, &$newPaths) {
                Gym::whereKey($gym->id)->lockForUpdate()->firstOrFail();
                $site = GymWebsite::lockForUpdate()->first() ?? new GymWebsite;
                $this->checkRevision($site, (int) $data['revision']);
                $old = $site->draft_content ?? $site->published_content ?? [];
                $content = array_intersect_key($data, array_flip(['description', 'contact', 'plans', 'facilities', 'announcements']));
                $content['gym_name'] = $data['gym_name'] ?? $old['gym_name'] ?? $gym->name;
                $content['plans_mode'] = $data['plans_mode'] ?? $old['plans_mode'] ?? 'cards';
                $content['plans'] = $data['plans'] ?? $old['plans'] ?? [];
                $content['about_html'] = WebsiteRichText::sanitize($data['about'] ?? '');
                if (isset($data['privacy']) || isset($old['privacy'])) {
                    $content['privacy'] = $data['privacy'] ?? $old['privacy'];
                    foreach (['contact_form_enabled', 'reviewed'] as $key) {
                        $content['privacy'][$key] = filter_var($content['privacy'][$key] ?? false, FILTER_VALIDATE_BOOLEAN);
                    }
                }
                foreach ($content['plans'] as &$plan) {
                    if (! isset($plan['price_php'])) {
                        continue;
                    }
                    [$whole, $fraction] = array_pad(explode('.', $plan['price_php'], 2), 2, '');
                    $plan['price_centavos'] = (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
                    unset($plan['price_php']);
                }
                unset($plan);
                if (! ($data['remove_image'] ?? false)) {
                    $content['image_path'] = $old['image_path'] ?? null;
                    $content['image_alt'] = $data['image_alt'] ?? '';
                }
                if ($file) {
                    $newPath = $this->storeImage($file, $gym->id);
                    $newPaths[] = $newPath;
                    $content['image_path'] = $newPath;
                    $content['image_alt'] = $data['image_alt'];
                }
                if (! empty($content['image_path']) && empty(trim($content['image_alt'] ?? ''))) {
                    throw ValidationException::withMessages(['image_alt' => 'Describe the image for people using screen readers.']);
                }
                if (! ($data['remove_logo'] ?? false)) {
                    $content['logo_path'] = $old['logo_path'] ?? null;
                }
                if ($logo) {
                    $content['logo_path'] = $this->storeImage($logo, $gym->id, 'logo', 512);
                    $newPaths[] = $content['logo_path'];
                }
                if (! ($data['remove_plans_image'] ?? false)) {
                    $content['plans_path'] = $old['plans_path'] ?? null;
                    $content['plans_alt'] = $data['plans_alt'] ?? $old['plans_alt'] ?? '';
                }
                if ($plansImage) {
                    $content['plans_path'] = $this->storeImage($plansImage, $gym->id, 'plans_image', 2400);
                    $content['plans_alt'] = $data['plans_alt'];
                    $newPaths[] = $content['plans_path'];
                }
                if ($content['plans_mode'] === 'image' && empty($content['plans_path'])) {
                    throw ValidationException::withMessages(['plans_image' => 'Upload your plans image, or choose plan cards.']);
                }
                if (! empty($content['plans_path']) && empty(trim($content['plans_alt'] ?? ''))) {
                    throw ValidationException::withMessages(['plans_alt' => 'Include the plan names, prices and duration for visitors who cannot read the image.']);
                }
                $site->forceFill(['draft_content' => $content, 'draft_revision' => ($site->draft_revision ?? 0) + 1])->save();
                Audit::log('website.draft_saved', $site, ['after' => ['revision' => $site->draft_revision]]);
                // Retain only draft/live hero, logo and plans assets (six files maximum).
                $this->cleanImages($site);
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($newPaths);
            throw $exception;
        }
    }

    public function publish(int $revision): void
    {
        $gym = app(CurrentGym::class)->get();
        Gate::authorize('manageWebsite', $gym);
        DB::transaction(function () use ($gym, $revision) {
            Gym::whereKey($gym->id)->lockForUpdate()->firstOrFail();
            $site = GymWebsite::lockForUpdate()->firstOrFail();
            $this->checkRevision($site, $revision);
            if (! $site->draft_content) {
                throw ValidationException::withMessages(['revision' => 'Save a draft before publishing.']);
            }
            if ($site->published_content === $site->draft_content) {
                return;
            }
            $site->forceFill(['published_content' => $site->draft_content, 'published_at' => now()->utc()])->save();
            Audit::log('website.published', $site);
            $this->cleanImages($site);
        });
    }

    private function checkRevision(GymWebsite $site, int $revision): void
    {
        if (($site->draft_revision ?? 0) !== $revision) {
            throw ValidationException::withMessages(['revision' => 'This draft changed in another window. Reload before saving or publishing.']);
        }
    }

    private function cleanImages(GymWebsite $site): void
    {
        $keep = [];
        foreach (['image_path', 'logo_path', 'plans_path'] as $key) {
            $keep[] = $site->draft_content[$key] ?? null;
            $keep[] = $site->published_content[$key] ?? null;
        }
        $keep = array_filter($keep);
        $obsolete = array_values(array_diff(Storage::disk('local')->files('websites/'.$site->gym_id), $keep));
        DB::afterCommit(fn () => Storage::disk('local')->delete($obsolete));
    }

    private function storeImage(UploadedFile $file, int $gymId, string $field = 'image', int $maxSize = 1600): string
    {
        $source = @imagecreatefromstring(file_get_contents($file->getRealPath()));
        if (! $source) {
            throw ValidationException::withMessages([$field => 'Choose a valid JPEG, PNG, or WebP image.']);
        }
        imagealphablending($source, false);
        imagesavealpha($source, true);
        $scale = min(1, $maxSize / max(imagesx($source), imagesy($source)));
        $image = imagescale($source, max(1, (int) (imagesx($source) * $scale)), max(1, (int) (imagesy($source) * $scale)));
        imagealphablending($image, false);
        imagesavealpha($image, true);
        ob_start();
        imagewebp($image, null, 82);
        $bytes = ob_get_clean();
        imagedestroy($source);
        imagedestroy($image);
        $path = 'websites/'.$gymId.'/'.Str::uuid().'.webp';
        if (! Storage::disk('local')->put($path, $bytes)) {
            throw new \RuntimeException('Unable to save the website image.');
        }

        return $path;
    }
}
