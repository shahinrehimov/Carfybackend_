<?php

namespace App\Services;


use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class RouteService
{
    private static ?RouteService $instance = null;
    private ?string $name = null;

    private function __construct()
    {

    }

    public static function instance(): RouteService
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): string
    {
        if ($this->name === null) {
            throw new BadRequestHttpException(
                LangService::instance()
                    ->setDefault('Route name not set!')
                    ->getLang('route_name_not_set')
            );
        }

        return $this->name;
    }
}
