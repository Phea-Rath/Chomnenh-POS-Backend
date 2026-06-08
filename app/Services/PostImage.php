<?php

namespace App\Services;

use App\Models\Image as Images;
use App\Models\ItemImage;
use App\Models\ExpenseImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class PostImage
{
    public function uploadSingle(UploadedFile $file): string
    {
        // $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        // $file->storeAs('public/images', $filename);
        $filename = time() . '.webp';

        $image = Image::read($file)
            ->resize(null, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });

        $path = Storage::disk('public')->put(
            "images/$filename",
            $image->toWebp(40)
        );
        return $filename;
    }

    public function replaceSingle(?string $oldFilename, UploadedFile $file): string
    {
        if ($oldFilename && Storage::exists('public/images/' . $oldFilename)) {
            Storage::delete('public/images/' . $oldFilename);
        }

        return $this->uploadSingle($file);
    }

    public function deleteSingle(?string $oldFilename){
        if ($oldFilename && Storage::exists('public/images/' . $oldFilename)) {
            Storage::delete('public/images/' . $oldFilename);
        }
    }

    public function attachItemImages(int $itemId, array $files): array
    {
        $createdImages = [];

        foreach ($files as $file) {
            $filename = $this->uploadSingle($file);
            $image = Images::create([
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

    public function replaceItemImages(?array $imageIds, int $item_id, array $files): array
    {
        $createdImages = [];

        if(!$imageIds || !count($imageIds) > 0 ){
            $images = $this->attachItemImages($item_id, $files);
            return $images;
        }

        $images = Images::whereIn('id', $imageIds)->get();


        foreach ($images as $key => $image) {
            $filename = $this->uploadSingle($files[$key]);
            $image = Images::find($image->id);

            if(!empty($image)){
                $image->update([
                    'image'=> $filename
                ]);
            }

            $createdImages[] = [
                'image_id' => $image->id,
                'image' => url('storage/images/' . $filename),
            ];
        }
        foreach ($images as $image) {
            if ($image->image && Storage::exists('public/images/' . $image->image)) {
                Storage::delete('public/images/' . $image->image);
            }
        }

        return $createdImages;
    }

    public function attachExpenseImages(int $expenseId, array $files): array
    {
        $createdImages = [];

        foreach ($files as $file) {
            $filename = $this->uploadSingle($file);
            $image = Images::create([
                'image' => $filename,
            ]);

            ExpenseImage::create([
                'expense_id' => $expenseId,
                'image_id' => $image->id,
            ]);

            $createdImages[] = [
                'image_id' => $image->id,
                'image' => url('storage/images/' . $filename),
            ];
        }

        return $createdImages;
    }

    public function replaceExpenseImages(?array $imageIds, int $expenseId, array $files): array
    {
        $createdImages = [];

        if (!$imageIds || !count($imageIds) > 0) {
            return $this->attachExpenseImages($expenseId, $files);
        }

        $images = Images::whereIn('id', $imageIds)->get();

        foreach ($images as $key => $image) {
            if (isset($files[$key])) {
                $filename = $this->uploadSingle($files[$key]);
                $image->update(['image' => $filename]);

                $createdImages[] = [
                    'image_id' => $image->id,
                    'image' => url('storage/images/' . $filename),
                ];
            }
        }
        return $createdImages;
    }

    public function deleteExpenseImages(array $imageIds): void
    {
        $images = Images::whereIn('id', $imageIds)->get();

        foreach ($images as $image) {
            if ($image->image && Storage::exists('public/images/' . $image->image)) {
                Storage::delete('public/images/' . $image->image);
            }
        }

        ExpenseImage::whereIn('image_id', $imageIds)->delete();
        Images::whereIn('id', $imageIds)->delete();
    }

    public function deleteItemImages(array $imageIds): void
    {
        $images = Images::whereIn('id', $imageIds)->get();

        foreach ($images as $image) {
            if ($image->image && Storage::exists('public/images/' . $image->image)) {
                Storage::delete('public/images/' . $image->image);
            }
        }

        ItemImage::whereIn('image_id', $imageIds)->delete();
        Images::whereIn('id', $imageIds)->delete();
    }

    public function deleteItemImagesByItemId(int $id): void
    {
        $images = ItemImage::where('id', $id)
            ->join('images', 'images.id', '=', 'item_image.image_id')->get();

        foreach ($images as $image) {
            if ($image->image && Storage::exists('public/images/' . $image->image)) {
                Storage::delete('public/images/' . $image->image);
            }
        }
        $imageIds = $images->pluck('id')->toArray();

        ItemImage::whereIn('image_id', $imageIds)->delete();
        Images::whereIn('id', $imageIds)->delete();
    }
}
