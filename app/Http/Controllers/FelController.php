<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Services\AinnovaFelConsultaClient;
use App\Services\FelCertificacionService;
use Illuminate\Http\Request;

class FelController extends Controller
{
    public function __construct(private FelCertificacionService $certificacion) {}

    public function certificar(Request $request, Venta $venta)
    {
        $documento = $this->certificacion->certificarVenta((int) $venta->id, (int) $request->user()->id);
        $mensaje = match ($documento->estado_fel) {
            'CERTIFICADA' => 'Documento FEL certificado correctamente.',
            'ERROR' => 'Ainnova rechazó el documento. Consulte el mensaje en la Venta.',
            default => 'Resultado FEL INCIERTA. Requiere conciliación antes de cualquier nuevo envío.',
        };

        return redirect()->route('ventas.show', $venta)->with($documento->estado_fel === 'CERTIFICADA' ? 'success' : 'warning', $mensaje);
    }

    public function conciliarRespuesta(Request $request, Venta $venta)
    {
        $documento = $this->certificacion->conciliarDesdeRespuestaExistente((int) $venta->documentoFel()->firstOrFail()->id, (int) $request->user()->id);
        $mensaje = $documento->estado_fel === 'CERTIFICADA'
            ? 'Respuesta guardada conciliada: documento FEL CERTIFICADA. No se realizó una nueva llamada a Ainnova.'
            : 'La respuesta guardada no acredita una certificación válida. El documento continúa INCIERTA; no se llamó a Ainnova.';

        return redirect()->route('ventas.show', $venta)->with($documento->estado_fel === 'CERTIFICADA' ? 'success' : 'warning', $mensaje);
    }

    public function reintentarCertificacion(Request $request, Venta $venta)
    {
        $datos = $request->validate(['ultimo_intento_id' => ['required', 'integer', 'min:0']]);
        $documento = $this->certificacion->reintentarCertificacion((int) $venta->documentoFel()->firstOrFail()->id,
            (int) $request->user()->id, (int) $datos['ultimo_intento_id']);
        $mensaje = match ($documento->estado_fel) {
            'CERTIFICADA' => 'Documento FEL CERTIFICADA usando la misma referencia y XML.',
            'ERROR' => 'Ainnova rechazó el documento. Consulte el mensaje en la Venta.',
            default => 'El reintento continúa INCIERTA. Revise las respuestas y el historial antes de otro envío.',
        };

        return redirect()->route('ventas.show', $venta)->with($documento->estado_fel === 'CERTIFICADA' ? 'success' : 'warning', $mensaje);
    }

    public function recuperarCertificacion(Request $request, Venta $venta)
    {
        $documento = $this->certificacion->recuperarCertificacion((int) $venta->documentoFel()->firstOrFail()->id, (int) $request->user()->id);

        return redirect()->route('ventas.show', $venta)->with($documento->estado_fel === 'CERTIFICADA' ? 'success' : 'warning',
            $documento->estado_fel === 'CERTIFICADA' ? 'Certificación recuperada sin nuevo envío.' : 'Intento abandonado cerrado como INCIERTA. Ahora puede conciliar o reintentar con la misma referencia y XML.');
    }

    public function consultarRepresentacion(Request $request, Venta $venta, string $tipo, AinnovaFelConsultaClient $consulta)
    {
        $url = $consulta->consultar((int) $venta->documentoFel()->firstOrFail()->id, strtoupper($tipo));

        return redirect()->away($url)->withHeaders(['Referrer-Policy' => 'no-referrer', 'Cache-Control' => 'no-store']);
    }
}
