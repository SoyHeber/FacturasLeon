<?php

namespace App\Services;

use SoapClient;

class AinnovaSoapClient extends SoapClient
{
    private ?string $respuestaRaw = null;

    public function __doRequest(string $request, string $location, string $action, int $version, bool $oneWay = false): ?string
    {
        // Se conserva únicamente la respuesta; nunca se habilita trace del request con passwords.
        $respuesta = parent::__doRequest($request, $location, $action, $version, $oneWay);
        $this->respuestaRaw = $respuesta;

        return $respuesta;
    }

    public function respuestaRaw(): ?string
    {
        return $this->respuestaRaw;
    }
}
