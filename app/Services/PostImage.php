<?php

namespace App\Services;

use App\Models\Image;
use App\Models\ItemImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PostImage
{
    public function uploadSingle(UploadedFile $file): string
    {
        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->storeAs('public/images', $filename);

        return $filename;
    }

    public function replaceSingle(?string $oldFilename, UploadedFile $file): string
    {
        if ($oldFilename && Storage::exists('public/images/' . $oldFilename)) {
            Storage::delete('public/images/' . $oldFilename);
        }

        return $this->uploadSingle($file);
    }

    public function attachItemImages(int $itemId, array $files): array
    {
        $createdImages = [];

        foreach ($files as $file) {
            $filename = $this->uploadSingle($file);
            $image = Image::create([
                'image' => $filename,
            ]);

            ItemImage::create([
                'item_id' => $itemId,
                'image_id' => $image->id,
            ]);

            $createdImages[] = [
                'image_id' => $image->id,
                'image' => url('storage/images/' . $filename),
            ];
        }

        return $createdImages;
    }

    public function deleteItemImages(array $imageIds): void
    {
        $images = Image::whereIn('id', $imageIds)->get();

        foreach ($images as $image) {
            if ($image->image && Storage::exists('public/images/' . $image->image)) {
                Storage::delete('public/images/' . $image->image);
            }
        }

        ItemImage::whereIn('image_id', $imageIds)->delete();
        Image::whereIn('id', $imageIds)->delete();
    }
}
