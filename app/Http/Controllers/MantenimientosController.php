<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Mantenimientos;
use App\Models\Vehiculos;
use App\Models\Proveedores;
use App\Models\Reparaciones;
use App\Models\Detalles_Mantenimiento;
use Datatables;
use Barryvdh\DomPDF\Facade\Pdf;

class MantenimientosController extends Controller
{

    public function index()
    {
        $proveedores = Proveedores::all();
        $reparaciones = Reparaciones::all();
        
        $vehiculos = Vehiculos::all();
        if(request()->ajax()) {
            return datatables()->of(DB::table('mantenimientos')
            ->join('proveedores', 'mantenimientos.proveedores_id', '=', 'proveedores.id')
            ->join('vehiculos', 'mantenimientos.vehiculos_id', '=', 'vehiculos.id')
            ->select('mantenimientos.id', 'mantenimientos.tipo', 'mantenimientos.expediente', 'mantenimientos.fecha_requerimiento', 'mantenimientos.fecha_conformidad_servicio', 'mantenimientos.fecha_ingreso_taller', 'mantenimientos.fecha_salida_taller', 
            DB::raw('CONCAT(COALESCE(vehiculos.unidad, ""), " ", COALESCE(vehiculos.marca, ""), " ", COALESCE(vehiculos.modelo, ""), " - PLACA: ", COALESCE(vehiculos.placa, "")) as vehiculox'), 
            DB::raw('CONCAT("RUC: ", COALESCE(proveedores.ruc, ""), " ", COALESCE(proveedores.nombre, "")) as proveedorx'))
        ->get())
            ->addColumn('action', 'mantenimientos-action')
            ->rawColumns(['action'])
            ->addIndexColumn()
            ->make(true);
        }
        return view('mantenimientos',compact('proveedores','vehiculos','reparaciones'));
    }
    
/*     private function calcularEstado($estMantenimiento){
        if($estMantenimiento == 'Pendiente'){
            $estado = 'bg-warning text-white';
        }else if($estMantenimiento == 'En Curso'){
            $estado = 'bg-info text-white';
        }else if($estMantenimiento == 'Completado'){
            $estado = 'bg-success text-white';
        }else if($estMantenimiento == 'Cancelado'){
            $estado = 'bg-danger text-white';
        }
        return $estado;
    } */
    public function store(Request $request)
    {  
    
        $validator = Validator::make($request->all(), [
            'expediente' => 'required|unique:mantenimientos,expediente,' . $request->id,
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ya existe un mantenimiento registrado con ese expediente. Por favor, ingresa uno diferente.'
            ], 422);
        };
    
        $mantenimientosId = $request->id;
    
        $mantenimientos = Mantenimientos::updateOrCreate(
            [
                'id' => $mantenimientosId
            ],
            [
                'tipo' => $request->tipo,
                'expediente' => $request->expediente,
                'fecha_requerimiento' => $request->fecha_requerimiento,
                'fecha_conformidad_servicio' => $request->fecha_conformidad_servicio,
                'fecha_ingreso_taller' => $request->fecha_ingreso_taller,
                'fecha_salida_taller' => $request->fecha_salida_taller,
                'vehiculos_id' => $request->vehiculos_id,
                'proveedores_id' => $request->proveedores_id,
            ]
        );
    
        // Obtener los detalles de mantenimiento actuales
        $existingDetalles = $mantenimientos->detallesMantenimiento()->pluck('reparaciones_id')->toArray();
        $selectedReparaciones = array_keys($request->precios);
    
        // Identificar detalles de mantenimiento a eliminar
        $detallesToDelete = array_diff($existingDetalles, $selectedReparaciones);
        
        // Eliminar los detalles de mantenimiento que coincidan
        if (!empty($detallesToDelete)) {
            Detalles_Mantenimiento::where('mantenimientos_id', $mantenimientos->id)
                ->whereIn('reparaciones_id', $detallesToDelete)
                ->delete();
        }
    
        // Identificar detalles de mantenimiento a crear o actualizar
        foreach ($request->precios as $reparacion_id => $costo) {
            $descripcion = $request->descripcion[$reparacion_id] ?? ''; // Obtén la descripción o cadena vacía si no se proporciona
            Detalles_Mantenimiento::updateOrCreate(
                ['reparaciones_id' => $reparacion_id, 'mantenimientos_id' => $mantenimientos->id],
                ['costo' => $costo, 'descripcion' => $descripcion]
            );
        }

    
        return Response()->json($mantenimientos);
    }
    
    

    public function edit(Request $request)
    {   
        $mantenimientos  = Mantenimientos::with('detallesMantenimiento')->find($request->id);
        return Response()->json($mantenimientos);
    }
    


    public function destroy(Request $request)
{
    $mantenimiento = Mantenimientos::find($request->id);

    // Eliminar los detalles de mantenimiento asociados
    $mantenimiento->detallesMantenimiento()->delete();

    // Eliminar el mantenimiento
    $mantenimiento->delete();

    return response()->json(['success' => true]);
}

public function print(Request $request)
{
    $mantenimiento = Mantenimientos::findOrFail($request->id);

    $pdf = PDF::loadView('reports.mantenimientos_report', compact('mantenimiento'))->setPaper('a4');

    
    // Guarda el PDF temporalmente
    $pdfPath = public_path('mantenimientos_report.pdf');
    $pdf->save($pdfPath);

    // Devuelve la URL del PDF
    return response()->json(['url' => url('mantenimientos_report.pdf')]);
}

public function printgen() {
    $mantenimientos = Mantenimientos::all();

    $pdf = PDF::loadView('reports.mantenimientos_report_gen', compact('mantenimientos'))->setPaper('a4','landscape');


    // Guarda el PDF temporalmente
    $pdfPath = public_path('mantenimientos_report_gen.pdf');
    $pdf->save($pdfPath);

    // Devuelve la URL del PDF
    return response()->json(['url' => url('mantenimientos_report_gen.pdf')]);
}


}