<?php

declare(strict_types=1);

namespace Plan2net\Webp\Converter;

/**
 * Class AbstractConverter
 *
 * @author Wolfgang Klinger <wk@plan2.net>
 */
abstract class AbstractConverter implements Converter
{
    /**
     * @var string
     */
    protected $parameters;

    public function __construct(string $parameters)
    {
        $this->parameters = $parameters;
    }

    abstract public function convert(string $originalFilePath, string $targetFilePath): void;
}