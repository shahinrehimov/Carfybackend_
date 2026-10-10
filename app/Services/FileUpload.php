<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class FileUpload
{
    /**
     * Every extension that may ever land on disk. Request rules narrow this per field;
     * this list is the last line of defence so nothing executable is ever stored.
     */
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
    private const VIDEO_EXTENSIONS = ['mp4', 'webm', 'mov', 'avi', 'mkv'];
    private const DOCUMENT_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];

    public static function upload($request, $input, $collection, $model, bool $return_error = false): string
    {
        if($request->hasFile($input) && $request->file($input)?->isValid()) {
            $fileName = self::safeFileName($request->file($input), $input);

            try {
                $model->clearMediaCollection($collection);
                $model->addMediaFromRequest($input)
                    ->usingFileName($fileName)
                    ->toMediaCollection($collection);

                return '';
            } catch (FileDoesNotExist|FileIsTooBig $exception) {
                if ($return_error) {
                    throw new BadRequestHttpException($exception->getMessage());
                }

                LoggerService::instance()
                    ->log('FileUpload error. upload:' . $exception->getMessage(), [], true, 'error');

                return ' ' . LangService::instance()
                        ->setDefault('An error occurred while uploading the file')
                        ->getLang('error_when_file_upload');
            }
        }

        return '';
    }

    public static function multipleUpload($request, $input, $collection, $model, bool $return_error = false): string
    {
        if($request->hasFile($input)) {
            $files = $request->file($input);
            $fileNames = array_map(
                static fn (UploadedFile $file) => self::safeFileName($file, $input),
                is_array($files) ? array_values($files) : [$files],
            );

            try {
                $model->addMultipleMediaFromRequest([$input])
                    ->values()
                    ->each(function ($fileAdder, $index) use ($collection, $fileNames) {
                        $fileAdder->usingFileName($fileNames[$index])->toMediaCollection($collection);
                    });

                return '';
            } catch (FileDoesNotExist|FileIsTooBig $exception) {
                if ($return_error) {
                    throw new BadRequestHttpException($exception->getMessage());
                }

                LoggerService::instance()
                    ->log('FileUpload error. multipleUpload:' . $exception->getMessage(), [], true, 'error');

                return ' ' . LangService::instance()
                        ->setDefault('An error occurred while uploading the file')
                        ->getLang('error_when_file_upload');
            }
        }

        return  '';
    }

    /**
     * Builds a random on-disk name whose extension comes from a whitelist, never from the client name alone.
     * Images and videos must also be what their content says they are.
     *
     * @throws ValidationException
     */
    private static function safeFileName(UploadedFile $file, string $input): string
    {
        $clientExtension = strtolower($file->getClientOriginalExtension());
        $guessedExtension = strtolower((string) $file->guessExtension());

        $extension = match (true) {
            in_array($clientExtension, self::IMAGE_EXTENSIONS, true)
                => in_array($guessedExtension, self::IMAGE_EXTENSIONS, true) ? $guessedExtension : null,
            in_array($clientExtension, self::VIDEO_EXTENSIONS, true)
                => in_array($guessedExtension, self::VIDEO_EXTENSIONS, true) ? $clientExtension : null,
            // Office files are often sniffed as zip/octet-stream, so trust the whitelisted client extension
            in_array($clientExtension, self::DOCUMENT_EXTENSIONS, true) => $clientExtension,
            default => null,
        };

        if ($extension === null) {
            throw ValidationException::withMessages([
                $input => LangService::instance()
                    ->setDefault('This file type is not allowed')
                    ->getLang('file_type_not_allowed'),
            ]);
        }

        return Str::uuid() . '.' . $extension;
    }
}
