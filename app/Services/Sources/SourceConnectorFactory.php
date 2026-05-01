<?php

namespace App\Services\Sources;

use App\Models\Source;

class SourceConnectorFactory
{
    public static function make(Source $source): CsvSourceConnector|ApiSourceConnector|XmlSourceConnector
    {
        return match ($source->type) {
            'csv'    => new CsvSourceConnector($source),
            'xml'    => new XmlSourceConnector($source),
            'api'    => new ApiSourceConnector($source),
            default  => throw new \InvalidArgumentException("Type source inconnu : {$source->type}"),
        };
    }
}
