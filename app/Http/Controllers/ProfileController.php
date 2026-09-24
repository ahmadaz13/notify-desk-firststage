<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\User;
use App\Services\CompletedWorkService;
use App\Support\AppointmentTypes;
use App\Support\OperationalTime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Work profile (§17, P13) — not HR. Self-editable: photo, name, job title, phone. Work email and role
 * are managed by an Owner through Team & Roles and are read-only here. Only the signed-in user's own
 * data is ever shown.
 */
class ProfileController extends Controller
{
    /** Profile photos: images only, ≤ 1 MB, resized to ≤ 256 px where GD is available. */
    public const AVATAR_MAX_KB = 1024;
    public const AVATAR_MAX_PIXELS = 256;

    public function edit(Request $request, CompletedWorkService $completedWork): View
    {
        $user = $request->user();
        $now = OperationalTime::now();

        // Next 3 appointments this user attends (appointment_user), from now on.
        $nextAppointments = $user->appointments()
            ->with('client:id,business_name')
            ->whereIn('appointments.status', AppointmentTypes::activeStatuses())
            ->where(function ($query) use ($now) {
                $query->whereDate('appointment_date', '>', $now->toDateString())
                    ->orWhere(function ($today) use ($now) {
                        $today->whereDate('appointment_date', $now->toDateString())
                            ->where(fn ($time) => $time->whereNull('appointment_time')->orWhere('appointment_time', '>=', $now->format('H:i')));
                    });
            })
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->limit(3)
            ->get();

        return view('profile.edit', [
            'user' => $user,
            'completedToday' => $completedWork->forUser($user, $now)['total'],
            'nextAppointments' => $nextAppointments,
            'now' => $now,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+()\-\s]{6,50}$/'],
            'avatar' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.self::AVATAR_MAX_KB],
            'remove_avatar' => ['nullable', 'boolean'],
        ], [
            'phone.regex' => __('notify.profile.validation.phone'),
            'avatar.mimes' => __('notify.profile.validation.avatar_type'),
            'avatar.mimetypes' => __('notify.profile.validation.avatar_type'),
            'avatar.max' => __('notify.profile.validation.avatar_size'),
        ]);

        $attributes = [
            'name' => $data['name'],
            'job_title' => $data['job_title'] ?? null,
            'phone' => $data['phone'] ?? null,
        ];

        $previous = (string) $user->avatar_path;
        if ($request->hasFile('avatar')) {
            $attributes['avatar_path'] = $this->storeAvatar($request->file('avatar'));
        } elseif ($request->boolean('remove_avatar')) {
            $attributes['avatar_path'] = null;
        }

        $user->update($attributes);

        // Remove the replaced file only when it is one this feature stored.
        if (array_key_exists('avatar_path', $attributes) && $previous !== '' && $previous !== $attributes['avatar_path']
            && str_starts_with($previous, User::AVATAR_DIRECTORY.'/')) {
            Storage::disk('public')->delete($previous);
        }

        return redirect()->route('profile.edit')->with('success', __('notify.profile.updated'));
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);
        $request->user()->update(['password' => Hash::make($data['password'])]);

        return redirect()->to(route('profile.edit').'#password')->with('success', __('notify.profile.password_updated'));
    }

    /**
     * Stores a real image under a random name. The content is checked (not only the extension) and,
     * where GD is available, re-encoded at ≤ 256 px — which also strips metadata.
     */
    private function storeAvatar(UploadedFile $file): string
    {
        $info = @getimagesize($file->getRealPath());
        $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
        if ($info === false || ! isset($types[$info[2]])) {
            throw ValidationException::withMessages(['avatar' => __('notify.profile.validation.avatar_type')]);
        }

        $name = User::AVATAR_DIRECTORY.'/'.Str::random(40);
        $resized = $this->resize($file->getRealPath(), $info);
        if ($resized !== null) {
            Storage::disk('public')->put($name.'.jpg', $resized);

            return $name.'.jpg';
        }

        Storage::disk('public')->putFileAs(User::AVATAR_DIRECTORY, $file, basename($name).'.'.$types[$info[2]]);

        return $name.'.'.$types[$info[2]];
    }

    /** @param array<int|string, mixed> $info getimagesize() result */
    private function resize(string $path, array $info): ?string
    {
        if (! extension_loaded('gd')) {
            return null;
        }

        $source = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
        if (! $source) {
            return null;
        }

        [$width, $height] = [$info[0], $info[1]];
        $scale = min(1, self::AVATAR_MAX_PIXELS / max($width, $height, 1));
        $targetW = max(1, (int) round($width * $scale));
        $targetH = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetW, $targetH);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetW, $targetH, $width, $height);

        ob_start();
        imagejpeg($canvas, null, 85);
        $binary = (string) ob_get_clean();
        imagedestroy($canvas);
        imagedestroy($source);

        return $binary !== '' ? $binary : null;
    }
}
