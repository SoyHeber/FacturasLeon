<?php

namespace App\Http\Controllers;

use App\Models\Venta;
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
}
