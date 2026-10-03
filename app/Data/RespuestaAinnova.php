<?php

namespace App\Data;

final class RespuestaAinnova
{
    public const RESPUESTA = 'RESPUESTA';

    public const TEXTUAL = 'TEXTUAL';

    public const TRANSPORTE = 'TRANSPORTE';

    public const SOAP_FAULT = 'SOAP_FAULT';

    public const TIMEOUT = 'TIMEOUT';

    public const HTTP = 'HTTP';

    public const CONFIGURACION = 'CONFIGURACION';

    public function __construct(
        public readonly string $tipo,
        public readonly ?string $texto = null,
        public readonly ?string $respuestaRaw = null,
        public readonly ?string $codigoTecnico = null,
        public readonly ?string $mensajeTecnico = null,
        public readonly int $duracionMs = 0
    ) {}
}
