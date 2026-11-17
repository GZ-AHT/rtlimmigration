<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
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
 * Raster images only: SVG can carry scripts, so it is refused.
 */
class MediaController extends Controller
{
    public const MAX_KB = 5120;

    public const MIMES = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    public function index(Request $request): Response|JsonResponse
    {
        Gate::authorize('media.view');

        $search = (string) ($request->validate(['search' => ['nullable', 'string', 'max:100']])['search'] ?? '');

        $media = Media::query()
            ->when($search !== '', fn (Builder $q) => $q->where(fn (Builder $q) => $q->where('original_name', 'like', '%'.addcslashes($search, '%_\\').'%')->orWhere('alt', 'like', '%'.addcslashes($search, '%_\\').'%')))
            ->latest('id')
            ->paginate($request->wantsJson() ? 24 : 36)
            ->withQueryString()
            ->through(fn (Media $m) => $m->present());

        if ($request->wantsJson()) {
            return response()->json($media);
        }

        return Inertia::render('admin/media/Index', [
            'media' => $media,
            'filters' => ['search' => $search],
            'limits' => ['max_mb' => self::MAX_KB / 1024, 'extensions' => self::MIMES],
            'canManage' => $request->user()->can('media.manage'),
        ]);
    }

    /** Uploads from the library page and from the picker in any editor. */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        // Editors that use images can upload without full media rights.
        abort_unless($request->user()->canAny(['media.manage', 'blog.manage', 'pages.manage', 'cms.manage', 'universities.manage']), 403);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.self::MAX_KB, 'mimes:'.implode(',', self::MIMES), 'dimensions:max_width=8000,max_height=8000'],
            'alt' => ['nullable', 'string', 'max:255'],
        ], ['file.mimes' => __('Upload a JPG, PNG, WEBP or GIF image.')]);

        $file = $data['file'];
        $size = @getimagesize($file->getRealPath()) ?: [null, null];
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

        $this->toast(['type' => 'success', 'message' => __('Image uploaded.')]);

        return back();
    }

    public function update(Request $request, Media $media): RedirectResponse|JsonResponse
    {
        Gate::authorize('media.manage');

        $media->update($request->validate(['alt' => ['nullable', 'string', 'max:255']]));

        return $request->wantsJson() ? response()->json($media->present()) : back();
    }

    public function destroy(Media $media): RedirectResponse
    {
        Gate::authorize('media.manage');

        $media->delete();
        $this->toast(['type' => 'success', 'message' => __('Image deleted.')]);

        return back();
    }
}
