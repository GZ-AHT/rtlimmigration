<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Support\Cms\MediaUsage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Website images (REQ §21 "Image Upload", §23.4 File & Media). The picker in
 * every editor uses the JSON endpoints; the library page manages them.
 * Raster images and MP4 / WebM videos only: SVG can carry scripts, so it is
 * refused. File types are checked from the contents, not the name.
 */
class MediaController extends Controller
{
    public const MAX_KB = 5120;

    public const MIMES = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /** Banner background videos (homepage hero designs). */
    public const VIDEO_MAX_KB = 51200;

    public const VIDEO_MIMES = ['mp4', 'webm'];

    public function index(Request $request): Response|JsonResponse
    {
        Gate::authorize('media.view');

        $filters = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'kind' => ['nullable', 'in:image,video'], 'missing_alt' => ['nullable', 'boolean']]);
        $search = (string) ($filters['search'] ?? '');
        $kind = $filters['kind'] ?? null;
        $missingAlt = (bool) ($filters['missing_alt'] ?? false);

        $media = Media::query()
            ->when($kind === 'video', fn (Builder $q) => $q->where('mime_type', 'like', 'video/%'))
            // REQ §22.3 image alt text management: find images that still need a description.
            ->when($missingAlt, fn (Builder $q) => $q->where('mime_type', 'not like', 'video/%')->where(fn (Builder $q) => $q->whereNull('alt')->orWhere('alt', '')))
            ->when($kind === 'image', fn (Builder $q) => $q->where('mime_type', 'not like', 'video/%'))
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q->where('original_name', 'like', '%'.addcslashes($search, '%_\\').'%')->orWhere('alt', 'like', '%'.addcslashes($search, '%_\\').'%')))
            ->latest('id')
            ->paginate($request->wantsJson() ? 24 : 36)
            ->withQueryString();

        if ($request->wantsJson()) {
            return response()->json($media->through(fn (Media $m) => $m->present()));
        }

        // The picker inside editors only needs the file; the library page also shows where it is used.
        $usage = MediaUsage::for(array_values(array_map(intval(...), $media->pluck('id')->all())));
        $media = $media->through(fn (Media $m) => [...$m->present(), 'used_in' => $usage[$m->id] ?? []]);

        return Inertia::render('admin/media/Index', [
            'media' => $media,
            'filters' => ['search' => $search, 'missing_alt' => $missingAlt ? '1' : ''],
            'limits' => ['max_mb' => self::MAX_KB / 1024, 'extensions' => self::MIMES, 'video_max_mb' => self::VIDEO_MAX_KB / 1024, 'video_extensions' => self::VIDEO_MIMES],
            'canManage' => $request->user()->can('media.manage'),
        ]);
    }

    /** Uploads from the library page and from the picker in any editor. */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        // Editors that use images can upload without full media rights.
        abort_unless($request->user()->canAny(['media.manage', 'blog.manage', 'pages.manage', 'cms.manage', 'universities.manage']), 403);

        $isVideo = $request->hasFile('file') && in_array(strtolower((string) $request->file('file')?->getClientOriginalExtension()), self::VIDEO_MIMES, true);

        $data = $request->validate([
            'file' => $isVideo
                ? ['required', 'file', 'max:'.self::VIDEO_MAX_KB, 'mimes:'.implode(',', self::VIDEO_MIMES), 'mimetypes:video/mp4,video/webm']
                : ['required', 'file', 'max:'.self::MAX_KB, 'mimes:'.implode(',', self::MIMES), 'dimensions:max_width=8000,max_height=8000'],
            'alt' => ['nullable', 'string', 'max:255'],
        ], [
            'file.mimes' => $isVideo ? __('Upload an MP4 or WebM video.') : __('Upload a JPG, PNG, WEBP or GIF image, or an MP4 or WebM video.'),
            'file.mimetypes' => __('Upload an MP4 or WebM video.'),
            'file.max' => $isVideo ? __('Videos can be up to :mb MB.', ['mb' => self::VIDEO_MAX_KB / 1024]) : __('Images can be up to :mb MB.', ['mb' => self::MAX_KB / 1024]),
        ]);

        $file = $data['file'];
        $size = $isVideo ? [null, null] : (@getimagesize($file->getRealPath()) ?: [null, null]);
        $path = $file->storeAs('media/'.now()->format('Y/m'), Str::random(32).'.'.$file->extension(), 'public');

        $media = Media::create([
            'disk' => 'public',
            'path' => (string) $path,
            'original_name' => Str::limit(basename($file->getClientOriginalName()), 180, ''),
            'mime_type' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'width' => $size[0],
            'height' => $size[1],
            'alt' => $data['alt'] ?? null,
            'uploaded_by' => $request->user()->id,
        ]);

        if ($request->wantsJson()) {
            return response()->json($media->present(), 201);
        }

        $this->toast(['type' => 'success', 'message' => $media->isVideo() ? __('Video uploaded.') : __('Image uploaded.')]);

        return back();
    }

    public function update(Request $request, Media $media): RedirectResponse|JsonResponse
    {
        Gate::authorize('media.manage');

        $media->update($request->validate(['alt' => ['nullable', 'string', 'max:255']]));

        return $request->wantsJson() ? response()->json($media->present()) : back();
    }

    /**
     * A file that pages, posts or settings still use is not deleted unless the
     * request says `force` (the library asks the person first), so a page is
     * never left with a missing image by accident.
     */
    public function destroy(Request $request, Media $media): RedirectResponse
    {
        Gate::authorize('media.manage');

        $usage = MediaUsage::for([$media->id])[$media->id] ?? [];

        if ($usage !== [] && ! $request->boolean('force')) {
            $this->toast(['type' => 'error', 'message' => __('This file is used in :count place(s), so it was not deleted.', ['count' => count($usage)])]);

            return back();
        }

        $media->delete();

        if ($usage !== []) {
            activity('cms')->causedBy($request->user())->withProperties(['file' => $media->original_name, 'was_used_in' => $usage])->log('In-use media file deleted');
        }

        $this->toast(['type' => 'success', 'message' => __('File deleted.')]);

        return back();
    }
}
