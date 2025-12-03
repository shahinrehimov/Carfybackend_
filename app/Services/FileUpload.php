<?php

namespace App\Services;

use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class FileUpload
{
    public static function upload($request, $input, $collection, $model, bool $return_error = false): string
    {
        if($request->hasFile($input) && $request->file($input)?->isValid()) {
            try {
                $model->clearMediaCollection($collection);
                $model->addMediaFromRequest($input)->toMediaCollection($collection);

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
            try {
                $model->addMultipleMediaFromRequest([$input])
                    ->each(function ($fileAdder) use ($collection) {
                        $fileAdder->toMediaCollection($collection);
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
}
